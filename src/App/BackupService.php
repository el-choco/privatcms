<?php
declare(strict_types=1);
namespace App;

use PDO;
use ZipArchive;
use Exception;

class BackupService {
    private PDO $pdo;
    private string $backupDir;
    private string $uploadDir;

    public function __construct(PDO $pdo, string $rootDir) {
        $this->pdo = $pdo;
        $realRoot = realpath($rootDir) ?: $rootDir;
        $this->backupDir = rtrim($realRoot, '/') . '/backups/';
        $this->uploadDir = rtrim($realRoot, '/') . '/public/uploads/';
        if (!is_dir($this->backupDir)) mkdir($this->backupDir, 0775, true);
    }

    public function createFullBackup(): string {
        if (!extension_loaded('zip')) throw new Exception("Zip-Modul fehlt.");
        $filename = 'full_backup_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $this->backupDir . $filename;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) throw new Exception("ZIP-Fehler.");
        $zip->addFromString('database.sql', $this->generateSqlDump());
        if (is_dir($this->uploadDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->uploadDir));
            foreach ($files as $file) {
                if (!$file->isDir()) $zip->addFile($file->getPathname(), 'uploads/' . basename($file->getPathname()));
            }
        }
        $zip->close();
        return $zipPath;
    }

    public function exportJson(): string {
        $tables = ['posts', 'categories', 'settings', 'comments'];
        $data = [];
        foreach ($tables as $t) $data[$t] = $this->pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        $path = $this->backupDir . 'export_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $path;
    }

    public function exportCsv(): string {
        $path = $this->backupDir . 'export_csv_' . date('Y-m-d_H-i-s') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach (['posts', 'categories', 'comments'] as $table) {
            $rows = $this->pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            $output = fopen('php://temp', 'r+');
            if (!empty($rows)) fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($output, $row);
            rewind($output);
            $zip->addFromString("$table.csv", stream_get_contents($output));
            fclose($output);
        }
        $zip->close();
        return $path;
    }

    public function getList(): array {
        $files = [];
        if (is_dir($this->backupDir)) {
            foreach (array_diff(scandir($this->backupDir), ['.', '..']) as $f) {
                $path = $this->backupDir . $f;
                $files[] = ['filename' => $f, 'size' => filesize($path), 'created' => filemtime($path)];
            }
        }
        usort($files, fn($a, $b) => $b['created'] <=> $a['created']);
        return $files;
    }

    private function generateSqlDump(): string {
        $dump = "-- PiperBlog Dump\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach (['users', 'categories', 'settings', 'posts', 'comments', 'files'] as $table) {
            $stmt = $this->pdo->query("SHOW CREATE TABLE `$table` ");
            $res = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;
            if ($res && isset($res[1])) {
                $dump .= "DROP TABLE IF EXISTS `$table`;\n" . $res[1] . ";\n\n";
                $rows = $this->pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $values = array_map(fn($v) => $v === null ? 'NULL' : $this->pdo->quote((string)$v), $row);
                    $dump .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                }
            }
            $dump .= "\n";
        }
        $dump .= "SET FOREIGN_KEY_CHECKS = 1;";
        return $dump;
    }
}