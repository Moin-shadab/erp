<?php

namespace App\Http\Controllers;

use App\Services\DynamicCrudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Services\NotificationService;

class DynamicCrudController extends Controller
{
    protected $crudService;

    public function __construct(DynamicCrudService $crudService)
    {
        $this->crudService = $crudService;
    }

    /**
     * View the dynamic CRUD page shell.
     */
    public function index($slug)
    {
        if (!request()->ajax()) {
            return redirect('/');
        }

        $pageConfig = $this->crudService->getPageConfig($slug);
        
        if (!$pageConfig) {
            abort(404, 'Page not found or inactive.');
        }

        // Access Control
        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_view']) {
            abort(403, 'Unauthorized access.');
        }

        // Resolve options source (foreign keys dropdowns) dynamically
        $formSchema = json_decode($pageConfig->form_schema, true) ?? [];
        foreach ($formSchema as &$field) {
            if (isset($field['options_source']) && $field['options_source'] === 'table') {
                try {
                    $field['options'] = DB::table($field['options_table'])
                        ->select($field['options_key'] . ' as value', $field['options_value'] . ' as label')
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    $field['options'] = [];
                }
            }
        }
        $pageConfig->form_schema = json_encode($formSchema);

        if ($pageConfig->is_custom && !empty($pageConfig->custom_view)) {
            if (str_contains($pageConfig->custom_view, 'permissions_matrix')) {
                $roles = DB::table('roles')->get();
                $activeRoleId = request()->query('role_id', $roles->first()->id);
                $pages = DB::table('pages')->where('is_active', true)->where('slug', '<>', 'permissions-matrix')->get(); // exclude self from matrix to prevent lockout
                $modules = DB::table('modules')->get();
                
                $rolePermissions = DB::table('role_permissions')
                    ->where('role_id', $activeRoleId)
                    ->get()
                    ->keyBy('page_id');

                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'roles', 'pages', 'modules', 'activeRoleId', 'rolePermissions'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }
            
            if (str_contains($pageConfig->custom_view, 'user_management')) {
                $users = DB::table('users')
                    ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                    ->leftJoin('users as mgr', 'users.reports_to_id', '=', 'mgr.id')
                    ->select('users.*', 'roles.name as role_name', 'roles.slug as role_slug', 'mgr.name as manager_name')
                    ->get();
                $roles = DB::table('roles')->get();
                $modules = DB::table('modules')->orderBy('sequence')->get();
                $pages = DB::table('pages')->where('is_active', true)->get();
                $allUsers = DB::table('users')->select('id', 'name')->get();
                
                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'users', 'roles', 'modules', 'pages', 'allUsers'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            if (str_contains($pageConfig->custom_view, 'email/accounts')) {
                $accounts = DB::table('email_accounts')->get();
                foreach ($accounts as $acc) {
                    $acc->user_ids = DB::table('email_account_users')
                        ->where('email_account_id', $acc->id)
                        ->pluck('user_id')
                        ->toArray();
                }
                $users = DB::table('users')->where('is_active', true)->select('id', 'name', 'email')->get();
                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'accounts', 'users'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            if (str_contains($pageConfig->custom_view, 'email/inbox')) {
                $user = Auth::user();
                $accountId = session('active_email_account_id');
                if (!$accountId) {
                    $accountId = DB::table('email_account_users')->where('user_id', $user->id)->value('email_account_id');
                    if ($accountId) {
                        session(['active_email_account_id' => $accountId]);
                    }
                }
                $hasAccess = $accountId ? DB::table('email_account_users')->where('email_account_id', $accountId)->where('user_id', $user->id)->exists() : false;
                $account = $hasAccess ? DB::table('email_accounts')->where('id', $accountId)->first() : null;
                
                $counts = [
                    'INBOX' => 0, 'SENT' => 0, 'DRAFTS' => 0, 'TRASH' => 0, 'SPAM' => 0, 'ARCHIVE' => 0
                ];
                if ($account) {
                    $rawCounts = DB::table('emails')
                        ->where('email_account_id', $account->id)
                        ->select('folder', DB::raw('count(*) as cnt'))
                        ->groupBy('folder')
                        ->get();
                    foreach ($rawCounts as $rc) {
                        if (array_key_exists($rc->folder, $counts)) {
                            $counts[$rc->folder] = $rc->cnt;
                        }
                    }
                }

                $labels = DB::table('email_labels')
                    ->where('user_id', Auth::id())
                    ->orderBy('name')
                    ->get();

                $allAcc = DB::table('email_accounts')
                    ->join('email_account_users', 'email_accounts.id', '=', 'email_account_users.email_account_id')
                    ->where('email_account_users.user_id', Auth::id())
                    ->where('email_accounts.is_active', true)
                    ->select('email_accounts.*')
                    ->get();

                $lastSync = null;
                if ($account) {
                    $lastEmail = DB::table('emails')
                        ->where('email_account_id', $account->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    if ($lastEmail) {
                        $lastSync = (object)[
                            'status' => 'completed',
                            'finished_at' => $lastEmail->created_at
                        ];
                    }
                }

                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'account', 'counts', 'labels', 'allAcc', 'lastSync'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            if (str_contains($pageConfig->custom_view, 'email/contacts')) {
                $contacts = DB::table('email_contacts')
                    ->where('user_id', Auth::id())
                    ->orderBy('name')
                    ->get();
                $groups = DB::table('email_contact_groups')
                    ->where('user_id', Auth::id())
                    ->orderBy('name')
                    ->get();
                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'contacts', 'groups'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            if (str_contains($pageConfig->custom_view, 'email/compose')) {
                $user = Auth::user();
                $accountId = session('active_email_account_id');
                if (!$accountId) {
                    $accountId = DB::table('email_account_users')->where('user_id', $user->id)->value('email_account_id');
                    if ($accountId) {
                        session(['active_email_account_id' => $accountId]);
                    }
                }
                $hasAccess = $accountId ? DB::table('email_account_users')->where('email_account_id', $accountId)->where('user_id', $user->id)->exists() : false;
                $account = $hasAccess ? DB::table('email_accounts')->where('id', $accountId)->first() : null;
                $templates = DB::table('email_templates')->where('user_id', Auth::id())->get();
                $signature = DB::table('email_signatures')->where('user_id', Auth::id())->where('is_default', true)->first();

                $replyToId = request()->input('reply_to');
                $replyMail = null;
                if ($replyToId) {
                    $replyMail = DB::table('emails')
                        ->leftJoin('email_bodies', 'emails.id', '=', 'email_bodies.email_id')
                        ->where('emails.id', $replyToId)
                        ->select('emails.*', 'email_bodies.body_html', 'email_bodies.body_text')
                        ->first();
                }

                // Fetch contacts for autocompletion
                $role = DB::table('roles')->where('id', $user->role_id)->first();
                $isSuperAdmin = $role && $role->slug === 'super-admin';
                $canSendToAnyone = $isSuperAdmin || (bool)($user->can_send_to_anyone ?? false);

                $contacts = DB::table('email_contacts')
                    ->where('user_id', $user->id)
                    ->orderBy('name')
                    ->get(['name', 'email']);

                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'account', 'templates', 'signature', 'replyMail', 'contacts', 'canSendToAnyone'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            if (str_contains($pageConfig->custom_view, 'reports')) {
                $tables = [
                    'customers' => 'Customers List',
                    'sales_invoices' => 'Sales Invoices',
                    'inventory_items' => 'Inventory Assets',
                    'audit_logs' => 'System Audit Logs',
                    'users' => 'Staff Directory',
                    'emails' => 'Email Logbook'
                ];
                $savedReports = DB::table('reports')
                    ->where('created_by', Auth::id())
                    ->orderBy('name')
                    ->get();
                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'tables', 'savedReports'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            if (str_contains($pageConfig->custom_view, 'notification_routing')) {
                $users = DB::table('users')
                    ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                    ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                    ->select('users.*', 'roles.name as role_name', 'departments.name as department_name')
                    ->where('users.is_active', true)
                    ->get();
                $allUsers = DB::table('users')->where('is_active', true)->get();
                return view('modules.loader', array_merge(
                    compact('pageConfig', 'permissions', 'users', 'allUsers'),
                    ['pageDir' => $pageConfig->custom_view]
                ));
            }

            return view('modules.loader', array_merge(
                compact('pageConfig', 'permissions'),
                ['pageDir' => $pageConfig->custom_view]
            ));
        }

        return view('modules.loader', array_merge(
            compact('pageConfig', 'permissions'),
            ['pageDir' => 'modules/dynamic_crud']
        ));
    }

    /**
     * Get grid data as JSON.
     */
    public function getData(Request $request, $slug)
    {
        $pageConfig = $this->crudService->getPageConfig($slug);
        if (!$pageConfig) {
            return response()->json(['error' => 'Page configuration not found.'], 404);
        }

        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_view']) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        try {
            $gridData = $this->crudService->getGridData($pageConfig, $request->all());
            return response()->json($gridData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new record.
     */
    public function store(Request $request, $slug)
    {
        $pageConfig = $this->crudService->getPageConfig($slug);
        if (!$pageConfig) {
            return response()->json(['error' => 'Page configuration not found.'], 404);
        }

        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_create']) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        try {
            $sharedUserIds = $request->input('shared_user_ids', []);
            $shareWithEveryone = $request->boolean('share_with_everyone') ? 1 : 0;

            $recordId = $this->crudService->createRecord($pageConfig, $request->all(), $request->ip(), $request->userAgent());
            
            if ($slug === 'customers') {
                DB::table('customers')->where('id', $recordId)->update([
                    'share_with_everyone' => $shareWithEveryone
                ]);
                
                DB::table('customer_shares')->where('customer_id', $recordId)->delete();
                if (!$shareWithEveryone && !empty($sharedUserIds)) {
                    $shares = [];
                    foreach ($sharedUserIds as $uId) {
                        $shares[] = [
                            'customer_id' => $recordId,
                            'user_id' => (int)$uId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                    DB::table('customer_shares')->insert($shares);
                }
            }
            
            // Route notifications to supervisors/receivers
            try {
                NotificationService::send(
                    Auth::id(),
                    "New record created in " . ($pageConfig->title ?? $slug),
                    "User " . Auth::user()->name . " created a new record in " . ($pageConfig->title ?? $slug) . " (ID: {$recordId}).",
                    'SYSTEM'
                );
            } catch (\Exception $ne) {
                // Silently ignore notification errors to avoid failing the main action
            }

            return response()->json([
                'success' => true,
                'message' => 'Record created successfully.',
                'id' => $recordId
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing record.
     */
    public function update(Request $request, $slug, $id)
    {
        $pageConfig = $this->crudService->getPageConfig($slug);
        if (!$pageConfig) {
            return response()->json(['error' => 'Page configuration not found.'], 404);
        }

        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_edit']) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        try {
            $sharedUserIds = $request->input('shared_user_ids', []);
            $shareWithEveryone = $request->boolean('share_with_everyone') ? 1 : 0;

            $result = $this->crudService->updateRecord($pageConfig, $id, $request->all(), $request->ip(), $request->userAgent());
            
            if ($result && $slug === 'customers') {
                DB::table('customers')->where('id', $id)->update([
                    'share_with_everyone' => $shareWithEveryone
                ]);
                
                DB::table('customer_shares')->where('customer_id', $id)->delete();
                if (!$shareWithEveryone && !empty($sharedUserIds)) {
                    $shares = [];
                    foreach ($sharedUserIds as $uId) {
                        $shares[] = [
                            'customer_id' => $id,
                            'user_id' => (int)$uId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                    DB::table('customer_shares')->insert($shares);
                }
            }
            
            if ($result) {
                // Route notifications to supervisors/receivers
                try {
                    NotificationService::send(
                        Auth::id(),
                        "Record updated in " . ($pageConfig->title ?? $slug),
                        "User " . Auth::user()->name . " updated a record in " . ($pageConfig->title ?? $slug) . " (ID: {$id}).",
                        'SYSTEM'
                    );
                } catch (\Exception $ne) {
                    // Silently ignore notification errors to avoid failing the main action
                }
            }

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Record updated successfully.' : 'Failed to update record.'
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a record.
     */
    public function destroy(Request $request, $slug, $id)
    {
        $pageConfig = $this->crudService->getPageConfig($slug);
        if (!$pageConfig) {
            return response()->json(['error' => 'Page configuration not found.'], 404);
        }

        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_delete']) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        try {
            $result = $this->crudService->deleteRecord($pageConfig, $id, $request->ip(), $request->userAgent());
            return response()->json([
                'success' => $result,
                'message' => $result ? 'Record deleted successfully.' : 'Failed to delete record.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export grid data as CSV.
     */
    public function export(Request $request, $slug)
    {
        $pageConfig = $this->crudService->getPageConfig($slug);
        if (!$pageConfig) {
            abort(404);
        }

        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_export']) {
            abort(403);
        }

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$slug}_export_" . date('Y-m-d') . ".csv",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = $this->crudService->exportGridData($pageConfig, $request->all());
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import grid data from CSV.
     */
    public function import(Request $request, $slug)
    {
        $pageConfig = $this->crudService->getPageConfig($slug);
        if (!$pageConfig) {
            return response()->json(['error' => 'Page configuration not found.'], 404);
        }

        $permissions = $this->checkPermissions($pageConfig->id);
        if (!$permissions['can_create']) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        try {
            $handle = fopen($filePath, 'r');
            $headers = fgetcsv($handle);
            
            // Clean headers
            $headers = array_map(function($h) {
                return trim(strtolower(str_replace(' ', '_', $h)));
            }, $headers);

            $formSchema = json_decode($pageConfig->form_schema, true) ?? [];
            $schemaFields = array_column($formSchema, 'name');

            $imported = 0;
            $failed = 0;
            $errors = [];

            while (($row = fgetcsv($handle)) !== false) {
                $rowData = array_combine($headers, $row);
                
                // Keep only fields in the form schema
                $filteredRow = array_intersect_key($rowData, array_flip($schemaFields));

                try {
                    $this->crudService->createRecord($pageConfig, $filteredRow, $request->ip(), $request->userAgent());
                    $imported++;
                } catch (\Exception $ex) {
                    $failed++;
                    $errors[] = "Row " . ($imported + $failed) . ": " . $ex->getMessage();
                }
            }
            fclose($handle);

            return response()->json([
                'success' => true,
                'message' => "Import complete. {$imported} rows imported. {$failed} rows failed.",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to parse CSV: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve a pending workflow instance.
     */
    public function approveWorkflow(Request $request, $instanceId)
    {
        $user = Auth::user();
        $instance = DB::table('workflow_instances')->where('id', $instanceId)->first();
        if (!$instance) {
            return response()->json(['error' => 'Workflow instance not found.'], 404);
        }

        $workflow = DB::table('workflows')->where('id', $instance->workflow_id)->first();
        $steps = json_decode($workflow->steps, true) ?? [];
        $currentStepIdx = $instance->current_step - 1;

        if (!isset($steps[$currentStepIdx])) {
            return response()->json(['error' => 'Invalid workflow step.'], 400);
        }

        $currentStep = $steps[$currentStepIdx];

        // Check if user is Super Admin or has the step's required role
        if ($user->role_id != $currentStep['role_id'] && $user->role->slug != 'super-admin') {
            return response()->json(['error' => 'Unauthorized. You do not have the role required for this step.'], 403);
        }

        DB::beginTransaction();
        try {
            // Log approval
            DB::table('workflow_logs')->insert([
                'workflow_instance_id' => $instanceId,
                'user_id' => $user->id,
                'step_number' => $instance->current_step,
                'action' => 'APPROVE',
                'comments' => $request->input('comments'),
                'created_at' => now(),
            ]);

            $nextStepIdx = $instance->current_step;
            if (isset($steps[$nextStepIdx])) {
                // Move to next step
                DB::table('workflow_instances')->where('id', $instanceId)->update([
                    'current_step' => $instance->current_step + 1,
                    'updated_at' => now(),
                ]);

                // Notify next step role users
                $nextRole = $steps[$nextStepIdx]['role_id'];
                $usersToNotify = DB::table('users')->where('role_id', $nextRole)->get();
                foreach ($usersToNotify as $u) {
                    NotificationService::sendDirect(
                        $u->id,
                        'Workflow Action Required',
                        "Record in table '{$instance->table_name}' has been approved to step " . ($nextStepIdx + 1),
                        'WORKFLOW'
                    );
                }
            } else {
                // End of workflow - Approve the actual record
                DB::table('workflow_instances')->where('id', $instanceId)->update([
                    'status' => 'APPROVED',
                    'updated_at' => now(),
                ]);

                if (Schema::hasColumn($instance->table_name, 'status')) {
                    DB::table($instance->table_name)
                        ->where('id', $instance->record_id)
                        ->update(['status' => 'Approved']);
                }

                // Notify original creator if exists (via audit log)
                $creatorLog = DB::table('audit_logs')
                    ->where('table_name', $instance->table_name)
                    ->where('record_id', $instance->record_id)
                    ->where('action', 'CREATE')
                    ->first();
                if ($creatorLog && $creatorLog->user_id) {
                    NotificationService::sendDirect(
                        $creatorLog->user_id,
                        'Workflow Approved',
                        "Your record in '{$instance->table_name}' has been fully approved.",
                        'WORKFLOW'
                    );
                }
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Record approved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject a pending workflow instance.
     */
    public function rejectWorkflow(Request $request, $instanceId)
    {
        $user = Auth::user();
        $instance = DB::table('workflow_instances')->where('id', $instanceId)->first();
        if (!$instance) {
            return response()->json(['error' => 'Workflow instance not found.'], 404);
        }

        $workflow = DB::table('workflows')->where('id', $instance->workflow_id)->first();
        $steps = json_decode($workflow->steps, true) ?? [];
        $currentStepIdx = $instance->current_step - 1;

        if (!isset($steps[$currentStepIdx])) {
            return response()->json(['error' => 'Invalid workflow step.'], 400);
        }

        $currentStep = $steps[$currentStepIdx];

        if ($user->role_id != $currentStep['role_id'] && $user->role->slug != 'super-admin') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        DB::beginTransaction();
        try {
            // Log rejection
            DB::table('workflow_logs')->insert([
                'workflow_instance_id' => $instanceId,
                'user_id' => $user->id,
                'step_number' => $instance->current_step,
                'action' => 'REJECT',
                'comments' => $request->input('comments'),
                'created_at' => now(),
            ]);

            // Reject workflow instance
            DB::table('workflow_instances')->where('id', $instanceId)->update([
                'status' => 'REJECTED',
                'updated_at' => now(),
            ]);

            if (Schema::hasColumn($instance->table_name, 'status')) {
                DB::table($instance->table_name)
                    ->where('id', $instance->record_id)
                    ->update(['status' => 'Rejected']);
            }

            // Notify creator
            $creatorLog = DB::table('audit_logs')
                ->where('table_name', $instance->table_name)
                ->where('record_id', $instance->record_id)
                ->where('action', 'CREATE')
                ->first();
            if ($creatorLog && $creatorLog->user_id) {
                NotificationService::sendDirect(
                    $creatorLog->user_id,
                    'Workflow Rejected',
                    "Your record in '{$instance->table_name}' has been rejected. Reason: " . $request->input('comments'),
                    'WORKFLOW'
                );
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Record rejected.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a page token (Super Admin only).
     */
    public function updatePageToken(Request $request)
    {
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if (!$role || $role->slug !== 'super-admin') {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'page_id' => 'required|integer|exists:pages,id',
            'token' => 'nullable|string|max:50',
        ]);

        $pageId = $request->input('page_id');
        $token = trim($request->input('token'));

        if (empty($token)) {
            // Auto-increment numeric fallback
            $token = \App\Services\ModuleScannerService::getNextFallbackToken();
        } else {
            // Check uniqueness
            $exists = DB::table('pages')
                ->where('id', '<>', $pageId)
                ->where('token', $token)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'The page token has already been taken.'], 422);
            }
        }

        DB::table('pages')->where('id', $pageId)->update([
            'token' => $token,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Page token updated successfully.',
            'token' => $token
        ]);
    }

    /**
     * Internal helper to resolve page permissions.
     */
    protected function checkPermissions(int $pageId): array
    {
        $user = Auth::user();
        if (!$user) {
            return [
                'can_view' => false, 'can_create' => false, 'can_edit' => false, 'can_delete' => false,
                'can_export' => false, 'can_print' => false, 'can_approve' => false, 'can_reject' => false
            ];
        }

        // Fetch user's role slug
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if ($role && $role->slug === 'super-admin') {
            // Super Admin has full override permission
            return [
                'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true,
                'can_export' => true, 'can_print' => true, 'can_approve' => true, 'can_reject' => true
            ];
        }

        // Check user-specific permissions first
        $perms = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->where('page_id', $pageId)
            ->first();

        if (!$perms) {
            // Fall back to role-based permissions
            $perms = DB::table('role_permissions')
                ->where('role_id', $user->role_id)
                ->where('page_id', $pageId)
                ->first();
        }

        if (!$perms) {
            return [
                'can_view' => false, 'can_create' => false, 'can_edit' => false, 'can_delete' => false,
                'can_export' => false, 'can_print' => false, 'can_approve' => false, 'can_reject' => false
            ];
        }

        return [
            'can_view' => (bool) $perms->can_view,
            'can_create' => (bool) $perms->can_create,
            'can_edit' => (bool) $perms->can_edit,
            'can_delete' => (bool) $perms->can_delete,
            'can_export' => (bool) $perms->can_export,
            'can_print' => (bool) $perms->can_print,
            'can_approve' => (bool) $perms->can_approve,
            'can_reject' => (bool) $perms->can_reject,
        ];
    }
}
