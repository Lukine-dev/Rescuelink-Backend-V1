<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of vehicles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Vehicle::with('admin:id,first_name,last_name,email');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('vehicle_type')) {
            $query->where('vehicle_type', $request->vehicle_type);
        }

        if ($request->has('license_plate')) {
            $query->where('license_plate', 'like', '%' . $request->license_plate . '%');
        }

        if ($request->has('model')) {
            $query->where('model', 'like', '%' . $request->model . '%');
        }

        // Spatial search (within radius)
        if ($request->has(['latitude', 'longitude', 'radius'])) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius; // in kilometers

            $query->whereRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                ) <= ? * 1000
            ", [$longitude, $latitude, $radius]);
        }

        // Search by current location
        if ($request->has('current_location')) {
            $query->where('current_location', 'like', '%' . $request->current_location . '%');
        }

        // Regular users and responders can only see available vehicles
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            $query->where('status', 'available');
        }

        // Apply sorting
        $sortBy = $request->sort_by ?? 'created_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $vehicles = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $vehicles
        ]);
    }

    /**
     * Store a newly created vehicle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // Only admin and superadmin can create vehicles
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can create vehicles.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'license_plate' => 'required|string|max:20|unique:vehicles',
            'vehicle_type' => 'required|in:ambulance,fire_truck,police_car,rescue_van,motorcycle,helicopter,other',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'status' => 'sometimes|in:available,in_use,maintenance,out_of_service',
            'current_location' => 'nullable|string|max:255',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
            'fuel_level' => 'nullable|integer|min:0|max:100',
            'odometer_reading' => 'nullable|integer|min:0',
            'last_maintenance' => 'nullable|date',
            'next_maintenance' => 'nullable|date|after_or_equal:last_maintenance',
            'equipment_list' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['admin_id'] = $user->id;

        // Create geometry from lat/long if provided
        if ($request->has('current_latitude') && $request->has('current_longitude')) {
            $data['vehicle_coordinates'] = DB::raw("ST_GeomFromText('POINT(" . $data['current_longitude'] . " " . $data['current_latitude'] . ")')");
        }

        $vehicle = Vehicle::create($data);

        // Log the action
        AuditLogController::log(
            $user->id,
            'created',
            Vehicle::class,
            $vehicle->id,
            null,
            $vehicle->toArray(),
            'Vehicle created: ' . $vehicle->license_plate
        );

        return response()->json([
            'success' => true,
            'message' => 'Vehicle created successfully',
            'data' => $vehicle->load('admin')
        ], 201);
    }

    /**
     * Display the specified vehicle.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = auth()->user();
        $vehicle = Vehicle::with('admin:id,first_name,last_name,email')->find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }

        // Regular users and responders can only see available vehicles
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $vehicle->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle is not available'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $vehicle
        ]);
    }

    /**
     * Update the specified vehicle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }

        // Only admin, superadmin, or the assigned admin can update
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $vehicle->admin_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this vehicle'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'license_plate' => 'sometimes|required|string|max:20|unique:vehicles,license_plate,' . $id,
            'vehicle_type' => 'sometimes|required|in:ambulance,fire_truck,police_car,rescue_van,motorcycle,helicopter,other',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
            'status' => 'sometimes|in:available,in_use,maintenance,out_of_service',
            'current_location' => 'nullable|string|max:255',
            'current_latitude' => 'nullable|numeric|between:-90,90',
            'current_longitude' => 'nullable|numeric|between:-180,180',
            'fuel_level' => 'nullable|integer|min:0|max:100',
            'odometer_reading' => 'nullable|integer|min:0',
            'last_maintenance' => 'nullable|date',
            'next_maintenance' => 'nullable|date|after_or_equal:last_maintenance',
            'equipment_list' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $vehicle->toArray();
        $data = $request->all();

        // Update geometry from lat/long if provided
        if ($request->has('current_latitude') && $request->has('current_longitude')) {
            $data['vehicle_coordinates'] = DB::raw("ST_GeomFromText('POINT(" . $data['current_longitude'] . " " . $data['current_latitude'] . ")')");
        } elseif ($request->has('current_latitude') || $request->has('current_longitude')) {
            // If only one coordinate is provided, keep the other existing one
            $currentLat = $request->has('current_latitude') ? $data['current_latitude'] : $vehicle->current_latitude;
            $currentLng = $request->has('current_longitude') ? $data['current_longitude'] : $vehicle->current_longitude;
            
            if ($currentLat && $currentLng) {
                $data['vehicle_coordinates'] = DB::raw("ST_GeomFromText('POINT(" . $currentLng . " " . $currentLat . ")')");
            }
        }

        $vehicle->update($data);

        // Log the action
        AuditLogController::log(
            $user->id,
            'updated',
            Vehicle::class,
            $vehicle->id,
            $oldData,
            $vehicle->toArray(),
            'Vehicle updated: ' . $vehicle->license_plate
        );

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle->load('admin')
        ]);
    }

    /**
     * Remove the specified vehicle.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }

        // Only admin and superadmin can delete vehicles
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this vehicle'
            ], 403);
        }

        $oldData = $vehicle->toArray();
        $vehicle->delete();

        // Log the action
        AuditLogController::log(
            $user->id,
            'deleted',
            Vehicle::class,
            $id,
            $oldData,
            null,
            'Vehicle deleted: ' . $oldData['license_plate']
        );

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully'
        ]);
    }

    /**
     * Update vehicle location (for responders/admins).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateLocation(Request $request, $id)
    {
        $user = auth()->user();
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }

        // Check if user is authorized (admin, superadmin, or assigned responder)
        $isAuthorized = $user->isAdmin() || $user->isSuperAdmin();
        
        // If user is a responder, check if they're assigned to this vehicle
        if (!$isAuthorized && $user->isResponder()) {
            // You might want to check if responder is assigned to this vehicle
            // This depends on your application logic
            $isAuthorized = true; // For now, allow all responders
        }

        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update vehicle location'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_location' => 'nullable|string|max:255',
            'current_latitude' => 'required_with:current_longitude|numeric|between:-90,90',
            'current_longitude' => 'required_with:current_latitude|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $vehicle->toArray();
        $data = $request->all();

        // Update geometry from lat/long
        if ($request->has('current_latitude') && $request->has('current_longitude')) {
            $data['vehicle_coordinates'] = DB::raw("ST_GeomFromText('POINT(" . $data['current_longitude'] . " " . $data['current_latitude'] . ")')");
        }

        $vehicle->update($data);

        // Log the action
        AuditLogController::log(
            $user->id,
            'location_updated',
            Vehicle::class,
            $vehicle->id,
            [
                'old_latitude' => $oldData['current_latitude'],
                'old_longitude' => $oldData['current_longitude'],
                'old_location' => $oldData['current_location'],
            ],
            [
                'new_latitude' => $vehicle->current_latitude,
                'new_longitude' => $vehicle->current_longitude,
                'new_location' => $vehicle->current_location,
            ],
            'Vehicle location updated: ' . $vehicle->license_plate
        );

        return response()->json([
            'success' => true,
            'message' => 'Vehicle location updated successfully',
            'data' => [
                'license_plate' => $vehicle->license_plate,
                'current_location' => $vehicle->current_location,
                'current_latitude' => $vehicle->current_latitude,
                'current_longitude' => $vehicle->current_longitude,
                'updated_at' => $vehicle->updated_at,
            ]
        ]);
    }

    /**
     * Find nearby vehicles within a given radius.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function nearby(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:0.1|max:100', // in kilometers
            'vehicle_type' => 'nullable|in:ambulance,fire_truck,police_car,rescue_van,motorcycle,helicopter,other',
            'status' => 'nullable|in:available,in_use,maintenance,out_of_service',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius;
        $vehicleType = $request->vehicle_type;
        $status = $request->status ?? 'available'; // Default to available vehicles

        $query = Vehicle::where('status', $status)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereRaw("
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                ) <= ? * 1000
            ", [$longitude, $latitude, $radius]);

        if ($vehicleType) {
            $query->where('vehicle_type', $vehicleType);
        }

        // Calculate distance for each vehicle
        $vehicles = $query->selectRaw("
                *,
                ST_Distance_Sphere(
                    POINT(current_longitude, current_latitude),
                    POINT(?, ?)
                ) as distance_meters
            ", [$longitude, $latitude])
            ->orderBy('distance_meters', 'asc')
            ->get();

        // Format response
        $formattedVehicles = $vehicles->map(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'vehicle_type' => $vehicle->vehicle_type,
                'model' => $vehicle->model,
                'status' => $vehicle->status,
                'current_location' => $vehicle->current_location,
                'current_latitude' => $vehicle->current_latitude,
                'current_longitude' => $vehicle->current_longitude,
                'distance_meters' => round($vehicle->distance_meters),
                'distance_kilometers' => round($vehicle->distance_meters / 1000, 2),
                'fuel_level' => $vehicle->fuel_level,
                'available_equipment' => $vehicle->equipment_list ? json_decode($vehicle->equipment_list, true) : [],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'search_center' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
                'radius_km' => $radius,
                'vehicles_found' => count($formattedVehicles),
                'vehicles' => $formattedVehicles,
            ]
        ]);
    }

    /**
     * Update vehicle status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }

        // Only admin, superadmin, or assigned admin can update status
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $vehicle->admin_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update vehicle status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:available,in_use,maintenance,out_of_service',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $vehicle->status;
        $vehicle->update(['status' => $request->status]);

        // Log the action
        AuditLogController::log(
            $user->id,
            'status_updated',
            Vehicle::class,
            $vehicle->id,
            ['old_status' => $oldStatus],
            ['new_status' => $vehicle->status, 'reason' => $request->reason],
            'Vehicle status updated: ' . $vehicle->license_plate . ' (' . $oldStatus . ' → ' . $vehicle->status . ')'
        );

        return response()->json([
            'success' => true,
            'message' => 'Vehicle status updated successfully',
            'data' => [
                'license_plate' => $vehicle->license_plate,
                'old_status' => $oldStatus,
                'new_status' => $vehicle->status,
                'reason' => $request->reason,
            ]
        ]);
    }

    /**
     * Get vehicle statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function statistics()
    {
        $user = auth()->user();

        // Only admin and superadmin can view statistics
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view vehicle statistics'
            ], 403);
        }

        $totalVehicles = Vehicle::count();
        $byStatus = Vehicle::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $byType = Vehicle::selectRaw('vehicle_type, count(*) as count')
            ->groupBy('vehicle_type')
            ->get()
            ->pluck('count', 'vehicle_type')
            ->toArray();

        $needsMaintenance = Vehicle::where('next_maintenance', '<=', now()->addDays(7))
            ->where('next_maintenance', '>', now())
            ->count();

        $overdueMaintenance = Vehicle::where('next_maintenance', '<', now())
            ->count();

        $lowFuel = Vehicle::where('fuel_level', '<', 25)
            ->whereNotNull('fuel_level')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_vehicles' => $totalVehicles,
                'vehicles_by_status' => $byStatus,
                'vehicles_by_type' => $byType,
                'needs_maintenance_soon' => $needsMaintenance,
                'overdue_for_maintenance' => $overdueMaintenance,
                'low_fuel' => $lowFuel,
                'last_updated' => now()->toIso8601String(),
            ]
        ]);
    }
}