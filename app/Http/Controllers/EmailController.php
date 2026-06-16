<?php

namespace App\Http\Controllers;

use App\Services\Email\EmailSyncService;
use App\Services\Email\SmtpSocketClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class EmailController extends Controller
{
    protected $syncService;

    public function __construct(EmailSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    protected function checkPagePermission(string $slug)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        $page = DB::table('pages')->where('slug', $slug)->where('is_active', true)->first();
        if (!$page) {
            abort(404, 'Page not found or inactive.');
        }

        // Super Admin bypass
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if ($role && $role->slug === 'super-admin') {
            return;
        }

        // Check user-specific permissions first
        $perms = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->where('page_id', $page->id)
            ->first();

        if (!$perms) {
            // Fall back to role-based permissions
            $perms = DB::table('role_permissions')
                ->where('role_id', $user->role_id)
                ->where('page_id', $page->id)
                ->first();
        }

        if (!$perms || !$perms->can_view) {
            abort(403, 'Unauthorized access.');
        }
    }

    protected function checkAnyEmailPermission()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        // Super Admin bypass
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        if ($role && $role->slug === 'super-admin') {
            return;
        }

        $emailPages = DB::table('pages')
            ->whereIn('slug', ['email-inbox', 'email-contacts', 'email-compose'])
            ->where('is_active', true)
            ->pluck('id');

        if ($emailPages->isEmpty()) {
            abort(403, 'Unauthorized access.');
        }

        // Check if user has view permission override for any of these pages
        $userOverrides = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->whereIn('page_id', $emailPages)
            ->get();
            
        $allowedCount = 0;
        foreach ($emailPages as $pid) {
            $override = $userOverrides->firstWhere('page_id', $pid);
            if ($override) {
                if ($override->can_view) {
                    $allowedCount++;
                }
            } else {
                $rolePerm = DB::table('role_permissions')
                    ->where('role_id', $user->role_id)
                    ->where('page_id', $pid)
                    ->value('can_view');
                if ($rolePerm) {
                    $allowedCount++;
                }
            }
        }

        if ($allowedCount > 0) {
            return;
        }

        abort(403, 'Unauthorized access.');
    }

    /**
     * Get active email account for user.
     */
    protected function getActiveAccount()
    {
        $this->ensureEmailTablesExist();
        $user = Auth::user();
        $accountId = session('active_email_account_id');
        if (!$accountId) {
            $accountId = DB::table('email_account_users')->where('user_id', $user->id)->value('email_account_id');
            if ($accountId) {
                session(['active_email_account_id' => $accountId]);
            }
        }

        if (!$accountId) {
            return null;
        }

        $hasAccess = DB::table('email_account_users')
            ->where('email_account_id', $accountId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$hasAccess) {
            // Re-fetch default if active session is invalid or belongs to another user
            $accountId = DB::table('email_account_users')->where('user_id', $user->id)->value('email_account_id');
            if ($accountId) {
                session(['active_email_account_id' => $accountId]);
            } else {
                session()->forget('active_email_account_id');
                return null;
            }
        }

        return DB::table('email_accounts')->where('id', $accountId)->first();
    }

    /**
     * View Inbox Page.
     */
    public function inbox(Request $request)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }
        $this->checkPagePermission('email-inbox');
        $account = $this->getActiveAccount();
        
        // Count folder stats
        $counts = [
            'INBOX' => 0,
            'SENT' => 0,
            'DRAFTS' => 0,
            'TRASH' => 0,
            'SPAM' => 0,
            'ARCHIVE' => 0
        ];

        if ($account) {
            $rawCounts = DB::table('emails')
                ->where('email_account_id', $account->id)
                ->select('folder', DB::raw('count(*) as cnt'))
                ->groupBy('folder')
                ->get();
            foreach ($rawCounts as $rc) {
                if (array_key_exists($rc->folder, $counts)) {
                    $counts[$rc->folder] = $rc->cnt;
                }
            }
        }

        $labels = DB::table('email_labels')
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        $allAcc = DB::table('email_accounts')
            ->join('email_account_users', 'email_accounts.id', '=', 'email_account_users.email_account_id')
            ->where('email_account_users.user_id', Auth::id())
            ->where('email_accounts.is_active', true)
            ->select('email_accounts.*')
            ->get();

        $lastSync = null;
        if ($account) {
            $lastEmail = DB::table('emails')
                ->where('email_account_id', $account->id)
                ->orderBy('created_at', 'desc')
                ->first();
            if ($lastEmail) {
                $lastSync = (object)[
                    'status' => 'completed',
                    'finished_at' => $lastEmail->created_at
                ];
            }
        }

        return view('modules.loader', array_merge(
            compact('account', 'counts', 'labels', 'allAcc', 'lastSync'),
            ['pageDir' => 'modules/email/inbox']
        ));
    }

    /**
     * View Address Book Page.
     */
    public function contacts(Request $request)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }
        $this->checkPagePermission('email-contacts');
        $this->ensureEmailTablesExist();
        $contacts = DB::table('email_contacts')
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
        
        $groups = DB::table('email_contact_groups')
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return view('modules.loader', array_merge(
            compact('contacts', 'groups'),
            ['pageDir' => 'modules/email/contacts']
        ));
    }

    /**
     * View Compose Page.
     */
    public function compose(Request $request)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }
        $this->checkPagePermission('email-compose');
        $account = $this->getActiveAccount();
        $templates = DB::table('email_templates')->where('user_id', Auth::id())->get();
        $signature = DB::table('email_signatures')->where('user_id', Auth::id())->where('is_default', true)->first();

        // Check if loading as reply/forward context
        $replyToId = $request->input('reply_to');
        $replyMail = null;
        if ($replyToId) {
            $replyMail = DB::table('emails')
                ->leftJoin('email_bodies', 'emails.id', '=', 'email_bodies.email_id')
                ->where('emails.id', $replyToId)
                ->select('emails.*', 'email_bodies.body_html', 'email_bodies.body_text')
                ->first();
        }

        // Fetch contacts for autocompletion
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        $isSuperAdmin = $role && $role->slug === 'super-admin';
        $canSendToAnyone = $isSuperAdmin || (bool)($user->can_send_to_anyone ?? false);

        $contacts = DB::table('email_contacts')
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['name', 'email']);

        return view('modules.loader', array_merge(
            compact('account', 'templates', 'signature', 'replyMail', 'contacts', 'canSendToAnyone'),
            ['pageDir' => 'modules/email/compose']
        ));
    }

    /**
     * Switch current email account session index.
     */
    public function switchAccount(Request $request)
    {
        $this->checkAnyEmailPermission();
        $id = $request->input('email_account_id');
        $exists = DB::table('email_account_users')
            ->where('email_account_id', $id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            session(['active_email_account_id' => $id]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'error' => 'Account not found.'], 404);
    }

    /**
     * Get active email account settings.
     */
    public function getSettings()
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json([
                'email' => '',
                'display_name' => '',
                'password' => ''
            ]);
        }

        return response()->json([
            'email' => $account->email,
            'display_name' => $account->display_name,
            'password' => ''
        ]);
    }

    /**
     * Update active email account settings.
     */
    public function updateSettings(Request $request)
    {
        $this->checkAnyEmailPermission();
        
        $request->validate([
            'email' => 'required|email',
            'display_name' => 'required|string|max:255',
            'password' => 'nullable|string'
        ]);

        $email = $request->input('email');
        $displayName = $request->input('display_name');
        $password = $request->input('password');

        $domain = substr(strrchr($email, "@"), 1);
        $isGmail = (strtolower($domain) === 'gmail.com');
        
        $imapHost = $isGmail ? 'imap.gmail.com' : 'mail.' . $domain;
        $imapPort = 993;
        $imapEncryption = 'ssl';

        $smtpHost = $isGmail ? 'smtp.gmail.com' : 'mail.' . $domain;
        $smtpPort = 465;
        $smtpEncryption = 'ssl';

        $userId = Auth::id();
        $account = $this->getActiveAccount();

        $data = [
            'email' => $email,
            'display_name' => $displayName,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_user' => $email,
            'imap_host' => $imapHost,
            'imap_port' => $imapPort,
            'imap_encryption' => $imapEncryption,
            'imap_user' => $email,
            'is_active' => true,
            'updated_at' => now()
        ];

        if ($password !== null && $password !== '') {
            $data['smtp_password'] = encrypt($password);
            $data['imap_password'] = encrypt($password);
        }

        if ($account) {
            DB::table('email_accounts')->where('id', $account->id)->update($data);
            $accountId = $account->id;
        } else {
            if (empty($password)) {
                $password = 'placeholder';
            }
            $data['smtp_password'] = encrypt($password);
            $data['imap_password'] = encrypt($password);
            $data['created_at'] = now();
            $accountId = DB::table('email_accounts')->insertGetId($data);

            // Map user to the newly created account
            DB::table('email_account_users')->insert([
                'email_account_id' => $accountId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        session(['active_email_account_id' => $accountId]);

        return response()->json(['success' => true]);
    }

    /**
     * Synchronize emails via Socket connection helper.
     */
    public function sync($id)
    {
        $this->checkAnyEmailPermission();
        // Double check ownership
        $account = DB::table('email_accounts')
            ->join('email_account_users', 'email_accounts.id', '=', 'email_account_users.email_account_id')
            ->where('email_accounts.id', $id)
            ->where('email_account_users.user_id', Auth::id())
            ->select('email_accounts.*')
            ->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account access denied.'], 403);
        }

        $result = $this->syncService->syncAccount($id);
        return response()->json($result);
    }

    /**
     * Fetch emails for AG Grid list.
     */
    public function getEmailList(Request $request)
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json(['data' => []]);
        }

        $folder = $request->input('folder', 'INBOX');
        $label = $request->input('label');
        
        $query = DB::table('emails')
            ->where('email_account_id', $account->id);

        $selectCols = [
            'emails.id',
            'emails.email_account_id',
            'emails.message_id',
            'emails.thread_id',
            'emails.uid',
            'emails.from_address',
            'emails.from_name',
            'emails.to_address',
            'emails.cc_address',
            'emails.bcc_address',
            'emails.subject',
            'emails.date_sent',
            'emails.folder',
            'emails.is_read',
            'emails.is_starred',
            'emails.has_attachments',
            'emails.created_at',
            'emails.updated_at'
        ];

        if ($label) {
            $query->join('email_label_emails', 'emails.id', '=', 'email_label_emails.email_id')
                ->join('email_labels', 'email_label_emails.label_id', '=', 'email_labels.id')
                ->where('email_labels.name', $label)
                ->select($selectCols);
        } else {
            $query->select($selectCols);
            if ($folder === 'STARRED') {
                $query->where('is_starred', true);
            } else {
                $query->where('folder', $folder);
            }
        }

        // Parse search term (e.g. from:sophia subject:revision urgent)
        $search = $request->input('search');
        if (!empty($search)) {
            $terms = [];
            preg_match_all('/(from|to|subject|label):("[^"]+"|[^\s]+)/i', $search, $matches, PREG_SET_ORDER);
            
            $generalSearch = $search;
            foreach ($matches as $m) {
                $key = strtolower($m[1]);
                $val = trim($m[2], '"');
                $terms[$key] = $val;
                $generalSearch = str_replace($m[0], '', $generalSearch);
            }
            $generalSearch = trim($generalSearch);

            if (isset($terms['from'])) {
                $query->where(function($q) use ($terms) {
                    $q->where('emails.from_address', 'like', '%' . $terms['from'] . '%')
                      ->orWhere('emails.from_name', 'like', '%' . $terms['from'] . '%');
                });
            }
            if (isset($terms['to'])) {
                $query->where('emails.to_address', 'like', '%' . $terms['to'] . '%');
            }
            if (isset($terms['subject'])) {
                $query->where('emails.subject', 'like', '%' . $terms['subject'] . '%');
            }
            if (isset($terms['label'])) {
                $query->whereExists(function($q) use ($terms) {
                    $q->select(DB::raw(1))
                      ->from('email_label_emails')
                      ->join('email_labels', 'email_label_emails.label_id', '=', 'email_labels.id')
                      ->whereColumn('email_label_emails.email_id', 'emails.id')
                      ->where('email_labels.name', 'like', '%' . $terms['label'] . '%');
                });
            }
            if (!empty($generalSearch)) {
                $query->where(function($q) use ($generalSearch) {
                    $q->where('emails.subject', 'like', '%' . $generalSearch . '%')
                      ->orWhere('emails.from_address', 'like', '%' . $generalSearch . '%')
                      ->orWhere('emails.from_name', 'like', '%' . $generalSearch . '%')
                      ->orWhere('emails.to_address', 'like', '%' . $generalSearch . '%')
                      ->orWhereExists(function($sub) use ($generalSearch) {
                          $sub->select(DB::raw(1))
                              ->from('email_bodies')
                              ->whereColumn('email_bodies.email_id', 'emails.id')
                              ->where('email_bodies.body_text', 'like', '%' . $generalSearch . '%');
                      });
                });
            }
        }

        $emails = $query->orderBy('emails.date_sent', 'desc')
            ->orderBy('emails.id', 'desc')
            ->get();

        foreach ($emails as $email) {
            $email->labels = DB::table('email_labels')
                ->join('email_label_emails', 'email_labels.id', '=', 'email_label_emails.label_id')
                ->where('email_label_emails.email_id', $email->id)
                ->select('email_labels.id', 'email_labels.name', 'email_labels.color')
                ->get();
            $email->is_read = (bool)$email->is_read;
            $email->is_starred = (bool)$email->is_starred;
            $email->has_attachments = (bool)$email->has_attachments;
        }

        return response()->json(['data' => $emails]);
    }

    /**
     * Fetch full email thread details for conversation view.
     */
    public function getThread($threadId)
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = DB::table('emails')
            ->leftJoin('email_bodies', 'emails.id', '=', 'email_bodies.email_id')
            ->where('emails.email_account_id', $account->id)
            ->where('emails.thread_id', $threadId)
            ->select('emails.*', 'email_bodies.body_html', 'email_bodies.body_text')
            ->orderBy('emails.date_sent', 'asc')
            ->get();

        $imap = null;
        $connectionTried = false;
        $connected = false;

        foreach ($messages as $msg) {
            if ($msg->body_html === null && $msg->uid) {
                // We need to fetch this email from IMAP!
                $isPlaceholder = empty($account->imap_host) || 
                                 str_contains($account->imap_host, 'example.com') || 
                                 str_contains($account->imap_user, 'example') ||
                                 $account->imap_host === 'localhost';

                if (!$isPlaceholder) {
                    if (!$connectionTried) {
                        $connectionTried = true;
                        try {
                            $imap = new \App\Services\Email\ImapSocketClient(
                                $account->imap_host,
                                $account->imap_port ?? 993,
                                $account->imap_encryption ?? 'ssl',
                                $account->imap_user,
                                $this->syncService->safeDecrypt($account->imap_password)
                            );
                            if ($imap->login()) {
                                $select = $imap->selectFolder($msg->folder ?: 'INBOX');
                                if ($select['ok']) {
                                    $connected = true;
                                }
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("IMAP dynamic connection error during lazy load: " . $e->getMessage());
                        }
                    }

                    if ($connected && $imap) {
                        try {
                            $rawMime = $imap->fetchRawEmail((int)$msg->uid);
                            if (!empty($rawMime)) {
                                // Save the parsed email using the existing email id
                                $this->syncService->saveParsedEmail($account, $rawMime, $msg->uid, $msg->folder ?: 'INBOX', $msg->id);
                                
                                // Reload this message from the database so we have the updated fields (including body columns)
                                $updatedMsg = DB::table('emails')
                                    ->leftJoin('email_bodies', 'emails.id', '=', 'email_bodies.email_id')
                                    ->where('emails.id', $msg->id)
                                    ->select('emails.*', 'email_bodies.body_html', 'email_bodies.body_text')
                                    ->first();
                                if ($updatedMsg) {
                                    foreach ($updatedMsg as $key => $val) {
                                        $msg->$key = $val;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("IMAP lazy load error for UID {$msg->uid}: " . $e->getMessage());
                        }
                    }
                } else {
                    // For simulated messages, if body is null for some reason, give a mock body
                    $bodyHtml = "<p>This is a simulated lazy-loaded body for message subject: <b>" . e($msg->subject) . "</b>.</p>";
                    $bodyText = "This is a simulated lazy-loaded body for message subject: " . $msg->subject;
                    
                    $bodyExists = DB::table('email_bodies')->where('email_id', $msg->id)->exists();
                    if ($bodyExists) {
                        DB::table('email_bodies')->where('email_id', $msg->id)->update([
                            'body_html' => $bodyHtml,
                            'body_text' => $bodyText,
                            'updated_at' => now()
                        ]);
                    } else {
                        DB::table('email_bodies')->insert([
                            'email_id' => $msg->id,
                            'body_html' => $bodyHtml,
                            'body_text' => $bodyText,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    $msg->body_html = $bodyHtml;
                    $msg->body_text = $bodyText;
                }
            }
        }

        if ($imap) {
            $imap->disconnect();
        }

        // Mark them as read
        DB::table('emails')
            ->where('email_account_id', $account->id)
            ->where('thread_id', $threadId)
            ->update(['is_read' => true]);

        // Attach attachments
        foreach ($messages as $msg) {
            $msg->attachments = DB::table('email_attachments')->where('email_id', $msg->id)->get();
            $msg->date_formatted = date('M d, Y H:i', strtotime($msg->date_sent));
        }

        return response()->json(['messages' => $messages]);
    }

    /**
     * Send email using socket library.
     */
    public function send(Request $request)
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json(['error' => 'No active email account found.'], 404);
        }

        $request->validate([
            'to' => 'required',
            'subject' => 'required',
            'body_html' => 'required'
        ]);

        $toAddresses = array_filter(array_map('trim', explode(',', $request->input('to'))));
        $ccAddresses = array_filter(array_map('trim', explode(',', $request->input('cc', ''))));
        $bccAddresses = array_filter(array_map('trim', explode(',', $request->input('bcc', ''))));
        
        // Relational address book validation
        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        $isSuperAdmin = $role && $role->slug === 'super-admin';
        $canSendToAnyone = $isSuperAdmin || (bool)($user->can_send_to_anyone ?? false);

        $allRecipients = array_unique(array_merge($toAddresses, $ccAddresses, $bccAddresses));
        foreach ($allRecipients as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['error' => "Recipient address '$email' is not a valid email address."], 422);
            }

            if (!$canSendToAnyone) {
                $exists = DB::table('email_contacts')
                    ->where('user_id', $user->id)
                    ->where('email', $email)
                    ->exists();
                if (!$exists) {
                    return response()->json(['error' => "Recipient address '$email' is blocked. You can only send to contacts in your Address Book."], 422);
                }
            } else {
                // Auto-save new contacts to address book for Super Admin / authorized users
                $exists = DB::table('email_contacts')
                    ->where('user_id', $user->id)
                    ->where('email', $email)
                    ->exists();
                if (!$exists) {
                    DB::table('email_contacts')->insert([
                        'user_id' => $user->id,
                        'name' => explode('@', $email)[0],
                        'email' => $email,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        $subject = $request->input('subject');
        $bodyHtml = $request->input('body_html');
        $bodyText = strip_tags($bodyHtml);

        // Process attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('temp_attachments');
                $attachments[] = [
                    'path' => storage_path('app/' . $path),
                    'name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType()
                ];
            }
        }

        // Connect and Send using SmtpSocketClient
        $isPlaceholder = empty($account->smtp_host) || 
                         str_contains($account->smtp_host, 'example.com') ||
                         $account->smtp_host === 'localhost';

        $sentOk = false;
        
        if ($isPlaceholder) {
            // Emulate send success in simulator mode
            $sentOk = true;
        } else {
            try {
                $smtp = new SmtpSocketClient(
                    $account->smtp_host,
                    $account->smtp_port ?? 465,
                    $account->smtp_encryption ?? 'ssl',
                    $account->smtp_user,
                    $this->syncService->safeDecrypt($account->smtp_password)
                );

                $sentOk = $smtp->sendEmail(
                    $account->email,
                    $account->display_name,
                    $toAddresses,
                    $ccAddresses,
                    $bccAddresses,
                    $subject,
                    $bodyHtml,
                    $bodyText,
                    $attachments
                );
            } catch (\Exception $e) {
                return response()->json(['error' => 'SMTP client error: ' . $e->getMessage()], 500);
            }
        }

        if ($sentOk) {
            // Write to SENT folder
            $cleanSubject = preg_replace('/^(Re|Fwd):\s*/i', '', $subject);
            $threadId = $request->input('thread_id');
            if (!$threadId) {
                $threadId = DB::table('emails')
                    ->where('email_account_id', $account->id)
                    ->where('subject', 'like', '%' . $cleanSubject . '%')
                    ->value('thread_id') ?: (string) Str::uuid();
            }

            $emailId = DB::table('emails')->insertGetId([
                'email_account_id' => $account->id,
                'message_id' => '<' . time() . '.' . uniqid() . '@mserp.local>',
                'thread_id' => $threadId,
                'from_address' => $account->email,
                'from_name' => $account->display_name,
                'to_address' => implode(',', $toAddresses),
                'cc_address' => implode(',', $ccAddresses) ?: null,
                'bcc_address' => implode(',', $bccAddresses) ?: null,
                'subject' => $subject,
                'date_sent' => now(),
                'folder' => 'SENT',
                'is_read' => true,
                'is_starred' => false,
                'has_attachments' => !empty($attachments),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('email_bodies')->insert([
                'email_id' => $emailId,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Save attachments metadata
            foreach ($attachments as $att) {
                // Keep file in permanent storage
                $permPath = 'email_attachments/' . $account->id . '/' . uniqid() . '_' . $att['name'];
                Storage::move(str_replace(storage_path('app/'), '', $att['path']), $permPath);

                DB::table('email_attachments')->insert([
                    'email_id' => $emailId,
                    'filename' => $att['name'],
                    'file_path' => $permPath,
                    'mime_type' => $att['mime_type'],
                    'file_size' => @filesize(storage_path('app/' . $permPath)) ?: 0,
                    'created_at' => now(),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Email transmitted successfully.']);
        }

        return response()->json(['error' => 'Failed to transmit email through sockets.'], 500);
    }

    /**
     * Save composing email to Draft folder.
     */
    public function saveDraft(Request $request)
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json(['error' => 'No active account'], 404);
        }

        $draftId = $request->input('draft_id');
        $subject = $request->input('subject', '(No Subject)');
        $bodyHtml = $request->input('body_html', '');
        $bodyText = strip_tags($bodyHtml);

        $data = [
            'email_account_id' => $account->id,
            'from_address' => $account->email,
            'from_name' => $account->display_name,
            'to_address' => $request->input('to', ''),
            'cc_address' => $request->input('cc'),
            'subject' => $subject,
            'folder' => 'DRAFTS',
            'is_read' => true,
            'date_sent' => now(),
            'updated_at' => now(),
        ];

        if ($draftId) {
            DB::table('emails')->where('id', $draftId)->update($data);
            $id = $draftId;
        } else {
            $data['thread_id'] = (string) Str::uuid();
            $data['created_at'] = now();
            $id = DB::table('emails')->insertGetId($data);
        }

        // Save body to email_bodies
        $bodyExists = DB::table('email_bodies')->where('email_id', $id)->exists();
        if ($bodyExists) {
            DB::table('email_bodies')->where('email_id', $id)->update([
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'updated_at' => now()
            ]);
        } else {
            DB::table('email_bodies')->insert([
                'email_id' => $id,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json(['success' => true, 'draft_id' => $id]);
    }

    /**
     * Toggle Star flags.
     */
    public function toggleStar($id)
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $email = DB::table('emails')->where('id', $id)->first();
        if ($email) {
            DB::table('emails')->where('id', $id)->update(['is_starred' => !$email->is_starred]);
            return response()->json(['success' => true, 'is_starred' => !$email->is_starred]);
        }
        return response()->json(['error' => 'Email not found'], 404);
    }

    /**
     * Move message to a folder (e.g. TRASH).
     */
    public function moveFolder(Request $request, $id)
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $folder = $request->input('folder');
        $updated = DB::table('emails')->where('id', $id)->update(['folder' => $folder]);
        return response()->json(['success' => $updated > 0]);
    }

    /**
     * Store contact.
     */
    public function storeContact(Request $request)
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $request->validate([
            'name' => 'required',
            'email' => 'required|email'
        ]);

        $id = $request->input('id');
        $data = [
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company' => $request->input('company'),
            'notes' => $request->input('notes'),
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('email_contacts')->where('id', $id)->where('user_id', Auth::id())->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('email_contacts')->insert($data);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Delete contact.
     */
    public function deleteContact($id)
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $deleted = DB::table('email_contacts')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['success' => $deleted > 0]);
    }

    /**
     * Get unread email counts for folders.
     */
    public function getFolderCounts()
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json([]);
        }

        $counts = [
            'INBOX' => 0,
            'SENT' => 0,
            'DRAFTS' => 0,
            'TRASH' => 0,
            'SPAM' => 0,
            'ARCHIVE' => 0,
            'STARRED' => 0,
        ];

        $rawCounts = DB::table('emails')
            ->where('email_account_id', $account->id)
            ->select('folder', DB::raw('count(*) as cnt'))
            ->groupBy('folder')
            ->get();

        foreach ($rawCounts as $rc) {
            if (array_key_exists($rc->folder, $counts)) {
                $counts[$rc->folder] = $rc->cnt;
            }
        }

        $counts['STARRED'] = DB::table('emails')
            ->where('email_account_id', $account->id)
            ->where('is_starred', true)
            ->count();

        return response()->json($counts);
    }

    /**
     * Bulk action on emails.
     */
    public function bulkAction(Request $request)
    {
        $this->checkAnyEmailPermission();
        $ids = $request->input('ids', []);
        $action = $request->input('action');
        if (empty($ids) || !$action) {
            return response()->json(['success' => false, 'error' => 'Invalid inputs.'], 400);
        }

        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json(['success' => false, 'error' => 'No active account.'], 403);
        }

        $query = DB::table('emails')
            ->whereIn('id', $ids)
            ->where('email_account_id', $account->id);

        switch ($action) {
            case 'read':
                $query->update(['is_read' => true, 'updated_at' => now()]);
                break;
            case 'unread':
                $query->update(['is_read' => false, 'updated_at' => now()]);
                break;
            case 'archive':
                $query->update(['folder' => 'ARCHIVE', 'updated_at' => now()]);
                break;
            case 'spam':
                $query->update(['folder' => 'SPAM', 'updated_at' => now()]);
                break;
            case 'trash':
                $query->update(['folder' => 'TRASH', 'updated_at' => now()]);
                break;
            case 'delete':
                $query->delete();
                break;
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get email labels.
     */
    public function getLabels()
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $labels = DB::table('email_labels')
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return response()->json($labels);
    }

    /**
     * Store new email label.
     */
    public function storeLabel(Request $request)
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20'
        ]);

        $name = $request->input('name');
        $color = $request->input('color');

        $exists = DB::table('email_labels')
            ->where('user_id', Auth::id())
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'error' => 'Label name already exists.'], 422);
        }

        $id = DB::table('email_labels')->insertGetId([
            'user_id' => Auth::id(),
            'name' => $name,
            'color' => $color,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    /**
     * Delete email label.
     */
    public function deleteLabel($id)
    {
        $this->checkAnyEmailPermission();
        $this->ensureEmailTablesExist();
        $deleted = DB::table('email_labels')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        if ($deleted) {
            DB::table('email_label_emails')->where('label_id', $id)->delete();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'error' => 'Label not found.'], 404);
    }

    /**
     * Apply/associate a label to/with an email.
     */
    public function applyLabel(Request $request)
    {
        $this->checkAnyEmailPermission();
        $emailId = $request->input('email_id');
        $labelId = $request->input('label_id');
        $apply = (bool)$request->input('apply', true);

        $account = $this->getActiveAccount();
        if (!$account) {
            return response()->json(['success' => false, 'error' => 'No active account.'], 403);
        }

        // Verify email belongs to active account
        $email = DB::table('emails')
            ->where('id', $emailId)
            ->where('email_account_id', $account->id)
            ->first();

        if (!$email) {
            return response()->json(['success' => false, 'error' => 'Email not found.'], 404);
        }

        // Verify label belongs to user
        $label = DB::table('email_labels')
            ->where('id', $labelId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$label) {
            return response()->json(['success' => false, 'error' => 'Label not found.'], 404);
        }

        if ($apply) {
            DB::table('email_label_emails')->updateOrInsert([
                'email_id' => $emailId,
                'label_id' => $labelId
            ]);
        } else {
            DB::table('email_label_emails')
                ->where('email_id', $emailId)
                ->where('label_id', $labelId)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get / download email attachment.
     */
    public function getAttachment(Request $request, $id)
    {
        $this->checkAnyEmailPermission();
        $account = $this->getActiveAccount();
        if (!$account) {
            abort(403, 'Unauthorized access.');
        }

        $attachment = DB::table('email_attachments')
            ->join('emails', 'email_attachments.email_id', '=', 'emails.id')
            ->where('email_attachments.id', $id)
            ->where('emails.email_account_id', $account->id)
            ->select('email_attachments.*')
            ->first();

        if (!$attachment) {
            abort(404, 'Attachment not found.');
        }

        if (!Storage::exists($attachment->file_path)) {
            abort(404, 'File not found in storage.');
        }

        $isImage = str_starts_with($attachment->mime_type ?? '', 'image/');
        $isInlineRequested = $request->query('inline') || (isset($attachment->is_inline) && $attachment->is_inline);

        if ($isImage || $isInlineRequested) {
            return Storage::response($attachment->file_path, $attachment->filename, [
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"'
            ]);
        }

        return Storage::download($attachment->file_path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream'
        ]);
    }

    protected function ensureEmailTablesExist()
    {
        if (!Schema::hasTable('email_account_users')) {
            Schema::create('email_account_users', function ($table) {
                $table->id();
                $table->unsignedBigInteger('email_account_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['user_id', 'email_account_id']);
            });
        }

        if (!Schema::hasTable('email_accounts')) {
            Schema::create('email_accounts', function ($table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('display_name');
                $table->string('smtp_host')->nullable();
                $table->integer('smtp_port')->nullable();
                $table->string('smtp_encryption')->nullable();
                $table->string('smtp_user')->nullable();
                $table->text('smtp_password')->nullable();
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
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('email_accounts', 'last_sync_at')) {
                Schema::table('email_accounts', function ($table) {
                    $table->timestamp('last_sync_at')->nullable()->after('is_active');
                });
            }
            if (Schema::hasColumn('email_accounts', 'user_id')) {
                // Migrate existing mappings to email_account_users mapping table
                $mappings = DB::table('email_accounts')->whereNotNull('user_id')->get();
                foreach ($mappings as $mapping) {
                    $exists = DB::table('email_account_users')
                        ->where('email_account_id', $mapping->id)
                        ->where('user_id', $mapping->user_id)
                        ->exists();
                    if (!$exists) {
                        DB::table('email_account_users')->insert([
                            'email_account_id' => $mapping->id,
                            'user_id' => $mapping->user_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                // Drop foreign key and column
                Schema::table('email_accounts', function ($table) {
                    try {
                        $table->dropForeign('email_accounts_user_id_foreign');
                    } catch (\Exception $e) {}
                    $table->dropColumn('user_id');
                });
            }
        }

        if (!Schema::hasColumn('users', 'can_send_to_anyone')) {
            Schema::table('users', function ($table) {
                $table->boolean('can_send_to_anyone')->default(false)->after('role_id');
            });
        }

        if (!Schema::hasTable('emails')) {
            Schema::create('emails', function ($table) {
                $table->id();
                $table->unsignedBigInteger('email_account_id');
                $table->string('message_id')->nullable()->index();
                $table->string('thread_id')->nullable()->index();
                $table->string('uid')->nullable()->index();
                $table->string('from_address');
                $table->string('from_name')->nullable();
                $table->string('to_address');
                $table->text('cc_address')->nullable();
                $table->text('bcc_address')->nullable();
                $table->text('subject')->nullable();
                $table->timestamp('date_sent')->nullable();
                $table->string('folder')->default('INBOX');
                $table->boolean('is_read')->default(false);
                $table->boolean('is_starred')->default(false);
                $table->boolean('has_attachments')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_bodies')) {
            Schema::create('email_bodies', function ($table) {
                $table->id();
                $table->unsignedBigInteger('email_id')->unique()->index();
                $table->longText('body_html')->nullable();
                $table->longText('body_text')->nullable();
                $table->timestamps();
            });
        }

        // Migration Check: Move existing bodies out of emails table and drop columns
        if (Schema::hasTable('emails')) {
            if (Schema::hasColumn('emails', 'body_html') || Schema::hasColumn('emails', 'body_text')) {
                // Fetch existing bodies
                $existingEmails = DB::table('emails')
                    ->whereNotNull('body_html')
                    ->orWhereNotNull('body_text')
                    ->get();

                foreach ($existingEmails as $email) {
                    $exists = DB::table('email_bodies')->where('email_id', $email->id)->exists();
                    if (!$exists) {
                        DB::table('email_bodies')->insert([
                            'email_id' => $email->id,
                            'body_html' => $email->body_html,
                            'body_text' => $email->body_text,
                            'created_at' => $email->created_at ?? now(),
                            'updated_at' => $email->updated_at ?? now(),
                        ]);
                    }
                }

                // Drop columns from emails table
                Schema::table('emails', function ($table) {
                    if (Schema::hasColumn('emails', 'body_html')) {
                        $table->dropColumn('body_html');
                    }
                    if (Schema::hasColumn('emails', 'body_text')) {
                        $table->dropColumn('body_text');
                    }
                });
            }
        }

        // Add composite indexes for scalability
        if (Schema::hasTable('emails')) {
            try {
                $conn = DB::connection();
                $dbName = $conn->getDatabaseName();
                
                // Composite Index 1: email_account_id + folder + date_sent
                $idx1 = 'emails_acct_folder_date_idx';
                $exists1 = $conn->select("
                    SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'emails' AND INDEX_NAME = ?
                ", [$dbName, $idx1]);
                
                if (empty($exists1)) {
                    Schema::table('emails', function ($table) use ($idx1) {
                        $table->index(['email_account_id', 'folder', 'date_sent'], $idx1);
                    });
                }

                // Composite Index 2: email_account_id + thread_id
                $idx2 = 'emails_acct_thread_idx';
                $exists2 = $conn->select("
                    SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'emails' AND INDEX_NAME = ?
                ", [$dbName, $idx2]);
                
                if (empty($exists2)) {
                    Schema::table('emails', function ($table) use ($idx2) {
                        $table->index(['email_account_id', 'thread_id'], $idx2);
                    });
                }
            } catch (\Exception $e) {
                Log::warning("Dynamic index creation warning: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('email_attachments')) {
            Schema::create('email_attachments', function ($table) {
                $table->id();
                $table->unsignedBigInteger('email_id');
                $table->string('filename');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->string('content_id')->nullable();
                $table->boolean('is_inline')->default(false);
                $table->integer('file_size')->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
        } else {
            // Self-healing columns check
            if (!Schema::hasColumn('email_attachments', 'content_id')) {
                Schema::table('email_attachments', function ($table) {
                    $table->string('content_id')->nullable()->after('mime_type');
                });
            }
            if (!Schema::hasColumn('email_attachments', 'is_inline')) {
                Schema::table('email_attachments', function ($table) {
                    $table->boolean('is_inline')->default(false)->after('content_id');
                });
            }
        }

        if (!Schema::hasTable('email_contacts')) {
            Schema::create('email_contacts', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('email')->index();
                $table->string('phone')->nullable();
                $table->string('company')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_contact_groups')) {
            Schema::create('email_contact_groups', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_contact_group_pivot')) {
            Schema::create('email_contact_group_pivot', function ($table) {
                $table->id();
                $table->unsignedBigInteger('contact_id');
                $table->unsignedBigInteger('group_id');
            });
        }

        if (!Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('subject')->nullable();
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_signatures')) {
            Schema::create('email_signatures', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->text('content')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_labels')) {
            Schema::create('email_labels', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->string('color');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_label_emails')) {
            Schema::create('email_label_emails', function ($table) {
                $table->id();
                $table->unsignedBigInteger('email_id');
                $table->unsignedBigInteger('label_id');
            });
        }

        // Seed a default email account for the authenticated user if they don't have one
        if (Auth::check()) {
            $userId = Auth::id();
            $exists = DB::table('email_account_users')->where('user_id', $userId)->exists();
            if (!$exists) {
                $user = Auth::user();
                
                // Avoid inserting duplicate email address if it already exists from another user
                $accountId = DB::table('email_accounts')->where('email', $user->email)->value('id');
                if (!$accountId) {
                    $accountId = DB::table('email_accounts')->insertGetId([
                        'email' => $user->email,
                        'display_name' => $user->name,
                        'smtp_host' => 'smtp.example.com',
                        'smtp_port' => 465,
                        'smtp_encryption' => 'ssl',
                        'smtp_user' => $user->email,
                        'smtp_password' => encrypt('placeholder-pass'),
                        'imap_host' => 'imap.example.com',
                        'imap_port' => 993,
                        'imap_encryption' => 'ssl',
                        'imap_user' => $user->email,
                        'imap_password' => encrypt('placeholder-pass'),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('email_account_users')->insertOrIgnore([
                    'email_account_id' => $accountId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
