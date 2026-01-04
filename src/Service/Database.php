<?php

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private PDO $connection;

    public function __construct(string $databaseUrl)
    {
        if ($databaseUrl === '') {
            throw new RuntimeException('DATABASE_URL is not configured.');
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['scheme'])) {
            throw new RuntimeException('DATABASE_URL is invalid.');
        }

        $scheme = $parts['scheme'];
        if ($scheme !== 'mysql') {
            throw new RuntimeException(sprintf('Unsupported database scheme: %s', $scheme));
        }

        $host = $parts['host'] ?? '127.0.0.1';
        $port = $parts['port'] ?? 3306;
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        $dbName = ltrim($parts['path'] ?? '', '/');
        $charset = 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
