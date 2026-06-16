<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class ChatController extends Controller
{
    /**
     * Get channels and allowed direct message contacts.
     */
    public function getChannelsAndContacts()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $role = DB::table('roles')->where('id', $user->role_id)->first();
        $isSuperAdmin = $role && $role->slug === 'super-admin';

        // 1. Fetch Group Channels
        $groupsQuery = DB::table('chat_groups');
        if (!$isSuperAdmin) {
            $groupsQuery->join('chat_group_users', 'chat_groups.id', '=', 'chat_group_users.group_id')
                ->where('chat_group_users.user_id', $user->id);
        }
        $groups = $groupsQuery->select('chat_groups.*')->orderBy('chat_groups.name')->get();

        // 2. Fetch Allowed DM Contacts
        // Direct superiors, direct subordinates, department peers, custom chat rules, or super admins
        $allowedUserIds = [];

        if ($isSuperAdmin) {
            // Super Admin can DM anyone
            $allowedUserIds = DB::table('users')->where('id', '<>', $user->id)->pluck('id')->toArray();
        } else {
            // A. Direct superior (the person they report to)
            if ($user->reports_to_id) {
                $allowedUserIds[] = $user->reports_to_id;
            }

            // B. Direct subordinates (people reporting to them)
            $subordinates = DB::table('users')->where('reports_to_id', $user->id)->pluck('id')->toArray();
            $allowedUserIds = array_merge($allowedUserIds, $subordinates);

            // C. Department peers
            if ($user->department_id) {
                $peers = DB::table('users')
                    ->where('department_id', $user->department_id)
                    ->where('id', '<>', $user->id)
                    ->pluck('id')->toArray();
                $allowedUserIds = array_merge($allowedUserIds, $peers);
            }

            // D. Custom chat rules (where they are sender or recipient)
            $rules = DB::table('chat_rules')
                ->where('user_id', $user->id)
                ->orWhere('allowed_user_id', $user->id)
                ->get();
            foreach ($rules as $rule) {
                if ($rule->user_id == $user->id) {
                    $allowedUserIds[] = $rule->allowed_user_id;
                } else {
                    $allowedUserIds[] = $rule->user_id;
                }
            }

            // E. All Super Admins
            $superAdminRole = DB::table('roles')->where('slug', 'super-admin')->first();
            if ($superAdminRole) {
                $superAdmins = DB::table('users')
                    ->where('role_id', $superAdminRole->id)
                    ->where('id', '<>', $user->id)
                    ->pluck('id')->toArray();
                $allowedUserIds = array_merge($allowedUserIds, $superAdmins);
            }
        }

        $allowedUserIds = array_unique(array_filter($allowedUserIds));

        $contacts = [];
        if (!empty($allowedUserIds)) {
            $contacts = DB::table('users')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->whereIn('users.id', $allowedUserIds)
                ->where('users.is_active', true)
                ->select('users.id', 'users.name', 'users.email', 'roles.name as role_name', 'departments.name as department_name')
                ->orderBy('users.name')
                ->get();
        }

        // Return current user's profile details and lists
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_super_admin' => $isSuperAdmin,
                'can_delete_chats' => (bool)($user->can_delete_chats ?? true)
            ],
            'channels' => $groups,
            'contacts' => $contacts
        ]);
    }

    /**
     * Get chat messages for group or direct chat.
     */
    public function getMessages(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $groupId = $request->query('group_id');
        $recipientId = $request->query('recipient_id');

        $query = DB::table('chat_messages')
            ->join('users as sender', 'chat_messages.sender_id', '=', 'sender.id')
            ->leftJoin('roles', 'sender.role_id', '=', 'roles.id')
            ->whereNull('chat_messages.parent_message_id'); // load only main chat threads

        if ($groupId) {
            // Verify group access
            $role = DB::table('roles')->where('id', $user->role_id)->first();
            if ($role->slug !== 'super-admin') {
                $hasAccess = DB::table('chat_group_users')
                    ->where('group_id', $groupId)
                    ->where('user_id', $user->id)
                    ->exists();
                if (!$hasAccess) {
                    return response()->json(['error' => 'Access Denied'], 403);
                }
            }
            $query->where('chat_messages.group_id', $groupId);
        } elseif ($recipientId) {
            $query->where(function ($q) use ($user, $recipientId) {
                $q->where(function ($q1) use ($user, $recipientId) {
                    $q1->where('chat_messages.sender_id', $user->id)
                       ->where('chat_messages.recipient_id', $recipientId);
                })->orWhere(function ($q2) use ($user, $recipientId) {
                    $q2->where('chat_messages.sender_id', $recipientId)
                       ->where('chat_messages.recipient_id', $user->id);
                });
            });
        } else {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        $messages = $query->select(
            'chat_messages.*',
            'sender.name as sender_name',
            'roles.name as sender_role',
            DB::raw("CASE WHEN chat_messages.deleted_at IS NOT NULL THEN 1 ELSE 0 END as is_deleted")
        )
        ->orderBy('chat_messages.created_at', 'asc')
        ->limit(100)
        ->get();

        // Load attachments and count thread replies for each message
        foreach ($messages as $msg) {
            if ($msg->is_deleted) {
                $msg->message = 'This message was deleted.';
                $msg->attachments = [];
            } else {
                $msg->attachments = DB::table('chat_attachments')
                    ->where('message_id', $msg->id)
                    ->whereNull('deleted_at')
                    ->get();
            }

            // Get thread reply count
            $msg->reply_count = DB::table('chat_messages')
                ->where('parent_message_id', $msg->id)
                ->whereNull('deleted_at')
                ->count();
        }

        return response()->json($messages);
    }

    /**
     * Send a new message (with optional attachments & deduplication).
     */
    public function sendMessage(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string',
            'recipient_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'parent_message_id' => 'nullable|integer',
            'attachments.*' => 'nullable|file|max:10240' // max 10MB per file
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $groupId = $request->input('group_id');
        $recipientId = $request->input('recipient_id');
        $parentMessageId = $request->input('parent_message_id');
        $messageText = $request->input('message');

        if (!$messageText && !$request->hasFile('attachments')) {
            return response()->json(['error' => 'Cannot send an empty message.'], 400);
        }

        // Verify group access if sending to a group
        if ($groupId) {
            $role = DB::table('roles')->where('id', $user->role_id)->first();
            if ($role->slug !== 'super-admin') {
                $hasAccess = DB::table('chat_group_users')
                    ->where('group_id', $groupId)
                    ->where('user_id', $user->id)
                    ->exists();
                if (!$hasAccess) {
                    return response()->json(['error' => 'Group access denied.'], 403);
                }
            }
        }

        // Insert message
        $messageId = DB::table('chat_messages')->insertGetId([
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'group_id' => $groupId,
            'message' => $messageText,
            'parent_message_id' => $parentMessageId,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Process attachments with SHA-256 deduplication
        $savedAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = $file->getClientOriginalName();
                $tempPath = $file->getRealPath();
                $hash = hash_file('sha256', $tempPath);
                $fileSize = $file->getSize();
                $mimeType = $file->getMimeType();

                // Check for duplicate file hash in existing active attachments
                $duplicate = DB::table('chat_attachments')
                    ->where('file_hash', $hash)
                    ->whereNull('deleted_at')
                    ->first();

                if ($duplicate) {
                    // Reuse the existing file path (Deduplication!)
                    $filePath = $duplicate->file_path;
                } else {
                    // Store new file on disk
                    $path = $file->store('chat_attachments');
                    $filePath = '/storage/app/' . $path;
                }

                $attachmentId = DB::table('chat_attachments')->insertGetId([
                    'message_id' => $messageId,
                    'filename' => $filename,
                    'file_path' => $filePath,
                    'file_hash' => $hash,
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $savedAttachments[] = [
                    'id' => $attachmentId,
                    'filename' => $filename,
                    'file_path' => $filePath,
                    'mime_type' => $mimeType
                ];
            }
        }

        $senderRole = DB::table('roles')->where('id', $user->role_id)->value('name');

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $messageId,
                'sender_id' => $user->id,
                'sender_name' => $user->name,
                'sender_role' => $senderRole,
                'recipient_id' => $recipientId,
                'group_id' => $groupId,
                'message' => $messageText,
                'parent_message_id' => $parentMessageId,
                'attachments' => $savedAttachments,
                'reply_count' => 0,
                'created_at' => now()->toDateTimeString()
            ]
        ]);
    }

    /**
     * Soft-delete a chat message.
     */
    public function deleteMessage($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Check user toggle delete permission
        if (!($user->can_delete_chats ?? true)) {
            return response()->json(['error' => 'You do not have permission to delete chat messages.'], 403);
        }

        $message = DB::table('chat_messages')->where('id', $id)->first();
        if (!$message) {
            return response()->json(['error' => 'Message not found.'], 404);
        }

        $role = DB::table('roles')->where('id', $user->role_id)->first();
        $isSuperAdmin = $role && $role->slug === 'super-admin';

        // Message owner or super-admin can delete
        if ($message->sender_id != $user->id && !$isSuperAdmin) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        // Soft delete message
        DB::table('chat_messages')->where('id', $id)->update([
            'deleted_at' => now(),
            'updated_at' => now()
        ]);

        // Soft delete associated attachments
        DB::table('chat_attachments')->where('message_id', $id)->update([
            'deleted_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Message deleted successfully.']);
    }

    /**
     * Forward a message (replicates references, zero disk usage overhead).
     */
    public function forwardMessage(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'message_id' => 'required|integer',
            'recipient_id' => 'nullable|integer',
            'group_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $messageId = $request->input('message_id');
        $recipientId = $request->input('recipient_id');
        $groupId = $request->input('group_id');

        $original = DB::table('chat_messages')->where('id', $messageId)->whereNull('deleted_at')->first();
        if (!$original) {
            return response()->json(['error' => 'Message not found.'], 404);
        }

        // Insert forwarded message reference
        $newMessageId = DB::table('chat_messages')->insertGetId([
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'group_id' => $groupId,
            'message' => $original->message,
            'parent_message_id' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Fetch original attachments
        $attachments = DB::table('chat_attachments')
            ->where('message_id', $messageId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($attachments as $at) {
            // Replicate database attachments pointing to the same file path (Deduplication!)
            DB::table('chat_attachments')->insert([
                'message_id' => $newMessageId,
                'filename' => $at->filename,
                'file_path' => $at->file_path,
                'file_hash' => $at->file_hash,
                'file_size' => $at->file_size,
                'mime_type' => $at->mime_type,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Message forwarded successfully.']);
    }

    /**
     * Get thread replies for a message.
     */
    public function getThreadReplies($messageId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Fetch parent message
        $parent = DB::table('chat_messages')
            ->join('users as sender', 'chat_messages.sender_id', '=', 'sender.id')
            ->leftJoin('roles', 'sender.role_id', '=', 'roles.id')
            ->where('chat_messages.id', $messageId)
            ->select('chat_messages.*', 'sender.name as sender_name', 'roles.name as sender_role')
            ->first();

        if (!$parent) {
            return response()->json(['error' => 'Parent thread not found.'], 404);
        }

        if ($parent->deleted_at) {
            $parent->message = 'This message was deleted.';
            $parent->attachments = [];
        } else {
            $parent->attachments = DB::table('chat_attachments')
                ->where('message_id', $parent->id)
                ->whereNull('deleted_at')
                ->get();
        }

        // Fetch replies
        $replies = DB::table('chat_messages')
            ->join('users as sender', 'chat_messages.sender_id', '=', 'sender.id')
            ->leftJoin('roles', 'sender.role_id', '=', 'roles.id')
            ->where('chat_messages.parent_message_id', $messageId)
            ->select('chat_messages.*', 'sender.name as sender_name', 'roles.name as sender_role',
                DB::raw("CASE WHEN chat_messages.deleted_at IS NOT NULL THEN 1 ELSE 0 END as is_deleted"))
            ->orderBy('chat_messages.created_at', 'asc')
            ->get();

        foreach ($replies as $reply) {
            if ($reply->is_deleted) {
                $reply->message = 'This message was deleted.';
                $reply->attachments = [];
            } else {
                $reply->attachments = DB::table('chat_attachments')
                    ->where('message_id', $reply->id)
                    ->whereNull('deleted_at')
                    ->get();
            }
        }

        return response()->json([
            'parent' => $parent,
            'replies' => $replies
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Console Endpoint Handlers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new group channel.
     */
    public function createChannel(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'user_ids' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = $request->input('name');
        $userIds = $request->input('user_ids');
        $userIds[] = $user->id; // Always add creator to the group
        $userIds = array_unique($userIds);

        DB::beginTransaction();
        try {
            $groupId = DB::table('chat_groups')->insertGetId([
                'name' => $name,
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $groupUsers = [];
            foreach ($userIds as $uId) {
                $groupUsers[] = [
                    'group_id' => $groupId,
                    'user_id' => (int)$uId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            DB::table('chat_group_users')->insert($groupUsers);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Channel group created successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create channel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user directory configurations (For admin console).
     */
    public function getAdminDirectory()
    {
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if (!$role || $role->slug !== 'super-admin') {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        $users = DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->select('users.id', 'users.name', 'users.email', 'roles.name as role_name', 'users.can_delete_chats')
            ->where('users.is_active', true)
            ->get();

        $rules = DB::table('chat_rules')
            ->join('users as sender', 'chat_rules.user_id', '=', 'sender.id')
            ->join('users as recipient', 'chat_rules.allowed_user_id', '=', 'recipient.id')
            ->select('chat_rules.id', 'sender.name as sender_name', 'recipient.name as recipient_name')
            ->get();

        return response()->json([
            'users' => $users,
            'rules' => $rules
        ]);
    }

    /**
     * Add direct messaging rules mapping between users.
     */
    public function addChatRule(Request $request)
    {
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if (!$role || $role->slug !== 'super-admin') {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'allowed_user_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->input('user_id');
        $allowedUserId = $request->input('allowed_user_id');

        if ($userId == $allowedUserId) {
            return response()->json(['error' => 'Cannot create a mapping to self.'], 400);
        }

        DB::table('chat_rules')->insertOrIgnore([
            'user_id' => $userId,
            'allowed_user_id' => $allowedUserId,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Communication permission rule mapped successfully.']);
    }

    /**
     * Remove communication mapping rule.
     */
    public function removeChatRule($id)
    {
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if (!$role || $role->slug !== 'super-admin') {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        DB::table('chat_rules')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Permission rule revoked.']);
    }

    /**
     * Toggle soft delete permission.
     */
    public function toggleDeletePermission(Request $request)
    {
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if (!$role || $role->slug !== 'super-admin') {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'can_delete_chats' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetUserId = $request->input('user_id');
        $canDelete = $request->boolean('can_delete_chats');

        DB::table('users')->where('id', $targetUserId)->update([
            'can_delete_chats' => $canDelete ? 1 : 0,
            'updated_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Chat delete permission status updated.']);
    }
}
