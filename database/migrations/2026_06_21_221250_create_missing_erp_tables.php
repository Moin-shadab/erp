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
        // 1. Vendors/Suppliers
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
                $table->string('name');
                $table->string('code')->unique();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('gstin', 15)->nullable();
                $table->string('pan', 10)->nullable();
                $table->string('status')->default('Active'); // Active, Inactive
                $table->timestamps();
            });
        }

        // 2. Tax Masters
        if (!Schema::hasTable('tax_masters')) {
            Schema::create('tax_masters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->decimal('rate', 5, 2);
                $table->decimal('cgst_rate', 5, 2)->default(0.00);
                $table->decimal('sgst_rate', 5, 2)->default(0.00);
                $table->decimal('igst_rate', 5, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. UOMs
        if (!Schema::hasTable('uoms')) {
            Schema::create('uoms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Cost Centers
        if (!Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 5. Email Account Users (Pivot for email accounts access control)
        if (!Schema::hasTable('email_account_users')) {
            Schema::create('email_account_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_account_id')->constrained('email_accounts')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['user_id', 'email_account_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_account_users');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('tax_masters');
        Schema::dropIfExists('vendors');
    }
};
