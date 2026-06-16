<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DynamicCrudRepository
{
    /**
     * Get paginated and filtered data from a table or a raw query.
     */
    public function getGridData(
        string $table,
        string $primaryKey = 'id',
        ?string $customQuery = null,
        int $page = 1,
        int $perPage = 100,
        ?string $sortField = null,
        ?string $sortOrder = 'asc',
        array $filters = [],
        ?string $globalSearch = null,
        array $schemaFields = []
    ): array {
        // Base query
        if ($customQuery) {
            // If custom query is provided, wrap it in a subquery to enable filtering/sorting/pagination easily
            $query = DB::table(DB::raw("({$customQuery}) as sub_query"));
        } else {
            $query = DB::table($table);
        }

        // Apply hierarchy-based row-level access control (RLS)
        if (\Illuminate\Support\Facades\Auth::check()) {
            $currentUser = \Illuminate\Support\Facades\Auth::user();
            $roleSlug = DB::table('roles')->where('id', $currentUser->role_id)->value('slug');
            
            if ($roleSlug !== 'super-admin' && $roleSlug !== 'admin') {
                if ($table === 'customers') {
                    $query->whereIn($primaryKey, function ($sub) use ($currentUser) {
                        $sub->select('id')->from('customers');
                        $this->applyCustomerRls($sub, $currentUser);
                    });
                } elseif ($table === 'sales_invoices') {
                    $query->whereIn('customer_id', function ($sub) use ($currentUser) {
                        $sub->select('id')->from('customers');
                        $this->applyCustomerRls($sub, $currentUser);
                    });
                } else {
                    // Check if other physical table has assigned_user_id for generic RLS
                    $hasAssignedUser = false;
                    try {
                        $hasAssignedUser = Schema::hasColumn($table, 'assigned_user_id');
                    } catch (\Exception $e) {}

                    if ($hasAssignedUser) {
                        $subordinateIds = $this->getSubordinateUserIds($currentUser->id);
                        $allowedUserIds = array_merge([$currentUser->id], $subordinateIds);

                        $query->whereIn($primaryKey, function ($sub) use ($table, $allowedUserIds, $currentUser) {
                            $sub->select('id')->from($table)
                                ->where(function ($q) use ($allowedUserIds, $currentUser) {
                                    $q->where('assigned_user_id', $currentUser->id)
                                      ->orWhere(function ($subQ) use ($allowedUserIds) {
                                          $subQ->whereIn('assigned_user_id', $allowedUserIds)
                                               ->where('is_restricted_subordinates', false);
                                      });
                                });
                        });
                    }
                }
            }
        }

        // Apply filters
        foreach ($filters as $field => $val) {
            if ($val !== null && $val !== '') {
                // If it's a date or partial text match
                if (is_string($val) && !is_numeric($val)) {
                    $query->where($field, 'like', '%' . $val . '%');
                } else {
                    $query->where($field, $val);
                }
            }
        }

        // Apply global search if search term is provided
        if (!empty($globalSearch) && !empty($schemaFields)) {
            $query->where(function ($q) use ($globalSearch, $schemaFields) {
                foreach ($schemaFields as $field) {
                    $q->orWhere($field, 'like', '%' . $globalSearch . '%');
                }
            });
        }

        // Count total matching records
        $totalCount = $query->count();

        // Apply sorting
        if (!empty($sortField)) {
            // Secure the sort field to prevent SQL injection by allowing only alphanumeric, underscores, and dots.
            $sortFieldSanitized = preg_replace('/[^a-zA-Z0-9_.]/', '', $sortField);
            $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
            if (!empty($sortFieldSanitized)) {
                $query->orderBy($sortFieldSanitized, $sortOrder);
            } else {
                $query->orderBy($primaryKey, 'desc');
            }
        } else {
            $query->orderBy($primaryKey, 'desc');
        }

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $data = $query->skip($offset)->take($perPage)->get();

        return [
            'data' => $data,
            'total' => $totalCount,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Find a record by ID.
     */
    public function find(string $table, string $primaryKey, $id)
    {
        return DB::table($table)->where($primaryKey, $id)->first();
    }

    /**
     * Insert a record into a table.
     */
    public function insert(string $table, array $data): int
    {
        // Add default timestamps if they exist on the physical table
        if (Schema::hasColumn($table, 'created_at')) {
            $data['created_at'] = now();
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $data['updated_at'] = now();
        }

        return DB::table($table)->insertGetId($data);
    }

    /**
     * Update a record by ID.
     */
    public function update(string $table, string $primaryKey, $id, array $data): bool
    {
        if (Schema::hasColumn($table, 'updated_at')) {
            $data['updated_at'] = now();
        }

        return DB::table($table)->where($primaryKey, $id)->update($data) >= 0;
    }

    /**
     * Delete a record by ID.
     */
    public function delete(string $table, string $primaryKey, $id): bool
    {
        return DB::table($table)->where($primaryKey, $id)->delete() > 0;
    }

    /**
     * Execute a raw SQL query.
     */
    public function selectRaw(string $sql, array $bindings = []): array
    {
        return DB::select($sql, $bindings);
    }

    /**
     * Recursively fetch all subordinate user IDs reporting to a user.
     */
    public function getSubordinateUserIds(int $userId): array
    {
        $subordinates = DB::table('users')->where('reports_to_id', $userId)->pluck('id')->toArray();
        $allSubordinates = $subordinates;
        
        foreach ($subordinates as $subId) {
            $allSubordinates = array_merge($allSubordinates, $this->getSubordinateUserIds($subId));
        }
        
        return array_unique($allSubordinates);
    }

    /**
     * Enforce RLS queries on customers table.
     */
    protected function applyCustomerRls($query, $currentUser)
    {
        $roleSlug = DB::table('roles')->where('id', $currentUser->role_id)->value('slug');
        if ($roleSlug === 'super-admin' || $roleSlug === 'admin') {
            return;
        }

        // 1. Check data access grants for the user or their department
        $grants = DB::table('data_access_grants')
            ->where(function ($q) use ($currentUser) {
                $q->where('accessor_type', 'user')
                  ->where('accessor_id', $currentUser->id);
                if ($currentUser->department_id) {
                    $q->orWhere(function ($orQ) use ($currentUser) {
                        $orQ->where('accessor_type', 'department')
                            ->where('accessor_id', $currentUser->department_id);
                    });
                }
            })
            ->get();

        foreach ($grants as $grant) {
            if ($grant->target_type === 'all') {
                return; // Has overall access
            }
        }

        // Gather allowed user IDs from target grants
        $grantedUserIds = [];
        foreach ($grants as $grant) {
            if ($grant->target_type === 'sales_rep' && $grant->target_id) {
                $grantedUserIds[] = $grant->target_id;
            } elseif ($grant->target_type === 'sales_head' && $grant->target_id) {
                $grantedUserIds[] = $grant->target_id;
                $grantedUserIds = array_merge($grantedUserIds, $this->getSubordinateUserIds($grant->target_id));
            }
        }
        $grantedUserIds = array_unique($grantedUserIds);

        // Get own subordinate IDs
        $subordinateIds = $this->getSubordinateUserIds($currentUser->id);

        $query->where(function ($q) use ($currentUser, $subordinateIds, $grantedUserIds) {
            // Own customers
            $q->where('assigned_user_id', $currentUser->id)
              // Or shared with everyone
              ->orWhere('share_with_everyone', 1)
              // Or shared with me specifically
              ->orWhereIn('id', function ($sub) use ($currentUser) {
                  $sub->select('customer_id')->from('customer_shares')->where('user_id', $currentUser->id);
              });

            // Or subordinate customers (respecting restriction flag)
            if (!empty($subordinateIds)) {
                $q->orWhere(function ($subQ) use ($subordinateIds) {
                    $subQ->whereIn('assigned_user_id', $subordinateIds)
                         ->where('is_restricted_subordinates', false);
                });
            }

            // Or customers of users granted via data_access_grants
            if (!empty($grantedUserIds)) {
                $q->orWhereIn('assigned_user_id', $grantedUserIds);
            }
        });
    }
}
