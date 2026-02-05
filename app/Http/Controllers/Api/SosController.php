<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SosRequest;
use App\Models\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Manual SOS trigger (from mobile app)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid SOS data',
                'errors' => $validator->errors()
            ], 422);
        }

        $sos = SosRequest::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'description' => $request->description,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'coordinates' => DB::raw(
                "ST_GeomFromText('POINT({$request->longitude} {$request->latitude})')"
            ),
        ]);

        // Optional auto-dispatch logic
        $this->notifyNearestResponders($sos);

        // Audit log (same pattern as accidents)
        AuditLogController::log(
            auth()->id(),
            'created',
            SosRequest::class,
            $sos->id,
            null,
            $sos->toArray(),
            'Manual SOS triggered'
        );

        return response()->json([
            'message' => 'SOS sent successfully',
            'sos' => $sos
        ], 201);
    }

    /**
     * Get SOS history (user/admin)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = SosRequest::with('user')
            ->orderByDesc('triggered_at');

        if ($user->isRegularUser()) {
            $query->where('user_id', $user->id);
        }

        return response()->json(
            $query->paginate($request->per_page ?? 15)
        );
    }

    /**
     * Auto-find nearest responders (non-blocking logic)
     */
    private function notifyNearestResponders(SosRequest $sos, $radius = 5)
    {
        $responders = Responder::where('status', 'active')
            ->where('availability', 'available')
            ->whereRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                ) <= ? * 1000
            ", [$sos->longitude, $sos->latitude, $radius])
            ->orderByRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                )
            ", [$sos->longitude, $sos->latitude])
            ->limit(2)
            ->get();

        // Future expansion: attach responders, send push notifications, etc.
    }
}
