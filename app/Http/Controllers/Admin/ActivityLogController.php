<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            auth('staff')->user()?->can('staff.view'),
            403,
            'You do not have permission [staff.view].'
        );

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));

        $query = ActivityLog::query();

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('action', 'like', $like);
            });
        }

        return response()->json($query->latest()->paginate($perPage));
    }
}
