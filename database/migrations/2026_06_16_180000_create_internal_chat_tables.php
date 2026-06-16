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
        // 1. Chat groups (Channels)
        if (!Schema::hasTable('chat_groups')) {
            Schema::create('chat_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // 2. Chat group users
        if (!Schema::hasTable('chat_group_users')) {
            Schema::create('chat_group_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('chat_groups')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['group_id', 'user_id']);
            });
        }

        // 3. Chat messages (Direct or group)
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->foreignId('group_id')->nullable()->constrained('chat_groups')->onDelete('cascade');
                $table->text('message')->nullable();
                $table->foreignId('parent_message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();

                $table->index(['sender_id', 'recipient_id']);
                $table->index('group_id');
                $table->index('deleted_at');
            });
        }

        // 4. Chat attachments (with hash index for deduplication)
        if (!Schema::hasTable('chat_attachments')) {
            Schema::create('chat_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade');
                $table->string('filename');
                $table->string('file_path');
                $table->string('file_hash')->index(); // sha256 hash
                $table->integer('file_size');
                $table->string('mime_type')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. Chat DM rules (allowed direct messaging relations)
        if (!Schema::hasTable('chat_rules')) {
            Schema::create('chat_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('allowed_user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['user_id', 'allowed_user_id']);
            });
        }

        // 6. User toggle permission for soft deletes
        if (!Schema::hasColumn('users', 'can_delete_chats')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_delete_chats')->default(true)->after('can_send_to_anyone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'can_delete_chats')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('can_delete_chats');
            });
        }

        Schema::dropIfExists('chat_rules');
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_group_users');
        Schema::dropIfExists('chat_groups');
    }
};
