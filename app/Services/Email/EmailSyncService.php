<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailSyncService
{
    /**
     * Decrypt value securely; fall back to raw value if it is not encrypted.
     */
    public function safeDecrypt($value): string
    {
        try {
            return decrypt($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Sync an email account.
     */
    public function syncAccount(int $accountId): array
    {
        $account = DB::table('email_accounts')->where('id', $accountId)->first();
        if (!$account) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        // Check if credentials are placeholders or if host is local/empty
        $isPlaceholder = empty($account->imap_host) || 
                         str_contains($account->imap_host, 'example.com') || 
                         str_contains($account->imap_user, 'example') ||
                         $account->imap_host === 'localhost';

        if ($isPlaceholder) {
            return $this->runSimulation($account);
        }

        try {
            // Attempt to connect via IMAP socket client
            $imap = new ImapSocketClient(
                $account->imap_host,
                $account->imap_port ?? 993,
                $account->imap_encryption ?? 'ssl',
                $account->imap_user,
                $this->safeDecrypt($account->imap_password)
            );

            if (!$imap->login()) {
                Log::warning("Socket connection failed for {$account->email}.");
                return [
                    'success' => false,
                    'message' => 'IMAP login failed. Please verify your email credentials or app password.',
                    'logs' => $imap->getLogs()
                ];
            }

            $select = $imap->selectFolder('INBOX');
            if (!$select['ok']) {
                $imap->disconnect();
                return ['success' => false, 'message' => 'Failed to select INBOX.'];
            }

            // Determine search criteria based on last sync time
            $criteria = 'ALL';
            if (isset($account->last_sync_at) && !empty($account->last_sync_at)) {
                // Fetch emails since last sync time minus 1 day to catch boundaries
                $sinceDate = \Carbon\Carbon::parse($account->last_sync_at)->subDay()->format('d-M-Y');
                $criteria = 'SINCE "' . $sinceDate . '"';
            } else {
                // Fetch emails since 15 days ago for new account
                $sinceDate = \Carbon\Carbon::now()->subDays(15)->format('d-M-Y');
                $criteria = 'SINCE "' . $sinceDate . '"';
            }

            $uids = $imap->searchUids($criteria);
            rsort($uids); // Newest UIDs first
            
            $syncedCount = 0;
            $newUids = [];

            // Identify UIDs we don't have yet (optimized single DB query + O(1) lookup)
            $existingUids = DB::table('emails')
                ->where('email_account_id', $accountId)
                ->whereNotNull('uid')
                ->pluck('uid')
                ->toArray();
            $existingUids = array_filter($existingUids, function($v) {
                return (is_string($v) || is_int($v)) && $v !== '';
            });
            $existingUidsMap = array_flip($existingUids);

            foreach ($uids as $uid) {
                if (!isset($existingUidsMap[$uid])) {
                    $newUids[] = $uid;
                }
            }

            // Fetch overviews in batches to optimize performance (e.g. 100 at a time)
            $newUids = array_slice($newUids, 0, 100); // Sync max 100 new emails per sync
            if (!empty($newUids)) {
                $overviews = $imap->fetchOverviews($newUids);
                
                foreach ($newUids as $uid) {
                    if (!isset($overviews[$uid])) continue;
                    $ov = $overviews[$uid];

                    // Check duplicate by message_id in the target folder
                    $messageId = $ov['message_id'];
                    if (!empty($messageId)) {
                        $existingEmail = DB::table('emails')
                            ->where('email_account_id', $accountId)
                            ->where('message_id', $messageId)
                            ->where('folder', 'INBOX')
                            ->first();
                        
                        if ($existingEmail) {
                            // Update UID if it changed (e.g. moved folder) and continue
                            DB::table('emails')
                                ->where('id', $existingEmail->id)
                                ->update([
                                    'uid' => $uid,
                                    'updated_at' => now()
                                ]);
                            continue;
                        }
                    }

                    // Parse date_sent safely
                    $dateStr = $ov['date_sent'];
                    $ts = $dateStr ? strtotime($dateStr) : false;
                    $dateSent = ($ts !== false && $ts > 0) ? date('Y-m-d H:i:s', $ts) : now();

                    // Insert lightweight header-only email record (lazy-load body later)
                    $cleanSubject = preg_replace('/^(Re|Fwd):\s*/i', '', $ov['subject']);
                    $threadId = DB::table('emails')
                        ->where('email_account_id', $accountId)
                        ->where('subject', 'like', '%' . $cleanSubject . '%')
                        ->value('thread_id') ?: (string) Str::uuid();

                    DB::table('emails')->insert([
                        'email_account_id' => $accountId,
                        'message_id' => $messageId ?: ('<' . time() . '.' . uniqid() . '@mserp.local>'),
                        'thread_id' => $threadId,
                        'uid' => $uid,
                        'from_address' => $ov['from_address'],
                        'from_name' => $ov['from_name'],
                        'to_address' => $ov['to_address'],
                        'cc_address' => $ov['cc_address'],
                        'bcc_address' => $ov['bcc_address'],
                        'subject' => $ov['subject'],
                        'date_sent' => $dateSent,
                        'folder' => 'INBOX',
                        'is_read' => $ov['is_read'],
                        'is_starred' => $ov['is_starred'],
                        'has_attachments' => false, // Will determine during lazy load
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $assignedUserIds = DB::table('email_account_users')
                        ->where('email_account_id', $account->id)
                        ->pluck('user_id');
                    foreach ($assignedUserIds as $uId) {
                        $this->autoCreateContact($uId, $ov['from_address'], $ov['from_name']);
                    }
                    $syncedCount++;
                }
            }

            // Update last_sync_at timestamp in email_accounts
            DB::table('email_accounts')->where('id', $accountId)->update([
                'last_sync_at' => now(),
                'updated_at' => now()
            ]);

            $imap->disconnect();
            return ['success' => true, 'message' => "Successfully synced {$syncedCount} new emails."];

        } catch (\Exception $e) {
            Log::error("IMAP sync error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'IMAP sync failed: ' . $e->getMessage(),
                'logs' => isset($imap) ? $imap->getLogs() : [$e->getMessage()]
            ];
        }
    }

    /**
     * Save parsed email to database, handling attachments and contacts creation.
     * Supports both new insertion and lazy-loading update for existing emails.
     */
    public function saveParsedEmail(object $account, $parsedOrRaw, ?string $uid = null, string $folder = 'INBOX', ?int $existingEmailId = null): int
    {
        if (is_string($parsedOrRaw)) {
            // It's a raw MIME string from live IMAP sync
            $rawMime = $parsedOrRaw;
            $parsedMime = MimeParser::parse($rawMime);
            $headers = $parsedMime['headers'];

            $subject = MimeParser::decodeHeader($headers['subject'] ?? '');
            $from = MimeParser::decodeHeader($headers['from'] ?? '');
            $to = MimeParser::decodeHeader($headers['to'] ?? '');
            $cc = MimeParser::decodeHeader($headers['cc'] ?? null);
            $bcc = MimeParser::decodeHeader($headers['bcc'] ?? null);
            
            $messageId = trim($headers['message-id'] ?? '', ' <>');
            if (empty($messageId)) {
                $messageId = '<' . time() . '.' . uniqid() . '@mserp.local>';
            }

            $fromAddress = MimeParser::extractEmail($from);
            $fromName = MimeParser::extractName($from);

            $dateStr = $headers['date'] ?? '';
            $ts = $dateStr ? strtotime($dateStr) : false;
            $dateSent = ($ts !== false && $ts > 0) ? date('Y-m-d H:i:s', $ts) : now();

            $inReplyTo = isset($headers['in-reply-to']) ? trim($headers['in-reply-to'], ' <>') : null;
            $references = $headers['references'] ?? null;

            $flatted = MimeParser::flatten($parsedMime);
            $bodyHtml = $flatted['html'];
            $bodyText = $flatted['text'];
            
            if (empty($bodyHtml) && !empty($bodyText)) {
                $bodyHtml = nl2br(e($bodyText));
            }
            
            $attachments = [];
            foreach ($flatted['inlines'] as $cid => $b64Data) {
                if (str_starts_with($cid, 'type:')) continue;
                $mimeType = $flatted['inlines']['type:' . $cid] ?? 'image/jpeg';
                $attachments[] = [
                    'filename' => $cid,
                    'mime_type' => $mimeType,
                    'content' => base64_decode($b64Data),
                    'content_id' => $cid,
                    'is_inline' => true,
                ];
            }
            foreach ($flatted['attachments'] as $att) {
                $attachments[] = [
                    'filename' => $att['filename'],
                    'mime_type' => $att['mime'],
                    'content' => base64_decode($att['data']),
                    'content_id' => null,
                    'is_inline' => false,
                ];
            }
        } else {
            // It's simulated mock email array
            $parsed = $parsedOrRaw;
            $subject = $parsed['subject'] ?? '';
            $fromAddress = $parsed['from_address'] ?? '';
            $fromName = $parsed['from_name'] ?? null;
            $to = $parsed['to_address'] ?? '';
            $cc = $parsed['cc_address'] ?? null;
            $bcc = $parsed['bcc_address'] ?? null;
            $messageId = $parsed['message_id'] ?? null;
            $dateSent = $parsed['date_sent'] ?? now()->format('Y-m-d H:i:s');
            $bodyHtml = $parsed['body_html'] ?? null;
            $bodyText = $parsed['body_text'] ?? null;
            $inReplyTo = $parsed['in_reply_to'] ?? null;
            $references = $parsed['references'] ?? null;
            
            $attachments = [];
            if (!empty($parsed['attachments'])) {
                foreach ($parsed['attachments'] as $att) {
                    $attachments[] = [
                        'filename' => $att['filename'],
                        'mime_type' => $att['mime_type'],
                        'content' => $att['content'],
                        'content_id' => null,
                        'is_inline' => false,
                    ];
                }
            }
        }

        $inReplyToClean = $inReplyTo ? trim($inReplyTo, ' <>') : null;
        $referencesClean = $references ? trim($references) : null;

        $threadId = null;
        if ($existingEmailId) {
            $existingEmail = DB::table('emails')->where('id', $existingEmailId)->first();
            $threadId = $existingEmail ? $existingEmail->thread_id : null;
        }

        if (empty($threadId)) {
            // 1. Try In-Reply-To
            if (!empty($inReplyToClean)) {
                $parent = DB::table('emails')
                    ->where('email_account_id', $account->id)
                    ->where('message_id', $inReplyToClean)
                    ->first();
                if ($parent) {
                    $threadId = $parent->thread_id;
                }
            }

            // 2. Try References
            if (empty($threadId) && !empty($referencesClean)) {
                $refIds = preg_split('/\s+/', $referencesClean);
                $refIdsClean = array_filter(array_map(function($id) {
                    return trim($id, ' <>');
                }, $refIds));

                if (!empty($refIdsClean)) {
                    $referencedEmail = DB::table('emails')
                        ->where('email_account_id', $account->id)
                        ->whereIn('message_id', $refIdsClean)
                        ->first();
                    if ($referencedEmail) {
                        $threadId = $referencedEmail->thread_id;
                    }
                }
            }

            // 3. Try Subject-based matching
            if (empty($threadId)) {
                $cleanSubject = preg_replace('/^(Re|Fwd):\s*/i', '', $subject);
                $threadId = DB::table('emails')
                    ->where('email_account_id', $account->id)
                    ->where('subject', 'like', '%' . $cleanSubject . '%')
                    ->value('thread_id');
            }

            // 4. Default to new UUID
            if (empty($threadId)) {
                $threadId = (string) Str::uuid();
            }
        }

        if ($existingEmailId) {
            // Lazy loading update: update body fields and attachments flag
            DB::table('emails')->where('id', $existingEmailId)->update([
                'has_attachments' => !empty($attachments),
                'updated_at' => now(),
            ]);
            $emailId = $existingEmailId;
        } else {
            // Deduplication check: check if email already exists by message_id in this folder
            $existing = null;
            if (!empty($messageId)) {
                $existing = DB::table('emails')
                    ->where('email_account_id', $account->id)
                    ->where('message_id', $messageId)
                    ->where('folder', $folder)
                    ->first();
            }

            if ($existing) {
                // Update UID and folder
                $updateData = [
                    'uid' => $uid ?: $existing->uid,
                    'folder' => $folder ?: $existing->folder,
                    'in_reply_to' => $inReplyToClean,
                    'references' => $referencesClean,
                    'updated_at' => now(),
                ];
                DB::table('emails')->where('id', $existing->id)->update($updateData);
                $emailId = $existing->id;
            } else {
                // Insert email
                $emailId = DB::table('emails')->insertGetId([
                    'email_account_id' => $account->id,
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyToClean,
                    'references' => $referencesClean,
                    'thread_id' => $threadId,
                    'uid' => $uid,
                    'from_address' => $fromAddress,
                    'from_name' => $fromName ?: $fromAddress,
                    'to_address' => $to,
                    'cc_address' => $cc,
                    'bcc_address' => $bcc,
                    'subject' => $subject ?: '(No Subject)',
                    'date_sent' => $dateSent,
                    'folder' => $folder,
                    'is_read' => ($folder === 'SENT' || $folder === 'DRAFTS') ? true : false,
                    'is_starred' => false,
                    'has_attachments' => !empty($attachments),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Save body to email_bodies
        if ($bodyHtml !== null || $bodyText !== null) {
            $bodyExists = DB::table('email_bodies')->where('email_id', $emailId)->exists();
            if ($bodyExists) {
                DB::table('email_bodies')->where('email_id', $emailId)->update([
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                    'updated_at' => now()
                ]);
            } else {
                DB::table('email_bodies')->insert([
                    'email_id' => $emailId,
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        $hasCidRewrites = false;

        // Save attachments
        foreach ($attachments as $att) {
            $fileName = $att['filename'];
            $fileContent = $att['content'];
            $contentId = $att['content_id'];
            $isInline = $att['is_inline'];
            $fileSize = strlen($fileContent);

            // Deduplication check: check if attachment already exists for this email
            $existingAtt = DB::table('email_attachments')
                ->where('email_id', $emailId)
                ->where('filename', $fileName)
                ->where('file_size', $fileSize)
                ->first();

            if ($existingAtt) {
                // If it already exists, rewrite CID in html if needed but don't insert/write again
                if ($contentId && !empty($bodyHtml)) {
                    $cidPattern = 'cid:' . preg_quote($contentId, '/');
                    $newUrl = '/api/email/attachment/' . $existingAtt->id . '?inline=1';
                    $rewritten = preg_replace('/' . $cidPattern . '/i', $newUrl, $bodyHtml, -1, $count);
                    if ($count > 0) {
                        $bodyHtml = $rewritten;
                        $hasCidRewrites = true;
                    }
                }
                continue;
            }

            // Save using CAS
            $attId = $this->saveAttachmentCas(
                $fileContent,
                $fileName,
                $att['mime_type'] ?? null,
                $dateSent,
                $messageId,
                $emailId,
                $contentId,
                $isInline
            );

            if ($contentId && !empty($bodyHtml)) {
                $cidPattern = 'cid:' . preg_quote($contentId, '/');
                $newUrl = '/api/email/attachment/' . $attId . '?inline=1';
                $rewritten = preg_replace('/' . $cidPattern . '/i', $newUrl, $bodyHtml, -1, $count);
                if ($count > 0) {
                    $bodyHtml = $rewritten;
                    $hasCidRewrites = true;
                }
            }
        }

        if ($hasCidRewrites) {
            DB::table('email_bodies')->where('email_id', $emailId)->update([
                'body_html' => $bodyHtml,
                'updated_at' => now()
            ]);
        }

        // Auto-create contact for all assigned users if it doesn't exist
        $assignedUserIds = DB::table('email_account_users')
            ->where('email_account_id', $account->id)
            ->pluck('user_id');
        foreach ($assignedUserIds as $uId) {
            $this->autoCreateContact($uId, $fromAddress, $fromName ?: '');
        }

        return $emailId;
    }

    /**
     * Save file content using Content-Addressable Storage (CAS) to optimize disk space,
     * and link it to the requested human-readable path.
     */
    public function saveAttachmentCas(
        string $fileContent,
        string $fileName,
        ?string $mimeType,
        ?string $dateSent,
        string $messageId,
        int $emailId,
        ?string $contentId = null,
        bool $isInline = false
    ): int {
        $sha256 = hash('sha256', $fileContent);
        $fileSize = strlen($fileContent);

        $casDir = "email_attachments/cas";
        $casPath = "{$casDir}/{$sha256}";

        if (!Storage::exists($casPath)) {
            Storage::put($casPath, $fileContent);
        }

        $emailRecord = DB::table('emails')->where('id', $emailId)->first();
        $accountId = $emailRecord ? $emailRecord->email_account_id : 0;
        $emailAccount = DB::table('email_accounts')->where('id', $accountId)->first();
        $accountEmailDir = $emailAccount ? preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', $emailAccount->email) : 'default';

        $cleanMsgId = trim($messageId, ' <>');
        $cleanMsgId = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', $cleanMsgId);
        $messageIdHash = md5($messageId);

        $ts = $dateSent ? strtotime($dateSent) : time();
        $year = date('Y', $ts);
        $month = date('m', $ts);
        $day = date('d', $ts);

        $safeFileName = basename($fileName);
        if (empty($safeFileName)) {
            $safeFileName = 'attachment_' . uniqid();
        }

        $readablePath = "email_attachments/{$accountEmailDir}/{$year}/{$month}/{$day}/{$messageIdHash}/{$safeFileName}";

        $absoluteCasPath = storage_path('app/' . $casPath);
        $absoluteReadablePath = storage_path('app/' . $readablePath);

        if (!file_exists($absoluteReadablePath)) {
            if (file_exists($absoluteCasPath)) {
                $targetDir = storage_path('app/' . dirname($readablePath));
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                try {
                    if (!@link($absoluteCasPath, $absoluteReadablePath)) {
                        if (!@symlink($absoluteCasPath, $absoluteReadablePath)) {
                            Storage::copy($casPath, $readablePath);
                        }
                    }
                } catch (\Exception $e) {
                    try {
                        if (!@symlink($absoluteCasPath, $absoluteReadablePath)) {
                            Storage::copy($casPath, $readablePath);
                        }
                    } catch (\Exception $ex) {
                        Storage::copy($casPath, $readablePath);
                    }
                }
            } else {
                // Fallback for virtual filesystems (e.g. testing using Storage::fake())
                Storage::copy($casPath, $readablePath);
            }
        }

        return DB::table('email_attachments')->insertGetId([
            'email_id' => $emailId,
            'filename' => $fileName,
            'file_path' => $readablePath,
            'mime_type' => $mimeType,
            'content_id' => $contentId,
            'is_inline' => $isInline,
            'file_size' => $fileSize,
            'sha256' => $sha256,
            'created_at' => now(),
        ]);
    }

    /**
     * Add sender to contacts if not already present.
     */
    protected function autoCreateContact(int $userId, string $email, ?string $name): void
    {
        if (empty($email)) return;

        $exists = DB::table('email_contacts')
            ->where('user_id', $userId)
            ->where('email', $email)
            ->exists();

        if (!$exists) {
            DB::table('email_contacts')->insert([
                'user_id' => $userId,
                'name' => $name ?: explode('@', $email)[0],
                'email' => $email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Run simulation mode to generate highly realistic demo emails.
     */
    protected function runSimulation(object $account, string $warning = ''): array
    {
        $templates = [
            [
                'from_name' => 'Sophia Martinez (Sales Director)',
                'from_address' => 'sophia.martinez@mserp-client.com',
                'subject' => 'URGENT: Request for Sales Invoice Revision - #INV-2026-004',
                'body_html' => '<p>Hi Team,</p><p>We received the invoice #INV-2026-004 for the Q2 licenses, but noticed the discount code for 10% was not applied. Could you please correct the amount and send over an updated PDF?</p><p>Best regards,<br>Sophia Martinez</p>',
                'body_text' => "Hi Team,\n\nWe received the invoice #INV-2026-004 for the Q2 licenses, but noticed the discount code for 10% was not applied. Could you please correct the amount and send over an updated PDF?\n\nBest regards,\nSophia Martinez",
                'folder' => 'INBOX',
                'attachments' => [
                    ['filename' => 'q2_revised_order.xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'content' => 'dummy spreadsheet data']
                ]
            ],
            [
                'from_name' => 'Liam Anderson (Supplier Support)',
                'from_address' => 'liam.a@vendor-systems.com',
                'subject' => 'Shipment tracking details for order #PO-9842',
                'body_html' => '<p>Dear Customer,</p><p>The inventory units for order #PO-9842 have been dispatched from our Dublin warehouse. You can track the shipment using tracking number <b>TRK84928420</b>.</p><p>Find the detailed delivery manifest sheet attached.</p><p>Sincerely,<br>Liam Anderson</p>',
                'body_text' => "Dear Customer,\n\nThe inventory units for order #PO-9842 have been dispatched from our Dublin warehouse. You can track the shipment using tracking number TRK84928420.\n\nFind the detailed delivery manifest sheet attached.\n\nSincerely,\nLiam Anderson",
                'folder' => 'INBOX',
                'attachments' => [
                    ['filename' => 'delivery_manifest_PO9842.csv', 'mime_type' => 'text/csv', 'content' => "item_code,name,qty\nITM001,Wireless Keyboard,50\nITM002,Ergonomic Mouse,30"]
                ]
            ],
            [
                'from_name' => 'ERP System Workflow Alerts',
                'from_address' => 'noreply@mserp-system.com',
                'subject' => 'PENDING ACTION: Sales Invoice #INV-2026-001 requires Super Admin approval',
                'body_html' => '<p>Hello Admin,</p><p>A purchase order workflow instance <b>WF-8492</b> has been created and requires your review.</p><p><b>Invoice Details:</b><br>- Invoice No: INV-2026-001<br>- Amount: ₹5,000.00<br>- Customer: Acme Corporation</p><p>Please click on the pending workflow approval alert in your dashboard to approve or reject this request.</p>',
                'body_text' => "Hello Admin,\n\nA purchase order workflow instance WF-8492 has been created and requires your review.\n\nInvoice Details:\n- Invoice No: INV-2026-001\n- Amount: ₹5,000.00\n- Customer: Acme Corporation\n\nPlease click on the pending workflow approval alert in your dashboard to approve or reject this request.",
                'folder' => 'INBOX',
                'attachments' => []
            ],
            [
                'from_name' => 'Michael Chang (CFO)',
                'from_address' => 'cfo@ourcompany.com',
                'subject' => 'RE: URGENT: Request for Sales Invoice Revision - #INV-2026-004',
                'body_html' => '<p>Got it. I will review this invoice with the accounts team and ensure the revised invoice is processed through the billing engine.</p>',
                'body_text' => "Got it. I will review this invoice with the accounts team and ensure the revised invoice is processed through the billing engine.",
                'folder' => 'SENT',
                'attachments' => []
            ]
        ];

        $syncedCount = 0;
        foreach ($templates as $tmpl) {
            // Check if this specific subject email exists to avoid duplication
            $exists = DB::table('emails')
                ->where('email_account_id', $account->id)
                ->where('subject', $tmpl['subject'])
                ->exists();

            if ($exists) continue;

            $parsed = [
                'message_id' => '<' . time() . '.' . uniqid() . '@mserp.simulated>',
                'subject' => $tmpl['subject'],
                'from_address' => $tmpl['from_address'],
                'from_name' => $tmpl['from_name'],
                'to_address' => $account->email,
                'cc_address' => null,
                'bcc_address' => null,
                'date_sent' => now()->subHours(rand(1, 48))->format('Y-m-d H:i:s'),
                'body_html' => $tmpl['body_html'],
                'body_text' => $tmpl['body_text'],
                'attachments' => $tmpl['attachments']
            ];

            $this->saveParsedEmail($account, $parsed, null, $tmpl['folder']);
            $syncedCount++;
        }

        $message = "Simulated sync completed. {$syncedCount} mock emails added.";
        if (!empty($warning)) {
            $message = $warning . " " . $message;
        }

        return ['success' => true, 'message' => $message];
    }
}
