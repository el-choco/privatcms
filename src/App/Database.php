<?php
declare(strict_types=1);

namespace App;

use PDO;

class Database {
    private PDO $pdo;

    public function __construct(array $cfg) {
        $host = $cfg['host'] ?? 'db';
        $port = (string)($cfg['port'] ?? '3306');
        $name = $cfg['name'] ?? 'blog';
        $user = $cfg['user'] ?? 'bloguser';
        $pass = $cfg['pass'] ?? ($cfg['password'] ?? '');
        $charset = $cfg['charset'] ?? 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
        
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // ERWEITERUNG FÜR UMLAUTE:
            // Dies erzwingt UTF-8 direkt beim Verbindungsaufbau
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $charset
        ]);
    }

    public function pdo(): PDO { return $this->pdo; }
}