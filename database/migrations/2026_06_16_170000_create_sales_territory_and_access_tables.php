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
        // 1. Create areas table
        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->timestamps();
            });
        }

        // 2. Create area_sales_heads table (pivot)
        if (!Schema::hasTable('area_sales_heads')) {
            Schema::create('area_sales_heads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('area_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['area_id', 'user_id']);
            });
        }

        // 3. Create customer_shares table (pivot)
        if (!Schema::hasTable('customer_shares')) {
            Schema::create('customer_shares', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['customer_id', 'user_id']);
            });
        }

        // 4. Create data_access_grants table
        if (!Schema::hasTable('data_access_grants')) {
            Schema::create('data_access_grants', function (Blueprint $table) {
                $table->id();
                $table->string('accessor_type'); // 'department' or 'user'
                $table->unsignedBigInteger('accessor_id');
                $table->string('target_type'); // 'all', 'sales_head', or 'sales_rep'
                $table->unsignedBigInteger('target_id')->nullable();
                $table->timestamps();
                
                $table->index(['accessor_type', 'accessor_id']);
            });
        }

        // 5. Add area_id to users
        if (!Schema::hasColumn('users', 'area_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable()->after('reports_to_id')->constrained('areas')->onDelete('set null');
            });
        }

        // 6. Add manager_id to departments
        if (!Schema::hasColumn('departments', 'manager_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('manager_id')->nullable()->after('code')->constrained('users')->onDelete('set null');
            });
        }

        // 7. Add share_with_everyone to customers
        if (!Schema::hasColumn('customers', 'share_with_everyone')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('share_with_everyone')->default(false)->after('assigned_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customers', 'share_with_everyone')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('share_with_everyone');
            });
        }

        if (Schema::hasColumn('departments', 'manager_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropForeign(['manager_id']);
                $table->dropColumn('manager_id');
            });
        }

        if (Schema::hasColumn('users', 'area_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['area_id']);
                $table->dropColumn('area_id');
            });
        }

        Schema::dropIfExists('data_access_grants');
        Schema::dropIfExists('customer_shares');
        Schema::dropIfExists('area_sales_heads');
        Schema::dropIfExists('areas');
    }
};
