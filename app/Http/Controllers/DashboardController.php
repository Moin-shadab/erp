<?php

namespace App\Http\Controllers;

use App\Services\ModuleScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Fetch analytics and return the dashboard view.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Get filter context (company, branch)
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        // 1. Core KPIs
        $salesQuery = DB::table('sales_invoices')->whereIn('status', ['Approved', 'Paid']);
        if ($companyId) $salesQuery->where('company_id', $companyId);
        if ($branchId) $salesQuery->where('branch_id', $branchId);
        $totalSales = $salesQuery->sum('total_amount');

        $usersCount = DB::table('users')->where('is_active', true)->count();
        
        $pendingWorkflows = DB::table('workflow_instances')->where('status', 'PENDING')->count();
        $totalAudits = DB::table('audit_logs')->count();

        // 2. Monthly Sales Analytics Chart Data
        $monthlySalesQuery = DB::table('sales_invoices')
            ->select(DB::raw("DATE_FORMAT(invoice_date, '%b %Y') as month"), DB::raw('SUM(total_amount) as total'))
            ->whereIn('status', ['Approved', 'Paid'])
            ->groupBy(DB::raw("DATE_FORMAT(invoice_date, '%Y-%m')"), 'invoice_date');
        if ($companyId) $monthlySalesQuery->where('company_id', $companyId);
        $monthlySalesRaw = $monthlySalesQuery->limit(6)->get();

        $monthlySales = [];
        foreach ($monthlySalesRaw as $ms) {
            $monthlySales[$ms->month] = ($monthlySales[$ms->month] ?? 0) + (float)$ms->total;
        }

        // 3. System Health Diagnostics
        $dbSize = '0.00 MB';
        try {
            $dbName = config('database.connections.mysql.database');
            $dbSizeResult = DB::select("
                SELECT SUM(data_length + index_length) / 1024 / 1024 AS size 
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [$dbName]);
            if (!empty($dbSizeResult)) {
                $dbSize = number_format((float)$dbSizeResult[0]->size, 2) . ' MB';
            }
        } catch (\Exception $e) {
            $dbSize = 'Unknown';
        }

        // Disk calculations
        $totalDisk = @disk_total_space('/') ?: (100 * 1024 * 1024 * 1024);
        $freeDisk = @disk_free_space('/') ?: (40 * 1024 * 1024 * 1024);
        $usedDisk = $totalDisk - $freeDisk;
        $diskPercentage = round(($usedDisk / $totalDisk) * 100, 1);

        $diskTotalGB = number_format($totalDisk / 1024 / 1024 / 1024, 1) . ' GB';
        $diskFreeGB = number_format($freeDisk / 1024 / 1024 / 1024, 1) . ' GB';
        $diskUsedGB = number_format($usedDisk / 1024 / 1024 / 1024, 1) . ' GB';

        // CPU load average
        $cpuLoad = '0.0%';
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load) {
                $cpuLoad = number_format($load[0] * 10, 1) . '%'; // Scale roughly
            }
        } else {
            $cpuLoad = '1.2%';
        }

        $systemHealth = [
            'db_name' => config('database.connections.mysql.database'),
            'db_size' => $dbSize,
            'disk_total' => $diskTotalGB,
            'disk_used' => $diskUsedGB,
            'disk_free' => $diskFreeGB,
            'disk_percent' => $diskPercentage,
            'cpu_load' => $cpuLoad,
            'php_version' => PHP_VERSION,
            'mysql_version' => DB::select('select version() as version')[0]->version ?? 'Unknown'
        ];

        // 4. Recent Audit Logs
        $recentAudits = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.name as user_name')
            ->orderBy('audit_logs.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                $log->time_ago = $this->timeAgo($log->created_at);
                return $log;
            });

        // 5. Workflows Pending User Review
        // A user can approve a step if they have the role required by the step, or if they are Super Admin
        $myPendingApprovals = [];
        $workflowInstances = DB::table('workflow_instances')
            ->where('workflow_instances.status', 'PENDING')
            ->join('workflows', 'workflow_instances.workflow_id', '=', 'workflows.id')
            ->select('workflow_instances.*', 'workflows.name as workflow_name', 'workflows.steps')
            ->get();

        foreach ($workflowInstances as $wi) {
            $steps = json_decode($wi->steps, true) ?? [];
            $currentStepIdx = $wi->current_step - 1;
            
            if (isset($steps[$currentStepIdx])) {
                $step = $steps[$currentStepIdx];
                // Check if user is allowed to approve this step (matches role_id or is Super Admin)
                $isSuperAdmin = DB::table('roles')->where('id', $user->role_id)->value('slug') === 'super-admin';
                if ($user->role_id == $step['role_id'] || $isSuperAdmin) {
                    // Fetch details of target record
                    $record = DB::table($wi->table_name)->where('id', $wi->record_id)->first();
                    $recordSummary = 'Record #' . $wi->record_id;
                    if ($record) {
                        if (isset($record->invoice_no)) {
                            $recordSummary = 'Invoice: ' . $record->invoice_no . ' (Amount: ₹' . number_format($record->total_amount, 2) . ')';
                        } else if (isset($record->name)) {
                            $recordSummary = $record->name;
                        }
                    }
                    
                    $myPendingApprovals[] = [
                        'id' => $wi->id,
                        'workflow_name' => $wi->workflow_name,
                        'record_summary' => $recordSummary,
                        'current_step_name' => $step['name'] ?? ('Step ' . $wi->current_step),
                        'created_at' => $wi->created_at,
                        'time_ago' => $this->timeAgo($wi->created_at)
                    ];
                }
            }
        }

        // AJAX response check
        if ($request->ajax()) {
            return view('dashboard_content', compact('totalSales', 'usersCount', 'pendingWorkflows', 'totalAudits', 'monthlySales', 'systemHealth', 'recentAudits', 'myPendingApprovals'));
        }

        return view('dashboard', compact('totalSales', 'usersCount', 'pendingWorkflows', 'totalAudits', 'monthlySales', 'systemHealth', 'recentAudits', 'myPendingApprovals'));
    }

    /**
     * Context configurations API.
     */
    public function getUserContext()
    {
        ModuleScannerService::scan();

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Get Role slug and name
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        
        $companies = DB::table('companies')->get();
        $branches = DB::table('branches')->where('company_id', $user->company_id)->get();
        $departments = DB::table('departments')->whereIn('branch_id', $branches->pluck('id'))->get();

        // Get active email accounts
        $emailAccounts = DB::table('email_accounts')
            ->join('email_account_users', 'email_accounts.id', '=', 'email_account_users.email_account_id')
            ->where('email_account_users.user_id', $user->id)
            ->select('email_accounts.*')
            ->get();
        
        // Active email switcher
        $activeEmailId = session('active_email_account_id');
        if (!$activeEmailId && $emailAccounts->isNotEmpty()) {
            $activeEmailId = $emailAccounts->first()->id;
            session(['active_email_account_id' => $activeEmailId]);
        }

        // Fetch authorized Navigation Modules
        $modules = DB::table('modules')
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get()
            ->map(function ($mod) use ($user) {
                // Fetch pages user has permission to view
                $isSuperAdmin = DB::table('roles')->where('id', $user->role_id)->value('slug') === 'super-admin';
                
                $pagesQuery = DB::table('pages')
                    ->where('module_id', $mod->id)
                    ->where('is_active', true);

                if (!$isSuperAdmin) {
                    $hasUserPerms = DB::table('user_permissions')->where('user_id', $user->id)->exists();
                    if ($hasUserPerms) {
                        $pagesQuery->join('user_permissions', 'pages.id', '=', 'user_permissions.page_id')
                            ->where('user_permissions.user_id', $user->id)
                            ->where('user_permissions.can_view', true);
                    } else {
                        $pagesQuery->join('role_permissions', 'pages.id', '=', 'role_permissions.page_id')
                            ->where('role_permissions.role_id', $user->role_id)
                            ->where('role_permissions.can_view', true);
                    }
                }

                $pages = $pagesQuery->select('pages.*')
                    ->orderBy('pages.name')
                    ->get();

                $mod->pages = $pages;
                return $mod;
            })->filter(function($mod) {
                return $mod->pages->isNotEmpty(); // Only show modules with pages
            })->values();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_name' => $role->name,
                'role_slug' => $role->slug,
                'company_id' => $user->company_id,
                'company_code' => DB::table('companies')->where('id', $user->company_id)->value('code'),
                'branch_id' => $user->branch_id,
                'department_id' => $user->department_id,
                'is_department_head' => $user->department_id ? DB::table('departments')->where('id', $user->department_id)->where('manager_id', $user->id)->exists() : false,
                'department_subordinates' => ($user->department_id && DB::table('departments')->where('id', $user->department_id)->where('manager_id', $user->id)->exists())
                    ? DB::table('users')
                        ->where('department_id', $user->department_id)
                        ->where('id', '<>', $user->id)
                        ->where('is_active', true)
                        ->select('id', 'name', 'email')
                        ->get()
                    : []
            ],
            'companies' => $companies,
            'branches' => $branches,
            'departments' => $departments,
            'email_accounts' => $emailAccounts->map(function($acc) {
                return ['id' => $acc->id, 'name' => $acc->display_name . ' (' . $acc->email . ')'];
            }),
            'active_email_account_id' => $activeEmailId,
            'modules' => $modules
        ]);
    }

    /**
     * Switch current session context (Company, Branch, Department).
     */
    public function switchContext(Request $request)
    {
        $user = Auth::user();
        $companyId = $request->input('company_id');
        $branchId = $request->input('branch_id');
        $departmentId = $request->input('department_id');

        // Update database user context
        DB::table('users')->where('id', $user->id)->update([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Helper to compute time difference readable text.
     */
    protected function timeAgo($timestamp): string
    {
        $time = strtotime($timestamp);
        $diff = time() - $time;

        if ($diff < 60) return 'Just now';
        $diff = round($diff / 60);
        if ($diff < 60) return $diff . ' m ago';
        $diff = round($diff / 60);
        if ($diff < 24) return $diff . ' h ago';
        $diff = round($diff / 24);
        return $diff . ' d ago';
    }
}
