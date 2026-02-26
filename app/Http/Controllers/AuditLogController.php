<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Display audit logs with optional filters.
     * Restricted to super_admin only (enforced by route middleware).
     */
    public function index(Request $request)
    {
        try {
            $filters = [];

            if ($request->filled('action')) {
                $filters['action'] = $request->input('action');
            }

            if ($request->filled('user')) {
                $filters['user_id'] = $request->input('user');
            }

            if ($request->filled('date_from')) {
                $filters['date_from'] = $request->input('date_from');
            }

            if ($request->filled('date_to')) {
                $filters['date_to'] = $request->input('date_to');
            }

            $logs = $this->firebase->getAuditLogs($filters);

            return view('audit.index', [
                'logs' => $logs,
                'filters' => [
                    'action' => $request->input('action', ''),
                    'user' => $request->input('user', ''),
                    'date_from' => $request->input('date_from', ''),
                    'date_to' => $request->input('date_to', ''),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit log index error: ' . $e->getMessage());
            return view('audit.index', [
                'logs' => [],
                'filters' => [
                    'action' => '',
                    'user' => '',
                    'date_from' => '',
                    'date_to' => '',
                ],
            ])->with('error', __('messages.unknown_error'));
        }
    }
}
