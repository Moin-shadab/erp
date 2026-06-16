<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    /**
     * Send a notification to all active mapped receivers of the sender.
     */
    public static function send($senderId, $title, $message, $type = 'SYSTEM')
    {
        $receivers = DB::table('notification_routes')
            ->where('sender_id', $senderId)
            ->where('is_active', true)
            ->pluck('receiver_id');

        foreach ($receivers as $receiverId) {
            self::sendDirect($receiverId, $title, $message, $type);
        }
    }

    /**
     * Send a direct notification to a specific user.
     */
    public static function sendDirect($receiverId, $title, $message, $type = 'SYSTEM')
    {
        DB::table('notifications')->insert([
            'user_id' => $receiverId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Retrieve any pending active broadcast for the authenticated user.
     */
    public static function getPendingBroadcast($user)
    {
        if (!$user) {
            return null;
        }

        return DB::table('broadcasts')
            ->where(function ($query) use ($user) {
                $query->where('scope', 'everyone')
                      ->orWhere(function ($q) use ($user) {
                          if ($user->department_id) {
                              $q->where('scope', 'department')
                                ->where('target_id', $user->department_id);
                          }
                      })
                      ->orWhere(function ($q) use ($user) {
                          $q->where('scope', 'user')
                            ->where('target_id', $user->id);
                      });
            })
            ->whereNotExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                      ->from('broadcast_read_receipts')
                      ->whereColumn('broadcast_read_receipts.broadcast_id', 'broadcasts.id')
                      ->where('broadcast_read_receipts.user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
