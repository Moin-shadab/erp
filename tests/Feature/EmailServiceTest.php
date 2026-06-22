<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Services\Email\EmailSyncService;
use App\Http\Controllers\EmailController;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailSyncService $syncService;
    protected object $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->syncService = new EmailSyncService();

        // 1. Setup default roles and user
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $user = \App\Models\User::factory()->create([
            'role_id' => $roleId,
        ]);
        $this->actingAs($user);

        // 2. Run self-healing schema creation
        $controller = app(EmailController::class);
        $controller->ensureEmailTablesExist();

        // 3. Create active email account
        $accountId = DB::table('email_accounts')->insertGetId([
            'user_id' => $user->id,
            'email' => 'test-user@mserp.local',
            'display_name' => 'Test User',
            'smtp_host' => 'localhost',
            'smtp_port' => 1025,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert pivot mapping
        DB::table('email_account_users')->insert([
            'email_account_id' => $accountId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->account = DB::table('email_accounts')->where('id', $accountId)->first();

        Storage::fake('local');
    }

    /**
     * Test self-healing schema ensures all required columns are created.
     */
    public function test_email_tables_schema_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('emails', 'in_reply_to'));
        $this->assertTrue(Schema::hasColumn('emails', 'references'));
        $this->assertTrue(Schema::hasColumn('email_attachments', 'sha256'));
    }

    /**
     * Test Content-Addressable Storage (CAS) saves a single physical file on disk
     * for identical attachments but links them at distinct human-readable paths.
     */
    public function test_attachment_cas_deduplication_and_linking(): void
    {
        // 1. Create two separate emails
        $emailId1 = DB::table('emails')->insertGetId([
            'email_account_id' => $this->account->id,
            'message_id' => 'msg1@mserp.local',
            'thread_id' => 'thread-uuid-1',
            'from_address' => 'sender@domain.local',
            'to_address' => 'test-user@mserp.local',
            'subject' => 'Mail 1',
            'folder' => 'INBOX',
            'created_at' => now(),
        ]);

        $emailId2 = DB::table('emails')->insertGetId([
            'email_account_id' => $this->account->id,
            'message_id' => 'msg2@mserp.local',
            'thread_id' => 'thread-uuid-2',
            'from_address' => 'sender@domain.local',
            'to_address' => 'test-user@mserp.local',
            'subject' => 'Mail 2',
            'folder' => 'INBOX',
            'created_at' => now(),
        ]);

        // 2. Save same attachment content for both emails
        $fileContent = 'This is a heavy business report pdf file content.';
        $fileName = 'business_report.pdf';
        $mimeType = 'application/pdf';
        $dateSent = '2026-06-21 12:00:00';
        $sha256 = hash('sha256', $fileContent);

        $attId1 = $this->syncService->saveAttachmentCas($fileContent, $fileName, $mimeType, $dateSent, 'msg1@mserp.local', $emailId1);
        $attId2 = $this->syncService->saveAttachmentCas($fileContent, $fileName, $mimeType, $dateSent, 'msg2@mserp.local', $emailId2);

        // 3. Assert DB entries are registered
        $this->assertDatabaseHas('email_attachments', ['id' => $attId1, 'sha256' => $sha256]);
        $this->assertDatabaseHas('email_attachments', ['id' => $attId2, 'sha256' => $sha256]);

        // 4. Assert CAS physical file exists
        Storage::assertExists("email_attachments/cas/{$sha256}");

        // 5. Assert human-readable paths are registered and files exist (linked or copies)
        $att1 = DB::table('email_attachments')->where('id', $attId1)->first();
        $att2 = DB::table('email_attachments')->where('id', $attId2)->first();

        $this->assertNotEquals($att1->file_path, $att2->file_path);
        Storage::assertExists($att1->file_path);
        Storage::assertExists($att2->file_path);
    }

    /**
     * Test folder-scoped duplicate email checks to allow same message ID
     * in different folders (SENT and INBOX).
     */
    public function test_multi_folder_message_duplication_allowed(): void
    {
        $messageId = 'msg-duplicate-123@domain.local';

        // 1. Sync in SENT folder
        $emailIdSent = $this->syncService->saveParsedEmail($this->account, [
            'message_id' => $messageId,
            'subject' => 'Self Sent Mail',
            'from_address' => 'test-user@mserp.local',
            'to_address' => 'test-user@mserp.local',
            'date_sent' => '2026-06-21 12:00:00',
            'body_html' => '<p>Hello myself</p>',
            'body_text' => 'Hello myself',
        ], null, 'SENT');

        // 2. Sync in INBOX folder
        $emailIdInbox = $this->syncService->saveParsedEmail($this->account, [
            'message_id' => $messageId,
            'subject' => 'Self Sent Mail',
            'from_address' => 'test-user@mserp.local',
            'to_address' => 'test-user@mserp.local',
            'date_sent' => '2026-06-21 12:00:00',
            'body_html' => '<p>Hello myself</p>',
            'body_text' => 'Hello myself',
        ], null, 'INBOX');

        // 3. Assert both records exist concurrently in the database
        $this->assertNotEquals($emailIdSent, $emailIdInbox);
        
        $this->assertDatabaseHas('emails', [
            'id' => $emailIdSent,
            'message_id' => $messageId,
            'folder' => 'SENT',
        ]);
        
        $this->assertDatabaseHas('emails', [
            'id' => $emailIdInbox,
            'message_id' => $messageId,
            'folder' => 'INBOX',
        ]);
    }

    /**
     * Test RFC threading correctly matches thread ID using In-Reply-To and References tree.
     */
    public function test_rfc_header_threading_hierarchy(): void
    {
        // 1. Sync parent email
        $parentMsgId = 'parent-msg-id-123@domain.local';
        $parentId = $this->syncService->saveParsedEmail($this->account, [
            'message_id' => $parentMsgId,
            'subject' => 'RFC Thread Threading Root',
            'from_address' => 'customer@domain.local',
            'to_address' => 'test-user@mserp.local',
            'date_sent' => '2026-06-21 10:00:00',
            'body_html' => '<p>Question</p>',
            'body_text' => 'Question',
        ], null, 'INBOX');

        $parentEmail = DB::table('emails')->where('id', $parentId)->first();
        $this->assertNotEmpty($parentEmail->thread_id);

        // 2. Sync child email (replying to parent)
        $childMsgId = 'child-msg-id-456@domain.local';
        $childId = $this->syncService->saveParsedEmail($this->account, [
            'message_id' => $childMsgId,
            'subject' => 'Re: RFC Thread Threading Root',
            'from_address' => 'test-user@mserp.local',
            'to_address' => 'customer@domain.local',
            'date_sent' => '2026-06-21 11:00:00',
            'body_html' => '<p>Answer</p>',
            'body_text' => 'Answer',
            'in_reply_to' => $parentMsgId,
        ], null, 'SENT');

        $childEmail = DB::table('emails')->where('id', $childId)->first();
        
        // Assert child resolves to same thread ID
        $this->assertEquals($parentEmail->thread_id, $childEmail->thread_id);

        // 3. Sync grandchild email (with References header chain)
        $grandchildMsgId = 'grandchild-msg-id-789@domain.local';
        $grandchildId = $this->syncService->saveParsedEmail($this->account, [
            'message_id' => $grandchildMsgId,
            'subject' => 'Re: RFC Thread Threading Root',
            'from_address' => 'customer@domain.local',
            'to_address' => 'test-user@mserp.local',
            'date_sent' => '2026-06-21 11:30:00',
            'body_html' => '<p>Follow-up</p>',
            'body_text' => 'Follow-up',
            'in_reply_to' => $childMsgId,
            'references' => "<{$parentMsgId}> <{$childMsgId}>",
        ], null, 'INBOX');

        $grandchildEmail = DB::table('emails')->where('id', $grandchildId)->first();

        // Assert grandchild resolves to same thread ID
        $this->assertEquals($parentEmail->thread_id, $grandchildEmail->thread_id);
    }
}
