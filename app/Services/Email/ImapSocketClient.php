<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;

class ImapSocketClient extends SocketClient
{
    protected $username;
    protected $password;
    protected $tagCount = 0;

    public function __construct(string $host, int $port, string $encryption, string $username, string $password)
    {
        parent::__construct($host, $port, $encryption);
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Authenticate and initialize session.
     */
    public function login(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Read Greeting
        $greeting = $this->readLine();
        if (empty($greeting)) {
            $this->disconnect();
            return false;
        }

        try {
            $response = $this->executeCommand("LOGIN \"{$this->username}\" \"{$this->password}\"");
            if (!$this->isOk($response)) {
                throw new \Exception("IMAP Login failed: " . implode("\n", $response));
            }
            return true;
        } catch (\Exception $e) {
            $this->log("Error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Select mail folder.
     */
    public function selectFolder(string $folder = 'INBOX'): array
    {
        $response = $this->executeCommand("SELECT \"{$folder}\"");
        $exists = 0;
        $uidnext = 0;

        foreach ($response as $line) {
            if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $matches)) {
                $exists = (int)$matches[1];
            }
            if (preg_match('/OK\s+\[UIDNEXT\s+(\d+)\]/i', $line, $matches)) {
                $uidnext = (int)$matches[1];
            }
        }

        return [
            'exists' => $exists,
            'uidnext' => $uidnext,
            'ok' => $this->isOk($response)
        ];
    }

    /**
     * Get list of email UIDs.
     */
    public function searchUids(string $criteria = 'ALL'): array
    {
        $response = $this->executeCommand("UID SEARCH {$criteria}");
        $uids = [];

        foreach ($response as $line) {
            if (str_starts_with(strtoupper($line), '* SEARCH')) {
                // Split terms and get UIDs
                $parts = explode(' ', trim($line));
                // Remove first 2 elements (* SEARCH)
                array_shift($parts);
                array_shift($parts);
                $uids = array_filter(array_map('intval', $parts));
            }
        }

        return $uids;
    }

    /**
     * Fetch overviews for multiple UIDs in one batch request.
     */
    public function fetchOverviews(array $uids): array
    {
        if (empty($uids)) return [];
        
        $set = implode(',', $uids);
        $tag = 'A' . ++$this->tagCount;
        $response = $this->executeCommand("UID FETCH {$set} (UID FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID)])");
        
        return $this->parseOverviews($response);
    }

    /**
     * Parse overviews response lines.
     */
    protected function parseOverviews(array $lines): array
    {
        $results = [];
        $cur     = null;
        $hbuf    = '';
        $inLit   = false;

        foreach ($lines as $line) {
            if (preg_match('/^\* (\d+) FETCH \((.*)$/i', $line, $m)) {
                if ($cur) { 
                    $cur['_hraw'] = $hbuf; 
                    $results[] = $cur; 
                }
                $cur    = ['_seq' => (int)$m[1], '_rest' => $m[2]];
                $hbuf   = '';
                $inLit  = false;
                $this->parseAttrsInto($cur, $m[2]);
                if (preg_match('/\{(\d+)\}/', $m[2])) {
                    $inLit = true;
                }
                continue;
            }
            if ($cur && $inLit) {
                $hbuf .= $line . "\n";
            }
        }
        if ($cur) { 
            $cur['_hraw'] = $hbuf; 
            $results[] = $cur; 
        }

        $out = [];
        foreach ($results as $r) {
            $uid     = isset($r['UID']) ? (int)$r['UID'] : 0;
            if (!$uid) continue;
            
            $flags   = $r['FLAGS'] ?? '';
            $size    = isset($r['RFC822.SIZE']) ? (int)$r['RFC822.SIZE'] : 0;
            $date    = $r['INTERNALDATE'] ?? '';
            
            $headers = MimeParser::parseHeaders($r['_hraw'] ?? '');

            $from    = MimeParser::decodeHeader($headers['from'] ?? '');
            $subj    = MimeParser::decodeHeader($headers['subject'] ?? '');

            $isRead    = str_contains($flags, '\\Seen');
            $isStarred = str_contains($flags, '\\Flagged');

            $out[$uid] = [
                'uid'       => $uid,
                'from_address' => MimeParser::extractEmail($from),
                'from_name'  => MimeParser::extractName($from) ?: $from,
                'to_address' => MimeParser::decodeHeader($headers['to'] ?? ''),
                'cc_address' => MimeParser::decodeHeader($headers['cc'] ?? null),
                'bcc_address' => MimeParser::decodeHeader($headers['bcc'] ?? null),
                'subject'   => $subj ?: '(no subject)',
                'date_sent' => $headers['date'] ?? $date,
                'is_read'    => $isRead,
                'is_starred' => $isStarred,
                'message_id' => trim($headers['message-id'] ?? '', ' <>'),
                'rfc_size'   => $size
            ];
        }
        return $out;
    }

    protected function parseAttrsInto(array &$cur, string $s): void
    {
        if (preg_match('/\bUID (\d+)/i', $s, $m)) $cur['UID'] = $m[1];
        if (preg_match('/FLAGS \(([^)]*)\)/i', $s, $m)) $cur['FLAGS'] = $m[1];
        if (preg_match('/RFC822\.SIZE (\d+)/i', $s, $m)) $cur['RFC822.SIZE'] = $m[1];
        if (preg_match('/INTERNALDATE "([^"]+)"/i', $s, $m)) $cur['INTERNALDATE'] = $m[1];
    }

    /**
     * Fetch raw email content (MIME text) by UID.
     */
    public function fetchRawEmail(int $uid): string
    {
        $tag = 'A' . ++$this->tagCount;
        $this->send("{$tag} UID FETCH {$uid} BODY[]");

        $rawMime = "";

        while ($line = fgets($this->socket, 4096)) {
            $this->log("< " . trim($line));
            
            // Check for IMAP literal marker: e.g. {1234}
            if (preg_match('/\{(\d+)\}\r?\n?$/', $line, $matches)) {
                $bytes = (int)$matches[1];
                $data = '';
                $remain = $bytes;
                while ($remain > 0) {
                    $chunk = @fread($this->socket, min($remain, 8192));
                    if ($chunk === false || $chunk === '') break;
                    $data .= $chunk;
                    $remain -= strlen($chunk);
                }
                $rawMime .= $data;
                continue;
            }

            if (str_starts_with($line, "{$tag} ")) {
                break;
            }
        }

        return $rawMime;
    }

    /**
     * Store flags on a message by UID.
     */
    public function storeFlag(int $uid, string $flag, bool $add = true): bool
    {
        $sign = $add ? '+' : '-';
        $response = $this->executeCommand("UID STORE {$uid} {$sign}FLAGS ({$flag})");
        return $this->isOk($response);
    }

    /**
     * Delete message (mark deleted & expunge).
     */
    public function deleteMessage(int $uid): bool
    {
        $storeOk = $this->storeFlag($uid, '\\Deleted', true);
        if ($storeOk) {
            $expungeResponse = $this->executeCommand("EXPUNGE");
            return $this->isOk($expungeResponse);
        }
        return false;
    }

    /**
     * Execute IMAP command and wait for tagged completion response.
     */
    protected function executeCommand(string $command): array
    {
        $this->tagCount++;
        $tag = 'A' . $this->tagCount;
        
        $this->send("{$tag} {$command}");
        
        $response = [];
        while ($line = $this->readLine()) {
            $response[] = $line;
            if (str_starts_with($line, "{$tag} ")) {
                break;
            }
        }
        
        return $response;
    }

    /**
     * Verify if the final line of response indicates success.
     */
    protected function isOk(array $response): bool
    {
        if (empty($response)) {
            return false;
        }
        $lastLine = end($response);
        return preg_match('/^[A-Z0-9]+\s+OK/i', $lastLine) === 1;
    }
}
