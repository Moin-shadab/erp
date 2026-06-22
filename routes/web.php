<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DynamicCrudController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ChatController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect('/');
    }
    return view('login');
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    // Check if user exists and is inactive
    $user = DB::table('users')->where('email', $credentials['email'])->first();
    if ($user && !$user->is_active) {
        return back()->withErrors([
            'email' => 'Your account has been deactivated. Please contact your system administrator.',
        ])->onlyInput('email');
    }

    // Force is_active to be 1 (true) for attempt
    $credentials['is_active'] = 1;

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        
        $accountId = DB::table('email_account_users')->where('user_id', Auth::id())->value('email_account_id');
        if ($accountId) {
            session(['active_email_account_id' => $accountId]);
        }

        return redirect()->intended('/');
    }

    return back()->withErrors([
        'email' => 'The provided credentials do not match our records.',
    ])->onlyInput('email');
});

Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| Authenticated ERP System Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    // Main workspace
    Route::get('/', [DashboardController::class, 'index']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // User context endpoints
    Route::get('/api/user/context', [DashboardController::class, 'getUserContext']);
    Route::post('/api/user/switch-context', [DashboardController::class, 'switchContext']);

    // Dynamic metadata-driven CRUD core routes
    Route::get('/erp/{slug}', [DynamicCrudController::class, 'index']);
    Route::get('/erp/api/{slug}/data', [DynamicCrudController::class, 'getData']);
    Route::post('/erp/api/{slug}/store', [DynamicCrudController::class, 'store']);
    Route::post('/erp/api/{slug}/update/{id}', [DynamicCrudController::class, 'update']);
    Route::delete('/erp/api/{slug}/destroy/{id}', [DynamicCrudController::class, 'destroy']);
    Route::get('/erp/api/{slug}/export', [DynamicCrudController::class, 'export']);
    Route::post('/erp/api/{slug}/import', [DynamicCrudController::class, 'import']);

    // Global search route
    Route::get('/erp/search', function (Request $request) {
        $q = $request->query('q');
        // Search across customer and invoice tables
        $customers = DB::table('customers')
            ->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->limit(5)->get();

        $invoices = DB::table('sales_invoices')
            ->where('invoice_no', 'like', "%{$q}%")
            ->limit(5)->get();

        $inventory = DB::table('inventory_items')
            ->where('name', 'like', "%{$q}%")
            ->orWhere('item_code', 'like', "%{$q}%")
            ->limit(5)->get();

        return view('search_results', compact('q', 'customers', 'invoices', 'inventory'));
    });

    // Workflows action endpoints
    Route::post('/api/workflow/approve/{instance_id}', [DynamicCrudController::class, 'approveWorkflow']);
    Route::post('/api/workflow/reject/{instance_id}', [DynamicCrudController::class, 'rejectWorkflow']);

    // Notifications AJAX endpoints
    Route::get('/api/notifications', function () {
        $user = Auth::user();
        $items = DB::table('notifications')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->time_ago = \Carbon\Carbon::parse($item->created_at)->diffForHumans(null, true) . ' ago';
                return $item;
            });
        $unreadCount = DB::table('notifications')
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        // Get active unread broadcast
        $pendingBroadcast = \App\Services\NotificationService::getPendingBroadcast($user);

        return response()->json([
            'items' => $items, 
            'unread_count' => $unreadCount,
            'pending_broadcast' => $pendingBroadcast
        ]);
    });

    Route::post('/api/notifications/read/{id}', function ($id) {
        DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->update(['is_read' => true]);
        return response()->json(['success' => true]);
    });

    Route::post('/api/notifications/read-all', function () {
        DB::table('notifications')
            ->where('user_id', Auth::id())
            ->update(['is_read' => true]);
        return response()->json(['success' => true]);
    });

    Route::post('/api/notifications/test-send', function (Request $request) {
        $request->validate([
            'sender_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);
        
        $receivers = DB::table('notification_routes')
            ->join('users', 'notification_routes.receiver_id', '=', 'users.id')
            ->where('notification_routes.sender_id', $request->sender_id)
            ->where('notification_routes.is_active', true)
            ->select('users.id', 'users.name')
            ->get();
            
        foreach ($receivers as $receiver) {
            \App\Services\NotificationService::sendDirect(
                $receiver->id,
                $request->title,
                $request->message,
                'SYSTEM'
            );
        }
        
        return response()->json([
            'success' => true,
            'receivers' => $receivers->pluck('name')->toArray()
        ]);
    });

    Route::post('/api/notifications/broadcast/acknowledge/{id}', function ($id) {
        DB::table('broadcast_read_receipts')->insert([
            'broadcast_id' => $id,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
        return response()->json(['success' => true]);
    });

    Route::post('/api/profile/change-password', function (Request $request) {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:4|confirmed',
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password matches incorrectly.'], 422);
        }

        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make($request->new_password),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Password credentials updated successfully.']);
    });

    // Admin-only Notification Routing Configuration APIs
    Route::middleware(['admin'])->group(function () {
        Route::get('/api/notification-routes/{userId}', function ($userId) {
            $outgoing = DB::table('notification_routes')
                ->where('sender_id', $userId)
                ->pluck('receiver_id');
            $incoming = DB::table('notification_routes')
                ->where('receiver_id', $userId)
                ->pluck('sender_id');
            return response()->json([
                'outgoing' => $outgoing,
                'incoming' => $incoming
            ]);
        });
        Route::post('/api/notification-routes/update', function (Request $request) {
            $request->validate([
                'user_id' => 'required|integer',
                'target_user_id' => 'required|integer',
                'direction' => 'required|string', // incoming, outgoing
                'value' => 'required|boolean'
            ]);
            $userId = $request->user_id;
            $targetId = $request->target_user_id;
            $direction = $request->direction;
            $val = $request->value;
            $senderId = ($direction === 'outgoing') ? $userId : $targetId;
            $receiverId = ($direction === 'outgoing') ? $targetId : $userId;
            if ($val) {
                DB::table('notification_routes')->updateOrInsert(
                    ['sender_id' => $senderId, 'receiver_id' => $receiverId],
                    ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            } else {
                DB::table('notification_routes')
                    ->where('sender_id', $senderId)
                    ->where('receiver_id', $receiverId)
                    ->delete();
            }
            return response()->json(['success' => true]);
        });
        Route::post('/api/notification-routes/copy', function (Request $request) {
            $request->validate([
                'source_user_id' => 'required|integer',
                'target_user_id' => 'required|integer',
            ]);
            $sourceId = $request->source_user_id;
            $targetId = $request->target_user_id;
            if ($sourceId === $targetId) {
                return response()->json(['error' => 'Source and target users must be different.'], 400);
            }
            // Copy outgoing rules (where sender is source)
            $outgoingRules = DB::table('notification_routes')
                ->where('sender_id', $sourceId)
                ->get();

            foreach ($outgoingRules as $rule) {
                if ($rule->receiver_id == $targetId) continue;
                DB::table('notification_routes')->updateOrInsert(
                    ['sender_id' => $targetId, 'receiver_id' => $rule->receiver_id],
                    ['is_active' => $rule->is_active, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            // Copy incoming rules (where receiver is source)
            $incomingRules = DB::table('notification_routes')
                ->where('receiver_id', $sourceId)
                ->get();

            foreach ($incomingRules as $rule) {
                if ($rule->sender_id == $targetId) continue;
                DB::table('notification_routes')->updateOrInsert(
                    ['sender_id' => $rule->sender_id, 'receiver_id' => $targetId],
                    ['is_active' => $rule->is_active, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            return response()->json(['success' => true]);
        });
    });

    // Email client module routes
    Route::get('/email/inbox', [EmailController::class, 'inbox']);
    Route::get('/email/contacts', [EmailController::class, 'contacts']);
    Route::get('/email/compose', [EmailController::class, 'compose']);
    
    Route::get('/api/email/list', [EmailController::class, 'getEmailList']);
    Route::get('/api/email/thread/{thread_id}', [EmailController::class, 'getThread']);
    Route::post('/api/email/send', [EmailController::class, 'send']);
    Route::post('/api/email/save-draft', [EmailController::class, 'saveDraft']);
    Route::post('/api/email/toggle-star/{id}', [EmailController::class, 'toggleStar']);
    Route::post('/api/email/move-folder/{id}', [EmailController::class, 'moveFolder']);
    Route::post('/api/email/sync/{id}', [EmailController::class, 'sync']);
    Route::post('/api/email/switch-account', [EmailController::class, 'switchAccount']);
    Route::post('/api/email/contacts/store', [EmailController::class, 'storeContact']);
    Route::post('/api/email/contacts/delete/{id}', [EmailController::class, 'deleteContact']);
    Route::get('/api/email/folder-counts', [EmailController::class, 'getFolderCounts']);
    Route::post('/api/email/bulk-action', [EmailController::class, 'bulkAction']);
    Route::get('/api/email/labels', [EmailController::class, 'getLabels']);
    Route::post('/api/email/labels', [EmailController::class, 'storeLabel']);
    Route::post('/api/email/apply-label', [EmailController::class, 'applyLabel']);
    Route::delete('/api/email/label/{id}', [EmailController::class, 'deleteLabel']);
    Route::get('/api/email/attachment/{id}', [EmailController::class, 'getAttachment']);
    Route::get('/api/email/settings', [EmailController::class, 'getSettings']);
    Route::post('/api/email/settings', [EmailController::class, 'updateSettings']);

    // Email Accounts Administrative endpoints
    Route::get('/api/email-accounts', [EmailController::class, 'getEmailAccounts']);
    Route::post('/api/email-accounts/store', [EmailController::class, 'storeEmailAccount']);
    Route::delete('/api/email-accounts/delete/{id}', [EmailController::class, 'deleteEmailAccount']);

    // Dynamic Report Builder routes
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/api/reports/columns/{table}', [ReportController::class, 'getColumns']);
    Route::post('/api/reports/generate', [ReportController::class, 'generate']);
    Route::post('/api/reports/save', [ReportController::class, 'save']);
    Route::post('/api/reports/delete/{id}', [ReportController::class, 'destroy']);

    // Internal Chat system routes
    Route::get('/api/chat/context', [ChatController::class, 'getChannelsAndContacts']);
    Route::get('/api/chat/users/search', [ChatController::class, 'searchUsers']);
    Route::get('/api/chat/messages', [ChatController::class, 'getMessages']);
    Route::post('/api/chat/send', [ChatController::class, 'sendMessage']);
    Route::delete('/api/chat/delete/{id}', [ChatController::class, 'deleteMessage']);
    Route::post('/api/chat/forward', [ChatController::class, 'forwardMessage']);
    Route::get('/api/chat/thread/{id}', [ChatController::class, 'getThreadReplies']);

    // Admin-only Staff and Permission Matrix Management APIs
    Route::middleware(['admin'])->group(function () {
        // Dynamic Permission matrix update route
        Route::post('/api/permissions/update', function (Request $request) {
            $roleId = $request->input('role_id');
            $pageId = $request->input('page_id');
            $action = $request->input('action'); // can_view, can_create, can_edit, etc.
            $val = (bool) $request->input('value');

            // Check if permission mapping already exists
            $exists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('page_id', $pageId)
                ->exists();

            if ($exists) {
                DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('page_id', $pageId)
                    ->update([
                        $action => $val,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'page_id' => $pageId,
                    $action => $val,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json(['success' => true]);
        });

        // Page Page-Token Update Route
        Route::post('/api/pages/update-token', [DynamicCrudController::class, 'updatePageToken']);

        // Custom User Management API Routes
        Route::post('/api/users/store', function (Request $request) {
            $data = $request->validate([
                'name' => 'required|string|max:255|unique:users,name',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:4',
                'role_id' => 'required|integer',
                'reports_to_id' => 'nullable|integer',
                'is_active' => 'required|boolean',
                'can_send_to_anyone' => 'nullable|boolean',
            ]);

            $data['can_send_to_anyone'] = $request->boolean('can_send_to_anyone') ? 1 : 0;
            $admin = Auth::user();
            $data['password'] = Hash::make($data['password']);
            $data['company_id'] = $admin->company_id;
            $data['branch_id'] = $admin->branch_id;
            $data['department_id'] = $admin->department_id;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $userId = DB::table('users')->insertGetId($data);

            // Optionally copy permissions from another user if source_user_id is provided
            $sourceUserId = $request->input('source_user_id');
            if ($sourceUserId) {
                $sourcePerms = DB::table('user_permissions')->where('user_id', $sourceUserId)->get();
                if ($sourcePerms->isNotEmpty()) {
                    foreach ($sourcePerms as $sp) {
                        DB::table('user_permissions')->insert([
                            'user_id' => $userId,
                            'page_id' => $sp->page_id,
                            'can_view' => $sp->can_view,
                            'can_create' => $sp->can_create,
                            'can_edit' => $sp->can_edit,
                            'can_delete' => $sp->can_delete,
                            'can_export' => $sp->can_export,
                            'can_print' => $sp->can_print,
                            'can_approve' => $sp->can_approve,
                            'can_reject' => $sp->can_reject,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    // Fall back to role permissions
                    $rolePerms = DB::table('role_permissions')->where('role_id', $data['role_id'])->get();
                    foreach ($rolePerms as $rp) {
                        DB::table('user_permissions')->insert([
                            'user_id' => $userId,
                            'page_id' => $rp->page_id,
                            'can_view' => $rp->can_view,
                            'can_create' => $rp->can_create,
                            'can_edit' => $rp->can_edit,
                            'can_delete' => $rp->can_delete,
                            'can_export' => $rp->can_export,
                            'can_print' => $rp->can_print,
                            'can_approve' => $rp->can_approve,
                            'can_reject' => $rp->can_reject,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else {
                // Populate default user permissions from role permissions
                $rolePerms = DB::table('role_permissions')->where('role_id', $data['role_id'])->get();
                foreach ($rolePerms as $rp) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $userId,
                        'page_id' => $rp->page_id,
                        'can_view' => $rp->can_view,
                        'can_create' => $rp->can_create,
                        'can_edit' => $rp->can_edit,
                        'can_delete' => $rp->can_delete,
                        'can_export' => $rp->can_export,
                        'can_print' => $rp->can_print,
                        'can_approve' => $rp->can_approve,
                        'can_reject' => $rp->can_reject,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json(['success' => true, 'user_id' => $userId]);
        });

        Route::post('/api/users/update/{id}', function (Request $request, $id) {
            $data = $request->validate([
                'name' => 'required|string|max:255|unique:users,name,' . $id,
                'email' => 'required|email|unique:users,email,' . $id,
                'role_id' => 'required|integer',
                'reports_to_id' => 'nullable|integer',
                'is_active' => 'required|boolean',
                'can_send_to_anyone' => 'nullable|boolean',
            ]);

            $data['can_send_to_anyone'] = $request->boolean('can_send_to_anyone') ? 1 : 0;
            $data['updated_at'] = now();

            DB::table('users')->where('id', $id)->update($data);

            return response()->json(['success' => true]);
        });

        Route::post('/api/users/change-password/{id}', function (Request $request, $id) {
            $data = $request->validate([
                'password' => 'required|string|min:4',
            ]);

            DB::table('users')->where('id', $id)->update([
                'password' => Hash::make($data['password']),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true]);
        });

        Route::get('/api/users/{id}/permissions', function ($id) {
            $user = DB::table('users')->where('id', $id)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $userPerms = DB::table('user_permissions')->where('user_id', $id)->get()->keyBy('page_id');

            if ($userPerms->isEmpty()) {
                // Populate defaults from role
                $rolePerms = DB::table('role_permissions')->where('role_id', $user->role_id)->get();
                foreach ($rolePerms as $rp) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $id,
                        'page_id' => $rp->page_id,
                        'can_view' => $rp->can_view,
                        'can_create' => $rp->can_create,
                        'can_edit' => $rp->can_edit,
                        'can_delete' => $rp->can_delete,
                        'can_export' => $rp->can_export,
                        'can_print' => $rp->can_print,
                        'can_approve' => $rp->can_approve,
                        'can_reject' => $rp->can_reject,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $userPerms = DB::table('user_permissions')->where('user_id', $id)->get()->keyBy('page_id');
            }

            return response()->json(['permissions' => $userPerms]);
        });

        Route::post('/api/users/permissions/update', function (Request $request) {
            $userId = $request->input('user_id');
            $pageId = $request->input('page_id');
            $action = $request->input('action'); // can_view, can_create, can_edit, etc.
            $val = (bool) $request->input('value');

            // Check if permission mapping already exists
            $exists = DB::table('user_permissions')
                ->where('user_id', $userId)
                ->where('page_id', $pageId)
                ->exists();

            if ($exists) {
                DB::table('user_permissions')
                    ->where('user_id', $userId)
                    ->where('page_id', $pageId)
                    ->update([
                        $action => $val,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('user_permissions')->insert([
                    'user_id' => $userId,
                    'page_id' => $pageId,
                    $action => $val,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json(['success' => true]);
        });

        Route::post('/api/users/permissions/clone', function (Request $request) {
            $targetUserId = $request->input('target_user_id');
            $sourceUserId = $request->input('source_user_id');

            if (!$targetUserId || !$sourceUserId) {
                return response()->json(['error' => 'Invalid source or target user ID'], 400);
            }

            // Delete existing user permissions for target
            DB::table('user_permissions')->where('user_id', $targetUserId)->delete();

            // Copy from source
            $sourcePerms = DB::table('user_permissions')->where('user_id', $sourceUserId)->get();
            foreach ($sourcePerms as $sp) {
                DB::table('user_permissions')->insert([
                    'user_id' => $targetUserId,
                    'page_id' => $sp->page_id,
                    'can_view' => $sp->can_view,
                    'can_create' => $sp->can_create,
                    'can_edit' => $sp->can_edit,
                    'can_delete' => $sp->can_delete,
                    'can_export' => $sp->can_export,
                    'can_print' => $sp->can_print,
                    'can_approve' => $sp->can_approve,
                    'can_reject' => $sp->can_reject,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json(['success' => true]);
        });

        Route::get('/api/my-sales-reps', function () {
            $user = Auth::user();
            if (!$user) return response()->json([], 401);
            
            $role = DB::table('roles')->where('id', $user->role_id)->first();
            $isSuperOrAdmin = $role && in_array($role->slug, ['super-admin', 'admin']);
            
            $query = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.id')
                ->where('roles.slug', 'sales-rep')
                ->where('users.is_active', true);
                
            if (!$isSuperOrAdmin) {
                // For a Sales Head, only list Sales Reps reporting directly or indirectly to them
                $subordinateIds = app(App\Repositories\DynamicCrudRepository::class)->getSubordinateUserIds($user->id);
                $query->whereIn('users.id', $subordinateIds);
            }
            
            $reps = $query->select('users.id', 'users.name', 'users.email')->get();
            return response()->json($reps);
        });

        Route::get('/api/customers/{id}/shares', function ($id) {
            $shares = DB::table('customer_shares')
                ->where('customer_id', $id)
                ->pluck('user_id');
            $customer = DB::table('customers')->where('id', $id)->first();
            return response()->json([
                'shared_user_ids' => $shares,
                'share_with_everyone' => (bool)($customer->share_with_everyone ?? false)
            ]);
        });

        // Chat admin config routes
        Route::get('/api/chat/admin/directory', [ChatController::class, 'getAdminDirectory']);
        Route::post('/api/chat/admin/groups', [ChatController::class, 'createChannel']);
        Route::post('/api/chat/admin/rules', [ChatController::class, 'addChatRule']);
        Route::delete('/api/chat/admin/rules/{id}', [ChatController::class, 'removeChatRule']);
        Route::post('/api/chat/admin/delete-permission', [ChatController::class, 'toggleDeletePermission']);
    });
});
