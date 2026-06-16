<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Companies
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // 2. Branches
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        // 3. Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        // 4. Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. Users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->foreignId('role_id')->constrained();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('reports_to_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // 6. Password Reset Tokens & Sessions
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 7. Low Code Metadata System: Modules
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->integer('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 8. Low Code Metadata System: Submodules
        Schema::create('submodules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('icon')->nullable();
            $table->integer('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 9. Low Code Metadata System: Pages
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->foreignId('submodule_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('token')->unique()->nullable();
            $table->string('title')->nullable();
            $table->string('db_table')->nullable();
            $table->string('primary_key')->default('id');
            $table->text('sql_query')->nullable();
            $table->longText('grid_schema')->nullable(); // JSON structure
            $table->longText('form_schema')->nullable(); // JSON structure
            $table->boolean('is_custom')->default(false);
            $table->string('custom_view')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 10. Role Permissions Matrix
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_reject')->default(false);
            $table->timestamps();
        });

        // 10.5 User Permissions Matrix
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_reject')->default(false);
            $table->timestamps();
        });

        // 11. Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action'); // CREATE, UPDATE, DELETE, LOGIN, LOGOUT, APPROVE, REJECT
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->longText('old_values')->nullable(); // JSON
            $table->longText('new_values')->nullable(); // JSON
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 12. Dynamic Workflows
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->longText('steps')->nullable(); // JSON Array e.g., [{"step":1, "role_id":2, "name":"Manager Approval"}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->integer('current_step')->default(1);
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->timestamps();
        });

        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('step_number');
            $table->string('action'); // APPROVE, REJECT
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 13. Dynamic Custom Reports
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('base_table');
            $table->text('sql_query')->nullable();
            $table->longText('columns')->nullable(); // JSON
            $table->longText('filters')->nullable(); // JSON
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // 14. System Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('SYSTEM'); // SYSTEM, WORKFLOW, EMAIL
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // 15. Email Accounts
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('email')->unique();
            $table->string('display_name');
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable(); // SSL, TLS, NONE
            $table->string('smtp_user')->nullable();
            $table->text('smtp_password')->nullable(); // encrypted
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->nullable();
            $table->string('imap_encryption')->nullable();
            $table->string('imap_user')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('pop3_host')->nullable();
            $table->integer('pop3_port')->nullable();
            $table->string('pop3_encryption')->nullable();
            $table->string('pop3_user')->nullable();
            $table->text('pop3_password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 16. Emails
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->string('message_id')->nullable()->index();
            $table->string('thread_id')->nullable()->index();
            $table->string('uid')->nullable()->index();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('to_address');
            $table->text('cc_address')->nullable();
            $table->text('bcc_address')->nullable();
            $table->text('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('date_sent')->nullable();
            $table->string('folder')->default('INBOX'); // INBOX, SENT, DRAFTS, TRASH, SPAM, ARCHIVE
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->timestamps();
        });

        // 17. Email Attachments
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // 18. Email Contacts
        Schema::create('email_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 19. Contact Groups
        Schema::create('email_contact_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });

        // 20. Contact Group Pivot
        Schema::create('email_contact_group_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('email_contacts')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('email_contact_groups')->onDelete('cascade');
        });

        // 21. Email Templates & Signatures
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('email_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('content')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 22. ERP Physical Data Tables (Targeted by metadata builder)
        // Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_restricted_subordinates')->default(false);
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('business_type', 100)->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('Active'); // Active, Inactive
            $table->timestamps();
        });

        // Invoices
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->string('invoice_no')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->decimal('tax', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->string('status')->default('Draft'); // Draft, Pending Approval, Approved, Paid, Void
            $table->timestamps();
        });

        // Inventory
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->string('item_code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->integer('qty_on_hand')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->integer('reorder_level')->default(10);
            $table->string('status')->default('In Stock'); // In Stock, Low Stock, Out of Stock
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('customers');
        
        Schema::dropIfExists('email_signatures');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_contact_group_pivot');
        Schema::dropIfExists('email_contact_groups');
        Schema::dropIfExists('email_contacts');
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('emails');
        Schema::dropIfExists('email_accounts');
        
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reports');
        
        Schema::dropIfExists('workflow_logs');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflows');
        
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('submodules');
        Schema::dropIfExists('modules');
        
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
