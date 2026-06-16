<?php

namespace App\Services;

use App\Repositories\DynamicCrudRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DynamicCrudService
{
    protected $repository;

    public function __construct(DynamicCrudRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get a page configuration by slug.
     */
    public function getPageConfig(string $slug)
    {
        return DB::table('pages')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get grid data with pagination, sorting, filters, and global search.
     */
    public function getGridData(object $pageConfig, array $params): array
    {
        $gridSchema = json_decode($pageConfig->grid_schema, true) ?? [];
        $formSchema = json_decode($pageConfig->form_schema, true) ?? [];

        // Extract column fields for search matching
        $schemaFields = [];
        foreach ($gridSchema as $col) {
            if (isset($col['field'])) {
                $schemaFields[] = $col['field'];
            }
        }
        if (empty($schemaFields)) {
            foreach ($formSchema as $field) {
                if (isset($field['name'])) {
                    $schemaFields[] = $field['name'];
                }
            }
        }

        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['perPage'] ?? 100);
        $sortField = $params['sortField'] ?? null;
        $sortOrder = $params['sortOrder'] ?? 'asc';
        $globalSearch = $params['search'] ?? null;

        // Parse filters
        $filters = [];
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'filter_') && $value !== null && $value !== '') {
                $field = substr($key, 7);
                $filters[$field] = $value;
            }
        }

        return $this->repository->getGridData(
            $pageConfig->db_table,
            $pageConfig->primary_key,
            $pageConfig->sql_query,
            $page,
            $perPage,
            $sortField,
            $sortOrder,
            $filters,
            $globalSearch,
            $schemaFields
        );
    }

    /**
     * Validate and create a new record.
     */
    public function createRecord(object $pageConfig, array $data, string $ip, string $userAgent): int
    {
        $validatedData = $this->validateData($pageConfig, $data);

        // Remove ID fields or password fields if they shouldn't be here, or encrypt passwords
        if (isset($validatedData['password'])) {
            $validatedData['password'] = bcrypt($validatedData['password']);
        }

        // Perform insert
        $recordId = $this->repository->insert($pageConfig->db_table, $validatedData);

        // Log audit trail
        $this->logAudit(
            Auth::id(),
            'CREATE',
            $pageConfig->db_table,
            $recordId,
            null,
            json_encode($validatedData),
            $ip,
            $userAgent
        );

        // Check if there is an active workflow for this page
        $this->checkAndTriggerWorkflow($pageConfig, $recordId);

        return $recordId;
    }

    /**
     * Validate and update an existing record.
     */
    public function updateRecord(object $pageConfig, $id, array $data, string $ip, string $userAgent): bool
    {
        $validatedData = $this->validateData($pageConfig, $data, $id);

        // Retrieve original record for audit logging
        $oldRecord = $this->repository->find($pageConfig->db_table, $pageConfig->primary_key, $id);

        // Don't update password if it's empty
        if (isset($validatedData['password'])) {
            if (empty($validatedData['password'])) {
                unset($validatedData['password']);
            } else {
                $validatedData['password'] = bcrypt($validatedData['password']);
            }
        }

        // Perform update
        $result = $this->repository->update($pageConfig->db_table, $pageConfig->primary_key, $id, $validatedData);

        if ($result) {
            // Log audit trail
            $this->logAudit(
                Auth::id(),
                'UPDATE',
                $pageConfig->db_table,
                $id,
                json_encode($oldRecord),
                json_encode($validatedData),
                $ip,
                $userAgent
            );
        }

        return $result;
    }

    /**
     * Delete a record.
     */
    public function deleteRecord(object $pageConfig, $id, string $ip, string $userAgent): bool
    {
        // Retrieve original record for audit logging
        $oldRecord = $this->repository->find($pageConfig->db_table, $pageConfig->primary_key, $id);

        // Perform delete
        $result = $this->repository->delete($pageConfig->db_table, $pageConfig->primary_key, $id);

        if ($result) {
            // Log audit trail
            $this->logAudit(
                Auth::id(),
                'DELETE',
                $pageConfig->db_table,
                $id,
                json_encode($oldRecord),
                null,
                $ip,
                $userAgent
            );
        }

        return $result;
    }

    /**
     * Validate request data based on page form schema.
     */
    protected function validateData(object $pageConfig, array $data, $id = null): array
    {
        $formSchema = json_decode($pageConfig->form_schema, true) ?? [];
        $rules = [];
        $attributes = [];

        foreach ($formSchema as $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) continue;

            $validation = $field['validation'] ?? '';
            
            // Adjust unique validation rules to ignore the current record during updates
            if ($id && str_contains($validation, 'unique:')) {
                // e.g. unique:sales_invoices,invoice_no -> unique:sales_invoices,invoice_no,{id}
                $validation = preg_replace('/unique:([^,]+),([^,$]+)/', "unique:$1,$2," . $id, $validation);
            }

            // For update, if password is empty it's fine
            if ($id && $fieldName === 'password' && str_contains($validation, 'required')) {
                $validation = str_replace('required', 'nullable', $validation);
            }

            if (!empty($validation)) {
                $rules[$fieldName] = $validation;
            }
            $attributes[$fieldName] = $field['label'] ?? $fieldName;
        }

        $validator = Validator::make($data, $rules, [], $attributes);
        $validator->validate();

        // Return only elements defined in form schema to protect database
        $filtered = [];
        foreach ($formSchema as $field) {
            $name = $field['name'] ?? null;
            if ($name && array_key_exists($name, $data)) {
                $filtered[$name] = $data[$name];
            }
        }

        return $filtered;
    }

    /**
     * Insert a record in the audit logs table.
     */
    protected function logAudit($userId, string $action, string $table, $recordId, ?string $oldValues, ?string $newValues, string $ip, string $userAgent): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * Check if a workflow is registered for the page and trigger it.
     */
    protected function checkAndTriggerWorkflow(object $pageConfig, $recordId): void
    {
        $workflow = DB::table('workflows')
            ->where('page_id', $pageConfig->id)
            ->where('is_active', true)
            ->first();

        if ($workflow) {
            // Create workflow instance
            $instanceId = DB::table('workflow_instances')->insertGetId([
                'workflow_id' => $workflow->id,
                'table_name' => $pageConfig->db_table,
                'record_id' => $recordId,
                'current_step' => 1,
                'status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Set physical table status to 'Pending Approval' if the status column exists
            if (Schema::hasColumn($pageConfig->db_table, 'status')) {
                DB::table($pageConfig->db_table)
                    ->where($pageConfig->primary_key, $recordId)
                    ->update(['status' => 'Pending Approval']);
            }

            // Create notification for users with the workflow step role
            $steps = json_decode($workflow->steps, true) ?? [];
            if (!empty($steps) && isset($steps[0]['role_id'])) {
                $roleId = $steps[0]['role_id'];
                $usersToNotify = DB::table('users')->where('role_id', $roleId)->get();

                foreach ($usersToNotify as $u) {
                    DB::table('notifications')->insert([
                        'user_id' => $u->id,
                        'title' => 'Workflow Approval Required',
                        'message' => "A new record in '{$pageConfig->name}' requires your approval.",
                        'type' => 'WORKFLOW',
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Generate dynamic CSV content for grid export.
     */
    public function exportGridData(object $pageConfig, array $params)
    {
        // Fetch matching data (un-paginated to export everything)
        $params['perPage'] = 10000;
        $params['page'] = 1;
        
        $gridData = $this->getGridData($pageConfig, $params);
        $records = $gridData['data'];

        $gridSchema = json_decode($pageConfig->grid_schema, true) ?? [];
        $headers = [];
        $fields = [];

        foreach ($gridSchema as $col) {
            if (isset($col['field'])) {
                $headers[] = $col['headerName'] ?? $col['field'];
                $fields[] = $col['field'];
            }
        }

        $callback = function() use ($records, $headers, $fields) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($records as $row) {
                $line = [];
                foreach ($fields as $field) {
                    $line[] = $row->{$field} ?? '';
                }
                fputcsv($file, $line);
            }
            fclose($file);
        };

        return $callback;
    }
}
