<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class DatabaseBootstrapper
{
    /**
     * Ensure the database configured in env exists.
     */
    public static function bootstrap(): void
    {
        $connection = config('database.default');
        
        if ($connection !== 'mysql') {
            return;
        }

        $config = config("database.connections.{$connection}");
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '3306';
        $database = $config['database'] ?? 'mserp';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? 'Mysql@123';
        $charset = $config['charset'] ?? 'utf8mb4';

        try {
            // Test connection to the specific database
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 3, // fast timeout
            ]);
        } catch (\PDOException $e) {
            // Check if error is database missing (SQLSTATE 1049 is "Unknown database")
            if ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), '1049')) {
                try {
                    // Connect without database name
                    $dsnWithoutDb = "mysql:host={$host};port={$port};charset={$charset}";
                    $pdo = new \PDO($dsnWithoutDb, $username, $password, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    
                    // Create the database
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci;");
                    
                    Log::info("Database '{$database}' created successfully on startup.");
                } catch (\PDOException $ex) {
                    Log::error("Failed to dynamically create database '{$database}': " . $ex->getMessage());
                }
            } else {
                Log::error("Database connection failed for '{$database}' with error: " . $e->getMessage());
            }
        }
    }
}
