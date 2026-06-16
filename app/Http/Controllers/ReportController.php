<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    // Restrict report queries to whitelisted tables to prevent SQL leakage of sensitive data
    protected $tableWhitelist = [
        'customers' => 'Customers List',
        'sales_invoices' => 'Sales Invoices',
        'inventory_items' => 'Inventory Assets',
        'audit_logs' => 'System Audit Logs',
        'users' => 'Staff Directory',
        'emails' => 'Email Logbook'
    ];

    protected function checkPagePermission(string $slug)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        $page = DB::table('pages')->where('slug', $slug)->where('is_active', true)->first();
        if (!$page) {
            abort(404, 'Page not found or inactive.');
        }

        // Super Admin bypass
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if ($role && $role->slug === 'super-admin') {
            return;
        }

        // Check user-specific permissions first
        $perms = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->where('page_id', $page->id)
            ->first();

        if (!$perms) {
            // Fall back to role-based permissions
            $perms = DB::table('role_permissions')
                ->where('role_id', $user->role_id)
                ->where('page_id', $page->id)
                ->first();
        }

        if (!$perms || !$perms->can_view) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }
        $this->checkPagePermission('report-builder');
        $tables = $this->tableWhitelist;
        
        $savedReports = DB::table('reports')
            ->where('created_by', Auth::id())
            ->orderBy('name')
            ->get();

        return view('modules.loader', array_merge(
            compact('tables', 'savedReports'),
            ['pageDir' => 'modules/analytics_console/reports']
        ));
    }

    /**
     * Fetch the physical table columns for the select parameters.
     */
    public function getColumns($table)
    {
        $this->checkPagePermission('report-builder');
        if (!array_key_exists($table, $this->tableWhitelist)) {
            return response()->json(['error' => 'Invalid table selected.'], 400);
        }

        $columns = Schema::getColumnListing($table);
        // Exclude internal passwords or tokens
        $columns = array_values(array_filter($columns, function($c) {
            return !in_array($c, ['password', 'remember_token', 'smtp_password', 'imap_password', 'pop3_password']);
        }));

        return response()->json(['columns' => $columns]);
    }

    /**
     * Compile and execute dynamic report queries securely.
     */
    public function generate(Request $request)
    {
        $this->checkPagePermission('report-builder');
        $table = $request->input('table');
        $columns = $request->input('columns', []);
        $filters = $request->input('filters', []);

        if (!array_key_exists($table, $this->tableWhitelist)) {
            return response()->json(['error' => 'Invalid table.'], 400);
        }

        if (empty($columns)) {
            return response()->json(['error' => 'Please select at least one column.'], 400);
        }

        // Build query securely using query builder
        $query = DB::table($table)->select($columns);

        // Apply dynamic filters
        foreach ($filters as $f) {
            $col = $f['column'] ?? null;
            $op = $f['operator'] ?? null;
            $val = $f['value'] ?? null;

            if ($col && $op && Schema::hasColumn($table, $col)) {
                // Secure operator values
                switch ($op) {
                    case 'eq':
                        $query->where($col, '=', $val);
                        break;
                    case 'neq':
                        $query->where($col, '<>', $val);
                        break;
                    case 'like':
                        $query->where($col, 'like', '%' . $val . '%');
                        break;
                    case 'gt':
                        $query->where($col, '>', $val);
                        break;
                    case 'lt':
                        $query->where($col, '<', $val);
                        break;
                }
            }
        }

        try {
            $data = $query->limit(1000)->get();
            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'SQL Error executing report query.'], 500);
        }
    }

    /**
     * Save report layout configuration.
     */
    public function save(Request $request)
    {
        $this->checkPagePermission('report-builder');
        $request->validate([
            'name' => 'required',
            'table' => 'required',
            'columns' => 'required|array'
        ]);

        $id = $request->input('id');
        
        $data = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'base_table' => $request->input('table'),
            'columns' => json_encode($request->input('columns')),
            'filters' => json_encode($request->input('filters', [])),
            'created_by' => Auth::id(),
            'updated_at' => now()
        ];

        if ($id) {
            DB::table('reports')->where('id', $id)->where('created_by', Auth::id())->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('reports')->insert($data);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Delete report layout.
     */
    public function destroy($id)
    {
        $this->checkPagePermission('report-builder');
        $deleted = DB::table('reports')
            ->where('id', $id)
            ->where('created_by', Auth::id())
            ->delete();

        return response()->json(['success' => $deleted > 0]);
    }
}
