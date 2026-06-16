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
        // 1. Broadcasts table
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('scope')->default('everyone'); // everyone, department, user
            $table->unsignedBigInteger('target_id')->nullable(); // department_id or user_id
            $table->timestamps();
        });

        // 2. Broadcast read receipts table
        Schema::create('broadcast_read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
        });

        // 3. Notification routing hierarchy table
        Schema::create('notification_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Unique routing pair to prevent duplicate definitions
            $table->unique(['sender_id', 'receiver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_routes');
        Schema::dropIfExists('broadcast_read_receipts');
        Schema::dropIfExists('broadcasts');
    }
};
