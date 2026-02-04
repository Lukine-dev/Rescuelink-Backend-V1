<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin,superadmin');
    }

    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        // Filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('date_from')) {
            $query->whereDate('performed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('performed_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('performed_at', 'desc')->paginate($request->per_page ?? 20);

        return response()->json($logs);
    }

    public static function log($userId, $action, $modelType, $modelId = null, $oldValues = null, $newValues = null, $description = null)
    {
        $request = request();

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'performed_at' => now()
        ]);
    }
}