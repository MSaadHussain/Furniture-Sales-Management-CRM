<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    /** Activity logs screen (actions performed). Super Admin only. */
    public function logs(Request $request)
    {
        Gate::authorize('view-activity');

        $query = UserActivityLog::query()->with('user')->latest('created_at');

        if ($uid = $request->integer('user_id')) {
            $query->where('user_id', $uid);
        }
        if ($action = $request->string('action')->value()) {
            $query->where('action', $action);
        }
        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return view('activity.logs', [
            'logs'    => $query->paginate(30)->withQueryString(),
            'users'   => User::orderBy('name')->get(['id', 'name']),
            'actions' => UserActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->only(['user_id', 'action', 'from', 'to']),
        ]);
    }

    /** Time-spent / session report. Super Admin only. */
    public function sessions(Request $request)
    {
        Gate::authorize('view-activity');

        $query = UserSession::query()->with('user')->latest('login_at');

        if ($uid = $request->integer('user_id')) {
            $query->where('user_id', $uid);
        }
        if ($from = $request->date('from')) {
            $query->where('login_at', '>=', $from->startOfDay());
        }
        if ($to = $request->date('to')) {
            $query->where('login_at', '<=', $to->endOfDay());
        }

        return view('activity.sessions', [
            'sessions' => $query->paginate(30)->withQueryString(),
            'users'    => User::orderBy('name')->get(['id', 'name']),
            'filters'  => $request->only(['user_id', 'from', 'to']),
        ]);
    }
}
