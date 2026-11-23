<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AuditSecurity\Models\ActivityLog;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query();

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                  ->orWhere('module', 'like', $search)
                  ->orWhere('details', 'like', $search)
                  ->orWhere('user_name', 'like', $search);
            });
        }

        $perPage = (int) $request->get('limit', $request->get('per_page', 10));
        return $this->paginatedResponse($query->latest()->paginate($perPage));
    }
}
