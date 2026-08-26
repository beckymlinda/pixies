<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Only managers and directors can view activity logs
        if (!$user->isManager() && !$user->isDirector()) {
            abort(403);
        }

        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        // Filter by action if provided
        if ($request->has('action') && $request->action !== '') {
            $query->where('action', $request->action);
        }

        // Filter by date range if provided
        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $activityLogs = $query->paginate(50);

        // Get available actions for filter dropdown
        $availableActions = ActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();

        return view('activity-logs.index', compact('activityLogs', 'availableActions'));
    }
}
