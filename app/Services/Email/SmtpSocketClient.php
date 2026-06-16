<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;

class SmtpSocketClient extends SocketClient
{
    protected $username;
    protected $password;

    public function __construct(string $host, int $port, string $encryption, string $username, string $password)
    {
        parent::__construct($host, $port, $encryption);
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Send email via SMTP commands.
     */
    public function sendEmail(
        string $fromAddress,
        string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        array $attachments = []
    ): bool {
        if (!$this->connect()) {
            return false;
        }

        try {
            // 1. Read Greeting
            $response = $this->readResponse();
            if (!$this->checkCode($response, '220')) {
                throw new \Exception("SMTP greeting failed: " . $response);
            }

            // 2. EHLO
            $this->send("EHLO " . request()->getHost());
            $response = $this->readResponse();
            if (!$this->checkCode($response, '250')) {
                throw new \Exception("EHLO failed: " . $response);
            }

            // 3. STARTTLS Upgrade if needed
            if ($this->encryption === 'starttls') {
                $this->send("STARTTLS");
                $response = $this->readResponse();
                if (!$this->checkCode($response, '220')) {
                    throw new \Exception("STARTTLS command rejected: " . $response);
                }

                if (!$this->enableCrypto()) {
                    throw new \Exception("TLS stream negotiation failed.");
                }

                // Re-handshake after encryption upgrade
                $this->send("EHLO " . request()->getHost());
                $response = $this->readResponse();
                if (!$this->checkCode($response, '250')) {
                    throw new \Exception("EHLO after TLS upgrade failed: " . $response);
                }
            }

            // 4. Authenticate
            if (!empty($this->username)) {
                $this->send("AUTH LOGIN");
                $response = $this->readResponse();
                if (!$this->checkCode($response, '334')) {
                    throw new \Exception("AUTH LOGIN failed: " . $response);
                }

                $this->send(base64_encode($this->username));
                $response = $this->readResponse();
                if (!$this->checkCode($response, '334')) {
                    throw new \Exception("AUTH LOGIN username rejected: " . $response);
                }

                $this->send(base64_encode($this->password));
                $response = $this->readResponse();
                if (!$this->checkCode($response, '235')) {
                    throw new \Exception("AUTH LOGIN password rejected: " . $response);
                }
            }

            // 5. Mail From
            $this->send("MAIL FROM:<{$fromAddress}>");
            $response = $this->readResponse();
            if (!$this->checkCode($response, '250')) {
                throw new \Exception("MAIL FROM failed: " . $response);
            }

            // 6. Recipients (TO, CC, BCC)
            $allRecipients = array_merge($to, $cc, $bcc);
            foreach ($allRecipients as $rcpt) {
                $rcptEmail = $rcpt['email'] ?? $rcpt;
                $this->send("RCPT TO:<{$rcptEmail}>");
                $response = $this->readResponse();
                if (!$this->checkCode($response, '250') && !$this->checkCode($response, '251')) {
                    throw new \Exception("RCPT TO <{$rcptEmail}> failed: " . $response);
                }
            }

            // 7. Data
            $this->send("DATA");
            $response = $this->readResponse();
            if (!$this->checkCode($response, '354')) {
                throw new \Exception("DATA initiation failed: " . $response);
            }

            // 8. Build and Send MIME Payload
            $mimeMessage = $this->buildMimePayload($fromAddress, $fromName, $to, $cc, $subject, $bodyHtml, $bodyText, $attachments);
            $this->sendRawPayload($mimeMessage);
            
            // End of DATA period marker
            $this->send(".");
            $response = $this->readResponse();
            if (!$this->checkCode($response, '250')) {
                throw new \Exception("Email transmission failed: " . $response);
            }

            // 9. Quit
            $this->send("QUIT");
            $this->disconnect();
            return true;

        } catch (\Exception $e) {
            $this->log("Error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Build the raw MIME multipart message.
     */
    protected function buildMimePayload(
        string $fromAddress,
        string $fromName,
        array $to,
        array $cc,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        array $attachments
    ): string {
        $boundaryMixed = '----=_Part_Mixed_' . md5(time() . uniqid());
        $boundaryAlternative = '----=_Part_Alternative_' . md5(time() . uniqid());

        // Format Recipients
        $toHeader = [];
        foreach ($to as $t) {
            $toHeader[] = isset($t['name']) ? "\"{$t['name']}\" <{$t['email']}>" : $t;
        }
        $ccHeader = [];
        foreach ($cc as $c) {
            $ccHeader[] = isset($c['name']) ? "\"{$c['name']}\" <{$c['email']}>" : $c;
        }

        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: \"{$fromName}\" <{$fromAddress}>";
        $headers[] = "To: " . implode(', ', $toHeader);
        if (!empty($ccHeader)) {
            $headers[] = "Cc: " . implode(', ', $ccHeader);
        }
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . time() . uniqid() . "@" . request()->getHost() . ">";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"";
        $headers[] = ""; // Empty line separation headers from body

        $body = [];
        // Mixed Boundary Start
        $body[] = "--{$boundaryMixed}";
        $body[] = "Content-Type: multipart/alternative; boundary=\"{$boundaryAlternative}\"";
        $body[] = "";

        // Plain Text Part
        $body[] = "--{$boundaryAlternative}";
        $body[] = "Content-Type: text/plain; charset=UTF-8";
        $body[] = "Content-Transfer-Encoding: 7bit";
        $body[] = "";
        $body[] = $bodyText;
        $body[] = "";

        // HTML Part
        $body[] = "--{$boundaryAlternative}";
        $body[] = "Content-Type: text/html; charset=UTF-8";
        $body[] = "Content-Transfer-Encoding: 8bit";
        $body[] = "";
        $body[] = $bodyHtml;
        $body[] = "";
        
        // End alternative part
        $body[] = "--{$boundaryAlternative}--";
        $body[] = "";

        // Attachments
        foreach ($attachments as $att) {
            $path = $att['path'] ?? null;
            $name = $att['name'] ?? 'attachment';
            $mime = $att['mime_type'] ?? 'application/octet-stream';
            
            if ($path && file_exists($path)) {
                $content = file_get_contents($path);
                $encoded = chunk_split(base64_encode($content));

                $body[] = "--{$boundaryMixed}";
                $body[] = "Content-Type: {$mime}; name=\"{$name}\"";
                $body[] = "Content-Disposition: attachment; filename=\"{$name}\"";
                $body[] = "Content-Transfer-Encoding: base64";
                $body[] = "";
                $body[] = $encoded;
                $body[] = "";
            }
        }

        // End mixed part
        $body[] = "--{$boundaryMixed}--";

        return implode("\r\n", $headers) . "\r\n" . implode("\r\n", $body);
    }

    /**
     * Read multi-line response buffer until completion.
     */
    protected function readResponse(): string
    {
        $response = "";
        while ($line = $this->readLine()) {
            $response .= $line;
            // Check if this is the final line of multi-line response (character 4 must not be '-')
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * Send raw RFC822 text payload to SMTP server.
     */
    protected function sendRawPayload(string $payload): void
    {
        // Double periods at start of line to comply with SMTP transparency
        $lines = explode("\r\n", $payload);
        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            fwrite($this->socket, $line . "\r\n");
        }
    }

    /**
     * Verify if the response code starts with the expected digits.
     */
    protected function checkCode(string $response, string $code): bool
    {
        return str_starts_with($response, $code);
    }
}
