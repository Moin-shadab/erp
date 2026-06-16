<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;

class Pop3SocketClient extends SocketClient
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
     * Authenticate and initialize session.
     */
    public function login(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $greeting = $this->readLine();
        if (!$this->isSuccess($greeting)) {
            $this->disconnect();
            return false;
        }

        try {
            $this->send("USER {$this->username}");
            $response = $this->readLine();
            if (!$this->isSuccess($response)) {
                throw new \Exception("USER rejected: " . $response);
            }

            $this->send("PASS {$this->password}");
            $response = $this->readLine();
            if (!$this->isSuccess($response)) {
                throw new \Exception("PASS rejected: " . $response);
            }

            return true;
        } catch (\Exception $e) {
            $this->log("Error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Get mailbox statistics (total messages, size).
     */
    public function getStats(): array
    {
        $this->send("STAT");
        $response = $this->readLine();
        
        $count = 0;
        $size = 0;

        if ($this->isSuccess($response)) {
            // +OK 25 154323
            $parts = explode(' ', trim($response));
            if (count($parts) >= 3) {
                $count = (int)$parts[1];
                $size = (int)$parts[2];
            }
        }

        return ['count' => $count, 'size' => $size];
    }

    /**
     * Fetch raw email content by message number.
     */
    public function fetchRawEmail(int $msgNo): string
    {
        $this->send("RETR {$msgNo}");
        $response = $this->readLine();
        
        if (!$this->isSuccess($response)) {
            return "";
        }

        $rawMime = "";
        while ($line = fgets($this->socket, 4096)) {
            // POP3 marks end of email with a single dot on a line
            if (trim($line) === '.') {
                break;
            }
            
            // Remove byte stuffing if line starts with double dots
            if (str_starts_with($line, '..')) {
                $line = substr($line, 1);
            }

            $rawMime .= $line;
        }

        return $rawMime;
    }

    /**
     * Delete email.
     */
    public function deleteMessage(int $msgNo): bool
    {
        $this->send("DELE {$msgNo}");
        $response = $this->readLine();
        return $this->isSuccess($response);
    }

    /**
     * Quit and finalize deletions.
     */
    public function quit(): bool
    {
        $this->send("QUIT");
        $response = $this->readLine();
        $this->disconnect();
        return $this->isSuccess($response);
    }

    /**
     * Verify if response indicates success (+OK).
     */
    protected function isSuccess(string $response): bool
    {
        return str_starts_with(strtoupper($response), '+OK');
    }
}
