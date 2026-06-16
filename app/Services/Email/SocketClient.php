<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;

abstract class SocketClient
{
    protected $socket = null;
    protected $host;
    protected $port;
    protected $encryption; // ssl, tls, starttls, none
    protected $timeout = 10;
    protected $debug = true;
    protected $logBuffer = [];

    public function __construct(string $host, int $port, string $encryption = 'none', int $timeout = 10)
    {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = strtolower($encryption);
        $this->timeout = $timeout;
    }

    /**
     * Establish the connection.
     */
    public function connect(): bool
    {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);
        
        $target = ($this->encryption === 'ssl' || $this->encryption === 'tls' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        
        $errno = 0;
        $errstr = '';
        
        $this->socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$this->socket) {
            $this->log("Connection failed: [{$errno}] {$errstr}");
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->log("Connected to {$target}");

        return true;
    }

    /**
     * Enable SSL/TLS mid-stream (STARTTLS).
     */
    protected function enableCrypto(): bool
    {
        if (!$this->socket) {
            return false;
        }
        
        // Wait briefly for buffer clearance
        usleep(100000);

        // Upgrade socket to secure TLS stream
        $result = @stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        );

        if ($result === true) {
            $this->log("Upgraded connection to SSL/TLS (STARTTLS).");
            return true;
        }

        $this->log("Failed to upgrade socket connection to SSL/TLS.");
        return false;
    }

    /**
     * Send command to the socket.
     */
    protected function send(string $command): bool
    {
        if (!$this->socket) {
            return false;
        }

        $this->log("> " . trim($command));
        $written = fwrite($this->socket, $command . "\r\n");
        return $written !== false;
    }

    /**
     * Read a line from socket.
     */
    protected function readLine(): string
    {
        if (!$this->socket) {
            return '';
        }

        $line = fgets($this->socket, 4096);
        if ($line === false) {
            return '';
        }

        $this->log("< " . trim($line));
        return $line;
    }

    /**
     * Close the connection.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->log("Disconnected.");
        }
    }

    /**
     * Log activities for debugging.
     */
    protected function log(string $msg): void
    {
        $this->logBuffer[] = $msg;
        if ($this->debug) {
            Log::debug("[SocketClient] " . $msg);
        }
    }

    /**
     * Fetch raw session logs.
     */
    public function getLogs(): array
    {
        return $this->logBuffer;
    }
}
