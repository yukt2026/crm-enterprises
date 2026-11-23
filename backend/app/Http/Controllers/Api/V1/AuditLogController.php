<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AuditSecurity\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List immutable audit logs for current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email,role')
            ->orderBy('created_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Audit logs retrieved.',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Show single audit log entry with diff
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with('user:id,name,email,role')->findOrFail($id);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Audit log details retrieved.',
            'data' => $log,
        ]);
    }
}
