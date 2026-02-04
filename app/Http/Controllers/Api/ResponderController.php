<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Responder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResponderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin,superadmin')->except(['index', 'show', 'updateLocation']);
    }

    public function index(Request $request)
    {
        $query = Responder::with(['user', 'vehicles', 'accidents']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('availability')) {
            $query->where('availability', $request->availability);
        }

        if ($request->has('department')) {
            $query->where('department', 'like', '%' . $request->department . '%');
        }

        // Nearby responders
        if ($request->has(['latitude', 'longitude', 'radius'])) {
            $query->whereRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                ) <= ? * 1000
            ", [$request->longitude, $request->latitude, $request->radius]);
        }

        $responders = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json($responders);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id|unique:responders',
            'department' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'badge_number' => 'required|string|max:50|unique:responders',
            'contact_number' => 'required|string|max:20',
            'emergency_contact' => 'nullable|string|max:20',
            'joined_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->all();
        $data['admin_id'] = auth()->id();

        $responder = Responder::create($data);

        // Update user role
        User::where('id', $request->user_id)->update(['role' => 'responder']);

        return response()->json([
            'message' => 'Responder created successfully',
            'responder' => $responder->load('user')
        ], 201);
    }

    public function updateLocation(Request $request)
    {
        $user = auth()->user();
        $responder = $user->responder;

        if (!$responder) {
            return response()->json(['error' => 'User is not a responder'], 400);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $responder->update([
            'current_latitude' => $request->latitude,
            'current_longitude' => $request->longitude,
            'location_coordinates' => DB::raw("ST_GeomFromText('POINT(" . $request->longitude . " " . $request->latitude . ")')"),
            'last_active_at' => now()
        ]);

        return response()->json([
            'message' => 'Location updated successfully',
            'responder' => $responder
        ]);
    }

    public function updateAvailability(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'availability' => 'required|in:available,on_duty,on_break,off_duty'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $responder = Responder::findOrFail($id);
        $responder->update([
            'availability' => $request->availability,
            'last_active_at' => now()
        ]);

        return response()->json([
            'message' => 'Availability updated successfully',
            'responder' => $responder
        ]);
    }
}