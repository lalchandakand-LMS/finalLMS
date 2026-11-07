<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('method')) {
            $query->where('method', strtoupper($request->string('method')->toString()));
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}
