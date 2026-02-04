<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accident;
use App\Models\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



class AccidentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Accident::with(['reporter', 'responders', 'vehicles', 'media']);

        // Apply role-based filters
        if ($user->isRegularUser()) {
            $query->where('reported_by', $user->id);
        } elseif ($user->isResponder()) {
            $query->whereHas('responders', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->has('date_from')) {
            $query->whereDate('reported_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('reported_at', '<=', $request->date_to);
        }

        // Spatial search (within radius)
        if ($request->has(['latitude', 'longitude', 'radius'])) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius; // in kilometers

            $query->whereRaw("
                ST_Distance_Sphere(
                    POINT(longitude, latitude),
                    POINT(?, ?)
                ) <= ? * 1000
            ", [$longitude, $latitude, $radius]);
        }

        $accidents = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json($accidents);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'required|in:low,medium,high,critical',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'injured_count' => 'integer|min:0',
            'casualty_count' => 'integer|min:0',
            'emergency_contacts' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->all();
        $data['reported_by'] = auth()->id();
        
        // Create geometry from lat/long
        $data['coordinates'] = DB::raw("ST_GeomFromText('POINT(" . $data['longitude'] . " " . $data['latitude'] . ")')");

        $accident = Accident::create($data);

        // Find and assign nearest available responders
        $this->assignNearestResponders($accident);

        // Create audit log
        AuditLogController::log(
            auth()->id(),
            'created',
            Accident::class,
            $accident->id,
            null,
            $accident->toArray(),
            'Accident reported at ' . $accident->location
        );

        return response()->json([
            'message' => 'Accident reported successfully',
            'accident' => $accident->load(['reporter', 'responders'])
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $accident = Accident::with(['reporter', 'responders.user', 'vehicles', 'media'])->findOrFail($id);

        // Check authorization
        if ($user->isRegularUser() && $accident->reported_by !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($accident);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $accident = Accident::findOrFail($id);

        // Authorization check
        if (!$user->isAdmin() && $accident->reported_by !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:reported,dispatched,in_progress,resolved,cancelled',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'description' => 'sometimes|string',
            'injured_count' => 'sometimes|integer|min:0',
            'casualty_count' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $oldData = $accident->toArray();
        $accident->update($request->all());

        if ($request->has('status') && $request->status === 'resolved') {
            $accident->resolved_at = now();
            $accident->save();
        }

        // Create audit log
        AuditLogController::log(
            auth()->id(),
            'updated',
            Accident::class,
            $accident->id,
            $oldData,
            $accident->toArray(),
            'Accident updated'
        );

        return response()->json([
            'message' => 'Accident updated successfully',
            'accident' => $accident
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $accident = Accident::findOrFail($id);
        
        // Create audit log before deletion
        AuditLogController::log(
            auth()->id(),
            'deleted',
            Accident::class,
            $accident->id,
            $accident->toArray(),
            null,
            'Accident deleted'
        );

        $accident->delete();

        return response()->json(['message' => 'Accident deleted successfully']);
    }

    public function assignResponders(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'responder_ids' => 'required|array',
            'responder_ids.*' => 'exists:responders,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $accident = Accident::findOrFail($id);
        $accident->responders()->sync($request->responder_ids);

        return response()->json([
            'message' => 'Responders assigned successfully',
            'accident' => $accident->load('responders')
        ]);
    }

    private function assignNearestResponders(Accident $accident, $radius = 10)
    {
        // Find available responders within radius
        $responders = Responder::where('status', 'active')
            ->where('availability', 'available')
            ->whereRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                ) <= ? * 1000
            ", [$accident->longitude, $accident->latitude, $radius])
            ->orderByRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                )
            ", [$accident->longitude, $accident->latitude])
            ->limit(3) // Assign up to 3 nearest responders
            ->get();

        $accident->responders()->attach($responders->pluck('id')->toArray());

        // Update responder status
        foreach ($responders as $responder) {
            $responder->update(['availability' => 'on_duty']);
        }
    }
}