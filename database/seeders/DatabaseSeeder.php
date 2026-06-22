<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Companies
        $acmeId = DB::table('companies')->insertGetId([
            'name' => 'Acme Corporation (India)',
            'code' => 'ACME',
            'logo' => null,
            'address' => 'Express Towers, Nariman Point, Mumbai, MH 400021',
            'phone' => '+91 22 5550 1999',
            'email' => 'finance@acme-india.in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Branches
        $mumbaiBranchId = DB::table('branches')->insertGetId([
            'company_id' => $acmeId,
            'name' => 'Mumbai Head Office',
            'code' => 'ACME-BOM',
            'address' => 'Express Towers, Mumbai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blrBranchId = DB::table('branches')->insertGetId([
            'company_id' => $acmeId,
            'name' => 'Bengaluru Tech Branch',
            'code' => 'ACME-BLR',
            'address' => 'Brigade Road, Bengaluru, KA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Departments
        $depts = [
            'EXEC' => 'Executive Office',
            'SALES' => 'Sales Department',
            'ACCTS' => 'Accounts & Finance',
            'ENG' => 'Engineering',
            'SRV' => 'Service & Support',
            'HR' => 'Human Resources'
        ];
        
        $deptIds = [];
        foreach ($depts as $code => $name) {
            $deptIds[$code] = DB::table('departments')->insertGetId([
                'branch_id' => $mumbaiBranchId,
                'name' => $name,
                'code' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Roles
        $roles = [
            'super-admin' => ['Super Admin', 'Unrestricted system access.'],
            'admin' => ['Admin', 'General administrator credentials.'],
            'sales-head' => ['Sales Head', 'Manages region representatives and customer flags.'],
            'sales-rep' => ['Sales Representative', 'Manages customer records and invoice drafts.'],
            'accounts-head' => ['Accounts Head', 'Manages ledger statements and signs workflows.'],
            'accounts-member' => ['Accounts Assistant', 'Reviews billing and audit records.'],
            'user' => ['General User', 'Basic read-only workspace access.']
        ];

        $roleIds = [];
        foreach ($roles as $slug => $info) {
            $roleIds[$slug] = DB::table('roles')->insertGetId([
                'name' => $info[0],
                'slug' => $slug,
                'description' => $info[1],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Users with Hierarchical reporting structures (reports_to_id)
        $password = Hash::make('password');

        // Level 1: CFO (Top Level)
        $cfoId = DB::table('users')->insertGetId([
            'name' => 'Michael Chang (CFO)',
            'email' => 'admin@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['super-admin'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['EXEC'],
            'reports_to_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Level 2: Heads of Departments (Report to CFO)
        $northSalesHeadId = DB::table('users')->insertGetId([
            'name' => 'Sarah Jenkins (North Sales Head)',
            'email' => 'north.head@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['sales-head'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['SALES'],
            'reports_to_id' => $cfoId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $southSalesHeadId = DB::table('users')->insertGetId([
            'name' => 'Rajesh Kumar (South Sales Head)',
            'email' => 'south.head@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['sales-head'],
            'company_id' => $acmeId,
            'branch_id' => $blrBranchId,
            'department_id' => $deptIds['SALES'],
            'reports_to_id' => $cfoId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountsHeadId = DB::table('users')->insertGetId([
            'name' => 'Ananya Sen (Finance Head)',
            'email' => 'accounts.head@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['accounts-head'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['ACCTS'],
            'reports_to_id' => $cfoId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Level 3: Department Members / Reps (Report to Heads)
        $northRep1Id = DB::table('users')->insertGetId([
            'name' => 'David Lee (Delhi Representative)',
            'email' => 'rep.north1@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['sales-rep'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['SALES'],
            'reports_to_id' => $northSalesHeadId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $northRep2Id = DB::table('users')->insertGetId([
            'name' => 'Amit Sharma (Noida Representative)',
            'email' => 'rep.north2@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['sales-rep'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['SALES'],
            'reports_to_id' => $northSalesHeadId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $southRep1Id = DB::table('users')->insertGetId([
            'name' => 'Karthik Raja (Bengaluru Rep)',
            'email' => 'rep.south1@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['sales-rep'],
            'company_id' => $acmeId,
            'branch_id' => $blrBranchId,
            'department_id' => $deptIds['SALES'],
            'reports_to_id' => $southSalesHeadId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountsMemberId = DB::table('users')->insertGetId([
            'name' => 'Sanjay Dutt (Accounts Assistant)',
            'email' => 'accounts.member@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['accounts-member'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['ACCTS'],
            'reports_to_id' => $accountsHeadId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // General staff user
        $generalStaffId = DB::table('users')->insertGetId([
            'name' => 'Pooja Hegde (Office Executive)',
            'email' => 'user@mserp.com',
            'password' => $password,
            'role_id' => $roleIds['user'],
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'department_id' => $deptIds['SRV'],
            'reports_to_id' => $cfoId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Navigation Modules (Low-Code Sidebar)
        $modOrg = DB::table('modules')->insertGetId([
            'name' => 'Organization Panel',
            'icon' => 'bi-building',
            'sequence' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $modErp = DB::table('modules')->insertGetId([
            'name' => 'Core ERP Data',
            'icon' => 'bi-database',
            'sequence' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7. Pages Configurations (Metadata engine core)
        // Page A: Customers CRUD (India-Focused details)
        $pageCustId = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Customers Management',
            'slug' => 'customers',
            'token' => 'CUST-100',
            'title' => 'Indian Customer Accounts Directory',
            'db_table' => 'customers',
            'primary_key' => 'id',
            'sql_query' => 'SELECT customers.id, customers.name, customers.gstin, customers.pan, customers.state, customers.city, users.name as assigned_to, customers.status FROM customers LEFT JOIN users ON customers.assigned_user_id = users.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Customer Name', 'flex' => 1.5],
                ['field' => 'gstin', 'headerName' => 'GSTIN (India)', 'flex' => 1.2],
                ['field' => 'pan', 'headerName' => 'PAN Card', 'flex' => 1.0],
                ['field' => 'state', 'headerName' => 'State', 'flex' => 1.0],
                ['field' => 'city', 'headerName' => 'City', 'flex' => 0.8],
                ['field' => 'assigned_to', 'headerName' => 'Assigned Officer', 'flex' => 1.2],
                ['field' => 'status', 'headerName' => 'Status', 'flex' => 0.8]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Customer Corporate Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'email', 'label' => 'Primary Email', 'type' => 'email', 'validation' => 'required|email|max:255', 'grid_width' => 6],
                ['name' => 'phone', 'label' => 'Phone / Contact No', 'type' => 'text', 'validation' => 'nullable|string|max:50', 'grid_width' => 6],
                ['name' => 'gstin', 'label' => 'GSTIN Number (15 Digits)', 'type' => 'text', 'validation' => 'required|string|size:15', 'grid_width' => 6],
                ['name' => 'pan', 'label' => 'PAN Card No (10 Digits)', 'type' => 'text', 'validation' => 'required|string|size:10', 'grid_width' => 6],
                ['name' => 'state', 'label' => 'State', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 4, 'options' => [
                    ['value' => 'Maharashtra', 'label' => 'Maharashtra'],
                    ['value' => 'Karnataka', 'label' => 'Karnataka'],
                    ['value' => 'Tamil Nadu', 'label' => 'Tamil Nadu'],
                    ['value' => 'Delhi', 'label' => 'Delhi'],
                    ['value' => 'Gujarat', 'label' => 'Gujarat'],
                    ['value' => 'Uttar Pradesh', 'label' => 'Uttar Pradesh'],
                    ['value' => 'West Bengal', 'label' => 'West Bengal'],
                    ['value' => 'Telangana', 'label' => 'Telangana']
                ]],
                ['name' => 'city', 'label' => 'City', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 4, 'options' => []],
                ['name' => 'business_type', 'label' => 'Business Entity Type', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 4, 'options' => [
                    ['value' => 'Sole Proprietorship', 'label' => 'Sole Proprietorship'],
                    ['value' => 'Partnership', 'label' => 'Partnership'],
                    ['value' => 'Private Limited', 'label' => 'Private Limited'],
                    ['value' => 'Public Limited', 'label' => 'Public Limited']
                ]],
                ['name' => 'assigned_user_id', 'label' => 'Assigned Officer / Representative', 'type' => 'select', 'validation' => 'nullable|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'users', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'is_restricted_subordinates', 'label' => 'Supervisor Visibility Overrides', 'type' => 'select', 'validation' => 'required|boolean', 'grid_width' => 6, 'options' => [
                    ['value' => 0, 'label' => 'No restriction - Visible to subordinates'],
                    ['value' => 1, 'label' => 'Restrict - Only visible to assigned head/manager']
                ]],
                ['name' => 'address', 'label' => 'Corporate Office Address', 'type' => 'textarea', 'validation' => 'nullable|string', 'grid_width' => 12],
                ['name' => 'status', 'label' => 'Account Status', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 12, 'options' => [
                    ['value' => 'Active', 'label' => 'Active'],
                    ['value' => 'Inactive', 'label' => 'Inactive']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-people',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Page B: Sales Invoices CRUD
        $pageInvId = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Sales Invoices',
            'slug' => 'sales-invoices',
            'token' => 'INV-200',
            'title' => 'Sales Ledger & Billings',
            'db_table' => 'sales_invoices',
            'primary_key' => 'id',
            'sql_query' => 'SELECT sales_invoices.id, sales_invoices.invoice_no, customers.name as customer_name, sales_invoices.invoice_date, sales_invoices.total_amount, sales_invoices.status FROM sales_invoices LEFT JOIN customers ON sales_invoices.customer_id = customers.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'invoice_no', 'headerName' => 'Invoice No', 'flex' => 1.2],
                ['field' => 'customer_name', 'headerName' => 'Customer Name', 'flex' => 1.8],
                ['field' => 'invoice_date', 'headerName' => 'Date issued', 'flex' => 1.0],
                ['field' => 'total_amount', 'headerName' => 'Total Amount (₹)', 'flex' => 1.2],
                ['field' => 'status', 'headerName' => 'Approval Status', 'flex' => 1.0]
            ]),
            'form_schema' => json_encode([
                ['name' => 'invoice_no', 'label' => 'Invoice Reference', 'type' => 'text', 'validation' => 'required|string|unique:sales_invoices,invoice_no', 'grid_width' => 6],
                ['name' => 'customer_id', 'label' => 'Select Customer', 'type' => 'select', 'validation' => 'required|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'customers', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'invoice_date', 'label' => 'Billing Date', 'type' => 'date', 'validation' => 'required|date', 'grid_width' => 6],
                ['name' => 'due_date', 'label' => 'Due Date', 'type' => 'date', 'validation' => 'required|date', 'grid_width' => 6],
                ['name' => 'amount', 'label' => 'Subtotal Amount (₹)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 4],
                ['name' => 'tax', 'label' => 'Tax Amount (₹)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 4],
                ['name' => 'total_amount', 'label' => 'Total Amount (₹)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 4],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 12, 'options' => [
                    ['value' => 'Draft', 'label' => 'Draft'],
                    ['value' => 'Pending Approval', 'label' => 'Pending Approval'],
                    ['value' => 'Approved', 'label' => 'Approved'],
                    ['value' => 'Paid', 'label' => 'Paid']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-receipt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Page C: Inventory Items CRUD
        $pageInvItem = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Inventory Items',
            'slug' => 'inventory-items',
            'token' => 'STK-300',
            'title' => 'Warehouse & Inventory Stock',
            'db_table' => 'inventory_items',
            'primary_key' => 'id',
            'sql_query' => 'SELECT * FROM inventory_items',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'item_code', 'headerName' => 'SKU Code', 'flex' => 1.0],
                ['field' => 'name', 'headerName' => 'Item Description', 'flex' => 1.8],
                ['field' => 'category', 'headerName' => 'Category', 'flex' => 1.2],
                ['field' => 'qty_on_hand', 'headerName' => 'Stock Qty', 'flex' => 0.8],
                ['field' => 'unit_price', 'headerName' => 'Unit Price (₹)', 'flex' => 1.0],
                ['field' => 'status', 'headerName' => 'Status', 'flex' => 1.0]
            ]),
            'form_schema' => json_encode([
                ['name' => 'item_code', 'label' => 'SKU / Item Code', 'type' => 'text', 'validation' => 'required|string|unique:inventory_items,item_code', 'grid_width' => 6],
                ['name' => 'name', 'label' => 'Item Description', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 6],
                ['name' => 'category', 'label' => 'Category Type', 'type' => 'text', 'validation' => 'nullable|string|max:100', 'grid_width' => 6],
                ['name' => 'qty_on_hand', 'label' => 'Stock Quantity', 'type' => 'number', 'validation' => 'required|integer|min:0', 'grid_width' => 6],
                ['name' => 'unit_price', 'label' => 'Unit Price (₹)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 6],
                ['name' => 'reorder_level', 'label' => 'Reorder Threshold Level', 'type' => 'number', 'validation' => 'required|integer|min:1', 'grid_width' => 6],
                ['name' => 'status', 'label' => 'Stock status', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 12, 'options' => [
                    ['value' => 'In Stock', 'label' => 'In Stock'],
                    ['value' => 'Low Stock', 'label' => 'Low Stock'],
                    ['value' => 'Out of Stock', 'label' => 'Out of Stock']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-box-seam',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Page D: User Profiles Settings Configuration
        $pageUserConfig = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Staff Profiles',
            'slug' => 'staff-profiles',
            'token' => 'USR-400',
            'title' => 'Corporate User Accounts Manager',
            'db_table' => 'users',
            'primary_key' => 'id',
            'sql_query' => 'SELECT users.id, users.name, users.email, roles.name as role_name, head.name as supervisor, users.is_active FROM users LEFT JOIN roles ON users.role_id = roles.id LEFT JOIN users as head ON users.reports_to_id = head.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Full Name', 'flex' => 1.5],
                ['field' => 'email', 'headerName' => 'Corporate Email', 'flex' => 1.8],
                ['field' => 'role_name', 'headerName' => 'Security Role', 'flex' => 1.0],
                ['field' => 'supervisor', 'headerName' => 'Reporting Supervisor', 'flex' => 1.2],
                ['field' => 'is_active', 'headerName' => 'Account Active', 'flex' => 0.8]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Employee Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 6],
                ['name' => 'email', 'label' => 'Corporate Email', 'type' => 'email', 'validation' => 'required|email|unique:users,email', 'grid_width' => 6],
                ['name' => 'password', 'label' => 'Password credentials', 'type' => 'password', 'validation' => 'required|string|min:4', 'grid_width' => 6],
                ['name' => 'role_id', 'label' => 'Security Role Class', 'type' => 'select', 'validation' => 'required|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'roles', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'reports_to_id', 'label' => 'Reporting Supervisor / Head', 'type' => 'select', 'validation' => 'nullable|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'users', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'is_active', 'label' => 'Account Enabled Status', 'type' => 'select', 'validation' => 'required|boolean', 'grid_width' => 12, 'options' => [
                    ['value' => 1, 'label' => 'Active / Enabled'],
                    ['value' => 0, 'label' => 'Disabled']
                ]]
            ]),
            'is_custom' => true,
            'custom_view' => 'modules/organization_panel/user_management',
            'is_active' => true,
            'icon' => 'bi-person-badge',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Page E: Custom Role-Permission Matrix Manager
        $pagePermMatrixId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Permissions Matrix',
            'slug' => 'permissions-matrix',
            'token' => 'ADM-500',
            'title' => 'Dynamic Access Permissions Control',
            'db_table' => 'role_permissions',
            'primary_key' => 'id',
            'is_custom' => true,
            'custom_view' => 'modules/organization_panel/permissions_matrix',
            'is_active' => true,
            'icon' => 'bi-shield-lock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Page F: Notification Routing
        $pageNotifRoutingId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Notification Routing',
            'slug' => 'notification-routing',
            'token' => 'ADM-501',
            'title' => 'Notification Routing Hierarchy',
            'db_table' => 'notification_routes',
            'primary_key' => 'id',
            'is_custom' => true,
            'custom_view' => 'modules/organization_panel/notification_routing',
            'is_active' => true,
            'icon' => 'bi-diagram-3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Page G: Broadcast Alerts
        $pageBroadcastId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Broadcast Alerts',
            'slug' => 'broadcast-alerts',
            'token' => 'ADM-502',
            'title' => 'System Alert Broadcasts Manager',
            'db_table' => 'broadcasts',
            'primary_key' => 'id',
            'sql_query' => null,
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'title', 'headerName' => 'Title', 'flex' => 1.5],
                ['field' => 'message', 'headerName' => 'Message', 'flex' => 3.0],
                ['field' => 'scope', 'headerName' => 'Scope', 'flex' => 1.0],
                ['field' => 'created_at', 'headerName' => 'Created At', 'flex' => 1.2]
            ]),
            'form_schema' => json_encode([
                ['name' => 'title', 'label' => 'Alert Title', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'message', 'label' => 'Alert Message Details', 'type' => 'textarea', 'validation' => 'required|string', 'grid_width' => 12],
                ['name' => 'scope', 'label' => 'Target Scope', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 6, 'options' => [
                    ['value' => 'everyone', 'label' => 'Everyone (All Staff)'],
                    ['value' => 'department', 'label' => 'Specific Department'],
                    ['value' => 'user', 'label' => 'Specific Employee']
                ]],
                ['name' => 'target_id', 'label' => 'Target ID (Required for Department/Employee)', 'type' => 'number', 'validation' => 'nullable|integer', 'grid_width' => 6]
            ]),
            'is_custom' => false,
            'custom_view' => null,
            'is_active' => true,
            'icon' => 'bi-megaphone',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7.5 Additional Modules & Pages
        $modComm = DB::table('modules')->insertGetId([
            'name' => 'Communication Hub',
            'icon' => 'bi-envelope',
            'sequence' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $modAnalytics = DB::table('modules')->insertGetId([
            'name' => 'Analytics Console',
            'icon' => 'bi-bar-chart-steps',
            'sequence' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageInboxId = DB::table('pages')->insertGetId([
            'module_id' => $modComm,
            'name' => 'Email Inbox',
            'slug' => 'email-inbox',
            'token' => 'EML-101',
            'title' => 'Corporate Email Inbox',
            'db_table' => 'emails',
            'primary_key' => 'id',
            'is_custom' => true,
            'custom_view' => 'modules/email/inbox',
            'is_active' => true,
            'icon' => 'bi-envelope',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageContactsId = DB::table('pages')->insertGetId([
            'module_id' => $modComm,
            'name' => 'Address Book',
            'slug' => 'email-contacts',
            'token' => 'EML-102',
            'title' => 'Email Contact Directory',
            'db_table' => 'email_contacts',
            'primary_key' => 'id',
            'is_custom' => true,
            'custom_view' => 'modules/email/contacts',
            'is_active' => true,
            'icon' => 'bi-journal-bookmark',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageComposeId = DB::table('pages')->insertGetId([
            'module_id' => $modComm,
            'name' => 'Compose Email',
            'slug' => 'email-compose',
            'token' => 'EML-103',
            'title' => 'Write Corporate Email',
            'db_table' => 'emails',
            'primary_key' => 'id',
            'is_custom' => true,
            'custom_view' => 'modules/email/compose',
            'is_active' => true,
            'icon' => 'bi-pencil-square',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageReportsId = DB::table('pages')->insertGetId([
            'module_id' => $modAnalytics,
            'name' => 'Report Builder',
            'slug' => 'report-builder',
            'token' => 'RPT-600',
            'title' => 'Dynamic Enterprise Reports',
            'db_table' => 'reports',
            'primary_key' => 'id',
            'is_custom' => true,
            'custom_view' => 'modules/analytics_console/reports',
            'is_active' => true,
            'icon' => 'bi-bar-chart-steps',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Companies CRUD Config
        $pageCompId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Company Master',
            'slug' => 'companies',
            'token' => 'COMP-100',
            'title' => 'Companies Registry',
            'db_table' => 'companies',
            'primary_key' => 'id',
            'sql_query' => 'SELECT * FROM companies',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Company Name', 'flex' => 2.0],
                ['field' => 'code', 'headerName' => 'Code', 'flex' => 1.0],
                ['field' => 'address', 'headerName' => 'Address', 'flex' => 2.0],
                ['field' => 'phone', 'headerName' => 'Phone', 'flex' => 1.2],
                ['field' => 'email', 'headerName' => 'Email', 'flex' => 1.5]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Company Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:companies,code', 'grid_width' => 6],
                ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'validation' => 'nullable|email|max:255', 'grid_width' => 6],
                ['name' => 'phone', 'label' => 'Contact Phone', 'type' => 'text', 'validation' => 'nullable|string|max:50', 'grid_width' => 6],
                ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'validation' => 'nullable|string', 'grid_width' => 12]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-building-fill',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Branches CRUD Config
        $pageBranchId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Branch Master',
            'slug' => 'branches',
            'token' => 'BRCH-100',
            'title' => 'Branches Registry',
            'db_table' => 'branches',
            'primary_key' => 'id',
            'sql_query' => 'SELECT branches.id, branches.name, branches.code, companies.name as company_name, branches.address FROM branches JOIN companies ON branches.company_id = companies.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Branch Name', 'flex' => 1.8],
                ['field' => 'code', 'headerName' => 'Code', 'flex' => 1.0],
                ['field' => 'company_name', 'headerName' => 'Associated Company', 'flex' => 1.8],
                ['field' => 'address', 'headerName' => 'Address', 'flex' => 2.0]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Branch Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique Branch Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:branches,code', 'grid_width' => 6],
                ['name' => 'company_id', 'label' => 'Select Company', 'type' => 'select', 'validation' => 'required|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'companies', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'address', 'label' => 'Branch Address', 'type' => 'textarea', 'validation' => 'nullable|string', 'grid_width' => 12]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-geo-alt-fill',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Department Master CRUD Config
        $pageDeptId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Department Master',
            'slug' => 'departments',
            'token' => 'DEPT-100',
            'title' => 'Departments Registry',
            'db_table' => 'departments',
            'primary_key' => 'id',
            'sql_query' => 'SELECT departments.id, departments.name, departments.code, branches.name as branch_name, mgr.name as manager_name FROM departments JOIN branches ON departments.branch_id = branches.id LEFT JOIN users as mgr ON departments.manager_id = mgr.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Department Name', 'flex' => 1.8],
                ['field' => 'code', 'headerName' => 'Code', 'flex' => 1.0],
                ['field' => 'branch_name', 'headerName' => 'Branch Office', 'flex' => 1.8],
                ['field' => 'manager_name', 'headerName' => 'Department Manager', 'flex' => 1.5]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Department Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique Department Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:departments,code', 'grid_width' => 6],
                ['name' => 'branch_id', 'label' => 'Select Branch', 'type' => 'select', 'validation' => 'required|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'branches', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'manager_id', 'label' => 'Select Manager', 'type' => 'select', 'validation' => 'nullable|integer', 'grid_width' => 12, 'options_source' => 'table', 'options_table' => 'users', 'options_key' => 'id', 'options_value' => 'name']
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-diagram-2-fill',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Vendors CRUD Config
        $pageVendorId = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Vendors Management',
            'slug' => 'vendors',
            'token' => 'VND-100',
            'title' => 'Vendors/Suppliers Directory',
            'db_table' => 'vendors',
            'primary_key' => 'id',
            'sql_query' => 'SELECT vendors.id, vendors.name, vendors.code, vendors.email, vendors.phone, companies.name as company_name, branches.name as branch_name, vendors.status FROM vendors LEFT JOIN companies ON vendors.company_id = companies.id LEFT JOIN branches ON vendors.branch_id = branches.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Vendor Name', 'flex' => 1.8],
                ['field' => 'code', 'headerName' => 'SKU Code', 'flex' => 1.0],
                ['field' => 'email', 'headerName' => 'Email', 'flex' => 1.5],
                ['field' => 'phone', 'headerName' => 'Phone', 'flex' => 1.2],
                ['field' => 'company_name', 'headerName' => 'Company', 'flex' => 1.5],
                ['field' => 'branch_name', 'headerName' => 'Branch', 'flex' => 1.5],
                ['field' => 'status', 'headerName' => 'Status', 'flex' => 0.8]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Vendor Corporate Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique Vendor Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:vendors,code', 'grid_width' => 6],
                ['name' => 'email', 'label' => 'Primary Email', 'type' => 'email', 'validation' => 'nullable|email|max:255', 'grid_width' => 6],
                ['name' => 'phone', 'label' => 'Phone / Contact No', 'type' => 'text', 'validation' => 'nullable|string|max:50', 'grid_width' => 6],
                ['name' => 'gstin', 'label' => 'GSTIN Number (15 Digits)', 'type' => 'text', 'validation' => 'nullable|string|size:15', 'grid_width' => 6],
                ['name' => 'pan', 'label' => 'PAN Card No (10 Digits)', 'type' => 'text', 'validation' => 'nullable|string|size:10', 'grid_width' => 6],
                ['name' => 'company_id', 'label' => 'Select Company', 'type' => 'select', 'validation' => 'nullable|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'companies', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'branch_id', 'label' => 'Select Branch', 'type' => 'select', 'validation' => 'nullable|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'branches', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'address', 'label' => 'Corporate Office Address', 'type' => 'textarea', 'validation' => 'nullable|string', 'grid_width' => 12],
                ['name' => 'status', 'label' => 'Account Status', 'type' => 'select', 'validation' => 'required|string', 'grid_width' => 12, 'options' => [
                    ['value' => 'Active', 'label' => 'Active'],
                    ['value' => 'Inactive', 'label' => 'Inactive']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-truck',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Tax Masters CRUD Config
        $pageTaxId = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Tax Master (GST)',
            'slug' => 'tax-masters',
            'token' => 'TAX-100',
            'title' => 'GST Tax Rate Masters',
            'db_table' => 'tax_masters',
            'primary_key' => 'id',
            'sql_query' => 'SELECT * FROM tax_masters',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Tax Label', 'flex' => 1.8],
                ['field' => 'code', 'headerName' => 'Tax Code', 'flex' => 1.0],
                ['field' => 'rate', 'headerName' => 'Combined Rate (%)', 'flex' => 1.2],
                ['field' => 'cgst_rate', 'headerName' => 'CGST (%)', 'flex' => 1.0],
                ['field' => 'sgst_rate', 'headerName' => 'SGST (%)', 'flex' => 1.0],
                ['field' => 'igst_rate', 'headerName' => 'IGST (%)', 'flex' => 1.0],
                ['field' => 'is_active', 'headerName' => 'Active Status', 'flex' => 0.8]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Tax Name / Label', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique Tax Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:tax_masters,code', 'grid_width' => 6],
                ['name' => 'rate', 'label' => 'GST Combined Tax Percentage', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 6],
                ['name' => 'cgst_rate', 'label' => 'CGST Percentage (Central)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 4],
                ['name' => 'sgst_rate', 'label' => 'SGST Percentage (State)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 4],
                ['name' => 'igst_rate', 'label' => 'IGST Percentage (Integrated)', 'type' => 'number', 'validation' => 'required|numeric|min:0', 'grid_width' => 4],
                ['name' => 'is_active', 'label' => 'Tax Rule Enabled', 'type' => 'select', 'validation' => 'required|boolean', 'grid_width' => 12, 'options' => [
                    ['value' => 1, 'label' => 'Active'],
                    ['value' => 0, 'label' => 'Disabled']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-percent',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // UOM CRUD Config
        $pageUomId = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Units of Measure',
            'slug' => 'uoms',
            'token' => 'UOM-100',
            'title' => 'UOM Units Master',
            'db_table' => 'uoms',
            'primary_key' => 'id',
            'sql_query' => 'SELECT * FROM uoms',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'UOM Unit Name', 'flex' => 2.0],
                ['field' => 'code', 'headerName' => 'UOM Code', 'flex' => 1.2],
                ['field' => 'is_active', 'headerName' => 'Status', 'flex' => 1.0]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'UOM Unit Description', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique UOM Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:uoms,code', 'grid_width' => 6],
                ['name' => 'is_active', 'label' => 'UOM Enabled', 'type' => 'select', 'validation' => 'required|boolean', 'grid_width' => 6, 'options' => [
                    ['value' => 1, 'label' => 'Active'],
                    ['value' => 0, 'label' => 'Disabled']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-rulers',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Cost Centers CRUD Config
        $pageCostCenterId = DB::table('pages')->insertGetId([
            'module_id' => $modErp,
            'name' => 'Cost Centers',
            'slug' => 'cost-centers',
            'token' => 'CSTC-100',
            'title' => 'Cost Centers Directory',
            'db_table' => 'cost_centers',
            'primary_key' => 'id',
            'sql_query' => 'SELECT cost_centers.id, cost_centers.name, cost_centers.code, companies.name as company_name, cost_centers.is_active FROM cost_centers LEFT JOIN companies ON cost_centers.company_id = companies.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Cost Center Description', 'flex' => 1.8],
                ['field' => 'code', 'headerName' => 'Cost Center Code', 'flex' => 1.2],
                ['field' => 'company_name', 'headerName' => 'Company Associated', 'flex' => 1.8],
                ['field' => 'is_active', 'headerName' => 'Active', 'flex' => 0.8]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Cost Center Name', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'code', 'label' => 'Unique Cost Center Code', 'type' => 'text', 'validation' => 'required|string|max:50|unique:cost_centers,code', 'grid_width' => 6],
                ['name' => 'company_id', 'label' => 'Select Company Group', 'type' => 'select', 'validation' => 'nullable|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'companies', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'description', 'label' => 'Operational Description', 'type' => 'textarea', 'validation' => 'nullable|string', 'grid_width' => 12],
                ['name' => 'is_active', 'label' => 'Enabled Status', 'type' => 'select', 'validation' => 'required|boolean', 'grid_width' => 12, 'options' => [
                    ['value' => 1, 'label' => 'Active'],
                    ['value' => 0, 'label' => 'Disabled']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-cash-coin',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Workflows Config CRUD Config
        $pageWorkflowConfigId = DB::table('pages')->insertGetId([
            'module_id' => $modOrg,
            'name' => 'Workflows Config',
            'slug' => 'workflows',
            'token' => 'WF-100',
            'title' => 'Workflow Approval Chains',
            'db_table' => 'workflows',
            'primary_key' => 'id',
            'sql_query' => 'SELECT workflows.id, workflows.name, pages.name as page_name, workflows.is_active FROM workflows JOIN pages ON workflows.page_id = pages.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'name', 'headerName' => 'Workflow Chain Name', 'flex' => 1.8],
                ['field' => 'page_name', 'headerName' => 'Target ERP Page', 'flex' => 1.8],
                ['field' => 'is_active', 'headerName' => 'Status', 'flex' => 0.8]
            ]),
            'form_schema' => json_encode([
                ['name' => 'name', 'label' => 'Approval Chain Label', 'type' => 'text', 'validation' => 'required|string|max:255', 'grid_width' => 12],
                ['name' => 'page_id', 'label' => 'Target ERP Module Page', 'type' => 'select', 'validation' => 'required|integer', 'grid_width' => 6, 'options_source' => 'table', 'options_table' => 'pages', 'options_key' => 'id', 'options_value' => 'name'],
                ['name' => 'steps', 'label' => 'Workflow Steps (JSON format e.g. [{"step":1, "role_id":3, "name":"Manager Review"}])', 'type' => 'textarea', 'validation' => 'required|string', 'grid_width' => 12],
                ['name' => 'is_active', 'label' => 'Enabled Status', 'type' => 'select', 'validation' => 'required|boolean', 'grid_width' => 6, 'options' => [
                    ['value' => 1, 'label' => 'Active'],
                    ['value' => 0, 'label' => 'Disabled']
                ]]
            ]),
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-diagram-3-fill',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Audit Log Viewer CRUD Config (Read Only)
        $pageAuditLogId = DB::table('pages')->insertGetId([
            'module_id' => $modAnalytics,
            'name' => 'Audit Log Viewer',
            'slug' => 'audit-logs',
            'token' => 'AUDIT-100',
            'title' => 'Enterprise System Audit Trails',
            'db_table' => 'audit_logs',
            'primary_key' => 'id',
            'sql_query' => 'SELECT audit_logs.id, users.name as user_name, audit_logs.action, audit_logs.table_name, audit_logs.record_id, audit_logs.ip_address, audit_logs.created_at FROM audit_logs LEFT JOIN users ON audit_logs.user_id = users.id',
            'grid_schema' => json_encode([
                ['field' => 'id', 'headerName' => 'ID', 'flex' => 0.5],
                ['field' => 'user_name', 'headerName' => 'Employee / Actor', 'flex' => 1.8],
                ['field' => 'action', 'headerName' => 'Action Type', 'flex' => 1.0],
                ['field' => 'table_name', 'headerName' => 'Target Entity Table', 'flex' => 1.5],
                ['field' => 'record_id', 'headerName' => 'Record ID', 'flex' => 0.8],
                ['field' => 'ip_address', 'headerName' => 'IP Address', 'flex' => 1.2],
                ['field' => 'created_at', 'headerName' => 'Logged Date', 'flex' => 1.5]
            ]),
            'form_schema' => json_encode([]), // No form schema means view-only
            'is_custom' => false,
            'is_active' => true,
            'icon' => 'bi-shield-check',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 8. Seeding Permissions Matrix
        $pages = [
            $pageCustId, $pageInvId, $pageInvItem, $pageUserConfig, $pagePermMatrixId, 
            $pageNotifRoutingId, $pageBroadcastId, $pageInboxId, $pageContactsId, 
            $pageComposeId, $pageReportsId,
            $pageCompId, $pageBranchId, $pageDeptId, $pageVendorId, $pageTaxId, 
            $pageUomId, $pageCostCenterId, $pageWorkflowConfigId, $pageAuditLogId
        ];

        // Super Admin gets unrestricted bypass automatically.
        // Seed Admin permissions
        foreach ($pages as $p) {
            DB::table('role_permissions')->insert([
                'role_id' => $roleIds['admin'],
                'page_id' => $p,
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_export' => true,
                'can_print' => true,
                'can_approve' => true,
                'can_reject' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed Sales Head permissions (sees and modifies ERP core)
        foreach ($pages as $p) {
            $isRestricted = in_array($p, [$pagePermMatrixId, $pageNotifRoutingId, $pageBroadcastId, $pageWorkflowConfigId, $pageAuditLogId]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleIds['sales-head'],
                'page_id' => $p,
                'can_view' => $isRestricted ? false : true,
                'can_create' => $isRestricted ? false : true,
                'can_edit' => $isRestricted ? false : true,
                'can_delete' => false,
                'can_export' => true,
                'can_print' => true,
                'can_approve' => true,
                'can_reject' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed Sales Representative permissions (only Customers & Invoices)
        foreach ($pages as $p) {
            $hasAccess = in_array($p, [$pageCustId, $pageInvId, $pageInboxId, $pageContactsId, $pageComposeId, $pageReportsId, $pageVendorId, $pageTaxId, $pageUomId]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleIds['sales-rep'],
                'page_id' => $p,
                'can_view' => $hasAccess,
                'can_create' => $hasAccess,
                'can_edit' => $hasAccess,
                'can_delete' => false,
                'can_export' => $hasAccess,
                'can_print' => $hasAccess,
                'can_approve' => false,
                'can_reject' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed Accounts Head permissions
        foreach ($pages as $p) {
            $hasAccess = in_array($p, [$pageInvId, $pageInvItem, $pageInboxId, $pageContactsId, $pageComposeId, $pageReportsId, $pageVendorId, $pageTaxId, $pageUomId, $pageCostCenterId]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleIds['accounts-head'],
                'page_id' => $p,
                'can_view' => $hasAccess,
                'can_create' => $hasAccess,
                'can_edit' => $hasAccess,
                'can_delete' => false,
                'can_export' => true,
                'can_print' => true,
                'can_approve' => true,
                'can_reject' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed Accounts Member permissions
        foreach ($pages as $p) {
            $hasAccess = in_array($p, [$pageInvId, $pageInvItem, $pageInboxId, $pageContactsId, $pageComposeId, $pageReportsId, $pageVendorId, $pageTaxId, $pageUomId, $pageCostCenterId]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleIds['accounts-member'],
                'page_id' => $p,
                'can_view' => $hasAccess,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => true,
                'can_print' => true,
                'can_approve' => false,
                'can_reject' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed General User permissions
        foreach ($pages as $p) {
            $isRestricted = in_array($p, [$pagePermMatrixId, $pageUserConfig, $pageNotifRoutingId, $pageBroadcastId, $pageWorkflowConfigId, $pageAuditLogId]);
            DB::table('role_permissions')->insert([
                'role_id' => $roleIds['user'],
                'page_id' => $p,
                'can_view' => $isRestricted ? false : true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => true,
                'can_print' => true,
                'can_approve' => false,
                'can_reject' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 9. Workflows Seeding (Sales invoices approval workflow)
        $wfId = DB::table('workflows')->insertGetId([
            'name' => 'High Value Invoice Approval Chain',
            'page_id' => $pageInvId,
            'steps' => json_encode([
                ['step' => 1, 'role_id' => $roleIds['sales-head'], 'name' => 'Sales Head Review'],
                ['step' => 2, 'role_id' => $roleIds['accounts-head'], 'name' => 'Finance Head Approval']
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 10. Email Accounts (Gmail settings configured with user app password)
        $emailAccId = DB::table('email_accounts')->insertGetId([
            'user_id' => $cfoId,
            'email' => 'moinahmed5426@gmail.com',
            'display_name' => 'Moin Shadab',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_user' => 'moinahmed5426@gmail.com',
            'smtp_password' => encrypt('sbbcctqvmqlqwujt'),
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_user' => 'moinahmed5426@gmail.com',
            'imap_password' => encrypt('sbbcctqvmqlqwujt'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('email_account_users')->insert([
            'email_account_id' => $emailAccId,
            'user_id' => $cfoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 11. Email Templates & Signatures
        DB::table('email_templates')->insert([
            'user_id' => $cfoId,
            'name' => 'Invoice Approved Notification',
            'subject' => 'Sales Invoice #[InvoiceNo] Approved',
            'body' => '<p>Dear Customer,</p><p>We are pleased to inform you that your Sales Invoice #[InvoiceNo] for the amount of $[Amount] has been fully approved by our accounts department.</p><p>Please find the transaction receipt statement attached.</p><p>Regards,<br>Finance Division</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('email_signatures')->insert([
            'user_id' => $cfoId,
            'name' => 'Corporate Executive Sign',
            'content' => '<strong>Michael Chang</strong><br><span style="color: #64748b; font-size: 0.8rem;">Chief Financial Officer | MS ERP Group</span><br><span style="color: #64748b; font-size: 0.8rem;">Phone: +91 22 5550 1999</span>',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 12. Address Book Contacts Seeding
        $contact1Id = DB::table('email_contacts')->insertGetId([
            'user_id' => $cfoId,
            'name' => 'Sophia Martinez',
            'email' => 'sophia.martinez@mserp-client.com',
            'phone' => '+91 98200 12345',
            'company' => 'Martinez Ventures',
            'notes' => 'Acme corporate account coordinator.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 13. Sample Business Data (Seeded with India-focused fields and ownership assignments)
        // Customer 1: Owned by North Rep 1 (David). Visible to David and Sales Head (Sarah).
        $cust1Id = DB::table('customers')->insertGetId([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'assigned_user_id' => $northRep1Id,
            'is_restricted_subordinates' => false,
            'gstin' => '27AAAAA1111A1Z1',
            'pan' => 'AAAAA1111A',
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
            'business_type' => 'Private Limited',
            'name' => 'Reliance Retail Outlets',
            'email' => 'billing@rel-retail.in',
            'phone' => '+91 22 4567 8901',
            'address' => 'Reliance Corporate Park, Navi Mumbai, MH',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Customer 2: Owned by North Rep 2 (Amit). Visible to Amit and Sales Head (Sarah).
        $cust2Id = DB::table('customers')->insertGetId([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'assigned_user_id' => $northRep2Id,
            'is_restricted_subordinates' => false,
            'gstin' => '09BBBBB2222B1Z2',
            'pan' => 'BBBBB2222B',
            'state' => 'Uttar Pradesh',
            'city' => 'Noida',
            'business_type' => 'Private Limited',
            'name' => 'HCL Technologies Ltd',
            'email' => 'invoices@hcl.in',
            'phone' => '+91 120 456 7890',
            'address' => 'Sector 3, Noida, UP',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Customer 3: Owned by South Rep 1 (Karthik). Visible to Karthik and South Sales Head (Rajesh).
        $cust3Id = DB::table('customers')->insertGetId([
            'company_id' => $acmeId,
            'branch_id' => $blrBranchId,
            'assigned_user_id' => $southRep1Id,
            'is_restricted_subordinates' => false,
            'gstin' => '29CCCCC3333C1Z3',
            'pan' => 'CCCCC3333C',
            'state' => 'Karnataka',
            'city' => 'Bengaluru',
            'business_type' => 'Public Limited',
            'name' => 'Infosys Technologies',
            'email' => 'billing@infosys.com',
            'phone' => '+91 80 2852 0261',
            'address' => 'Electronic City, Bengaluru, KA',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Customer 4: Owned by North Sales Head (Sarah) directly, AND set to Restricted visibility.
        // Sarah can see it, but David (her subordinate rep) CANNOT see it because is_restricted_subordinates = true!
        $cust4Id = DB::table('customers')->insertGetId([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'assigned_user_id' => $northSalesHeadId,
            'is_restricted_subordinates' => true, // Hidden from David and Amit!
            'gstin' => '27DDDDD4444D1Z4',
            'pan' => 'DDDDD4444D',
            'state' => 'Maharashtra',
            'city' => 'Pune',
            'business_type' => 'Sole Proprietorship',
            'name' => 'Tata Consultancy Services',
            'email' => 'accounts@tcs.co.in',
            'phone' => '+91 20 6608 6000',
            'address' => 'Hinjewadi Phase 3, Pune, MH',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Customer 5: Owned by CFO (Michael Chang) directly, and restricted.
        $cust5Id = DB::table('customers')->insertGetId([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'assigned_user_id' => $cfoId,
            'is_restricted_subordinates' => true,
            'gstin' => '33EEEEE5555E1Z5',
            'pan' => 'EEEEE5555E',
            'state' => 'Tamil Nadu',
            'city' => 'Chennai',
            'business_type' => 'Partnership',
            'name' => 'MRF Tyres Corporation',
            'email' => 'mrf@mrf-india.in',
            'phone' => '+91 44 2829 2777',
            'address' => 'Greams Road, Chennai, TN',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sales Invoices
        $inv1Id = DB::table('sales_invoices')->insertGetId([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'invoice_no' => 'INV-2026-001',
            'customer_id' => $cust1Id,
            'invoice_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'amount' => 450000.00,
            'tax' => 50000.00,
            'discount' => 0.00,
            'total_amount' => 500000.00,
            'status' => 'Pending Approval', // workflow trigger
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_invoices')->insert([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'invoice_no' => 'INV-2026-002',
            'customer_id' => $cust2Id,
            'invoice_date' => '2026-06-03',
            'due_date' => '2026-07-03',
            'amount' => 120000.00,
            'tax' => 12000.00,
            'discount' => 5000.00,
            'total_amount' => 127000.00,
            'status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_invoices')->insert([
            'company_id' => $acmeId,
            'branch_id' => $blrBranchId,
            'invoice_no' => 'INV-2026-003',
            'customer_id' => $cust3Id,
            'invoice_date' => '2026-06-05',
            'due_date' => '2026-07-05',
            'amount' => 800000.00,
            'tax' => 160000.00,
            'discount' => 40000.00,
            'total_amount' => 920000.00,
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Inventory Items
        DB::table('inventory_items')->insert([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'item_code' => 'ITM001',
            'name' => 'Mechanical Wireless Keyboard',
            'category' => 'Hardware Assets',
            'qty_on_hand' => 150,
            'unit_price' => 850.00,
            'reorder_level' => 15,
            'status' => 'In Stock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_items')->insert([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'item_code' => 'ITM002',
            'name' => 'Wireless Ergonomic Mouse',
            'category' => 'Hardware Assets',
            'qty_on_hand' => 5,
            'unit_price' => 450.00,
            'reorder_level' => 10,
            'status' => 'Low Stock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 13.5 Missing Masters Seeding
        // Vendors
        DB::table('vendors')->insert([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'name' => 'Tata Steel Ltd',
            'code' => 'VND001',
            'email' => 'sales@tatasteel.com',
            'phone' => '+91 22 6665 8282',
            'address' => 'Jamshedpur, Jharkhand',
            'gstin' => '20AAAAA1111A1Z1',
            'pan' => 'AAAAA1111A',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendors')->insert([
            'company_id' => $acmeId,
            'branch_id' => $mumbaiBranchId,
            'name' => 'Larsen & Toubro',
            'code' => 'VND002',
            'email' => 'info@larsentoubro.com',
            'phone' => '+91 22 6705 9000',
            'address' => 'L&T House, Ballard Estate, Mumbai',
            'gstin' => '27BBBBB2222B1Z2',
            'pan' => 'BBBBB2222B',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tax Masters
        DB::table('tax_masters')->insert([
            'name' => 'GST 5%',
            'code' => 'GST-5',
            'rate' => 5.00,
            'cgst_rate' => 2.50,
            'sgst_rate' => 2.50,
            'igst_rate' => 5.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_masters')->insert([
            'name' => 'GST 12%',
            'code' => 'GST-12',
            'rate' => 12.00,
            'cgst_rate' => 6.00,
            'sgst_rate' => 6.00,
            'igst_rate' => 12.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_masters')->insert([
            'name' => 'GST 18%',
            'code' => 'GST-18',
            'rate' => 18.00,
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'igst_rate' => 18.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // UOMs
        DB::table('uoms')->insert([
            'name' => 'Pieces',
            'code' => 'PCS',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('uoms')->insert([
            'name' => 'Kilograms',
            'code' => 'KGS',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cost Centers
        DB::table('cost_centers')->insert([
            'company_id' => $acmeId,
            'name' => 'Mumbai Cost Center',
            'code' => 'CC-MUM',
            'description' => 'Cost center for Mumbai Operations',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cost_centers')->insert([
            'company_id' => $acmeId,
            'name' => 'Bengaluru Tech Center',
            'code' => 'CC-BLR',
            'description' => 'Cost center for Bangalore Tech operations',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 14. Trigger Workflow Instance for INV-2026-001 (assigned to Sales Head Sarah Jenkins review)
        $wiId = DB::table('workflow_instances')->insertGetId([
            'workflow_id' => $wfId,
            'table_name' => 'sales_invoices',
            'record_id' => $inv1Id,
            'current_step' => 1,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Audit log creation for INV-2026-001
        DB::table('audit_logs')->insert([
            'user_id' => $northRep1Id, // created by David
            'action' => 'CREATE',
            'table_name' => 'sales_invoices',
            'record_id' => $inv1Id,
            'new_values' => json_encode(['invoice_no' => 'INV-2026-001', 'total_amount' => 500000.00, 'status' => 'Pending Approval']),
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 Chrome/122.0.0.0',
            'created_at' => now()->subHours(2),
        ]);

        // Set up notification for Sales Head (Sarah)
        DB::table('notifications')->insert([
            'user_id' => $northSalesHeadId,
            'title' => 'Workflow Approval Required',
            'message' => "A new record in 'Sales Invoices' requires your approval.",
            'type' => 'WORKFLOW',
            'is_read' => false,
            'created_at' => now()->subHours(2),
        ]);

        // 15. Pre-Sync simulated emails to test Inbox out of the box
        $thread1Id = (string) Str::uuid();
        
        $email1Data = [
            'email_account_id' => $emailAccId,
            'message_id' => '<' . time() . '.1@mserp-client.com>',
            'thread_id' => $thread1Id,
            'from_address' => 'sophia.martinez@mserp-client.com',
            'from_name' => 'Sophia Martinez (Sales Director)',
            'to_address' => 'admin@mserp.com',
            'subject' => 'URGENT: Request for Sales Invoice Revision - #INV-2026-004',
            'date_sent' => now()->subHours(5),
            'folder' => 'INBOX',
            'is_read' => false,
            'is_starred' => true,
            'has_attachments' => false,
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ];

        $email2Data = [
            'email_account_id' => $emailAccId,
            'message_id' => '<' . time() . '.2@mserp-client.com>',
            'thread_id' => $thread1Id,
            'from_address' => 'admin@mserp.com',
            'from_name' => 'Michael Chang (CFO)',
            'to_address' => 'sophia.martinez@mserp-client.com',
            'subject' => 'RE: URGENT: Request for Sales Invoice Revision - #INV-2026-004',
            'date_sent' => now()->subHours(4),
            'folder' => 'SENT',
            'is_read' => true,
            'is_starred' => false,
            'has_attachments' => false,
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ];

        $body1Html = '<p>Hi Team,</p><p>We received the invoice #INV-2026-004 for the Q2 licenses, but noticed the discount code for 10% was not applied. Could you please correct the amount and send over an updated PDF?</p><p>Best regards,<br>Sophia Martinez</p>';
        $body1Text = "Hi Team,\n\nWe received the invoice #INV-2026-004 for the Q2 licenses, but noticed the discount code for 10% was not applied. Could you please correct the amount and send over an updated PDF?\n\nBest regards,\nSophia Martinez";

        $body2Html = '<p>Hi Sophia,</p><p>Thanks for raising this. I am looking into our discount logs and will direct the sales billing desk to compile a corrected invoice INV-2026-004-REV. Expect it shortly.</p><p>Regards,<br>Michael</p>';
        $body2Text = "Hi Sophia,\n\nThanks for raising this. I am looking into our discount logs and will direct the sales billing desk to compile a corrected invoice INV-2026-004-REV. Expect it shortly.\n\nRegards,\nMichael";

        if (Schema::hasColumn('emails', 'body_html')) {
            $email1Data['body_html'] = $body1Html;
            $email1Data['body_text'] = $body1Text;
            DB::table('emails')->insert($email1Data);

            $email2Data['body_html'] = $body2Html;
            $email2Data['body_text'] = $body2Text;
            DB::table('emails')->insert($email2Data);
        } else {
            // Ensure email_bodies exists
            if (!Schema::hasTable('email_bodies')) {
                Schema::create('email_bodies', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('email_id')->unique()->index();
                    $table->longText('body_html')->nullable();
                    $table->longText('body_text')->nullable();
                    $table->timestamps();
                });
            }
            
            $email1Id = DB::table('emails')->insertGetId($email1Data);
            DB::table('email_bodies')->insert([
                'email_id' => $email1Id,
                'body_html' => $body1Html,
                'body_text' => $body1Text,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ]);

            $email2Id = DB::table('emails')->insertGetId($email2Data);
            DB::table('email_bodies')->insert([
                'email_id' => $email2Id,
                'body_html' => $body2Html,
                'body_text' => $body2Text,
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4),
            ]);
        }
    }
}
