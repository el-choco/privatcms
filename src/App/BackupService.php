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
        $tables = ['posts', 'categories', 'settings', 'comments', 'forum_boards', 'forum_threads', 'forum_posts'];
        $data = [];
        foreach ($tables as $t) {
            try {
                $data[$t] = $this->pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { continue; }
        }
        $path = $this->backupDir . 'export_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $path;
    }

    public function exportCsv(): string {
        $path = $this->backupDir . 'export_csv_' . date('Y-m-d_H-i-s') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach (['posts', 'categories', 'comments', 'forum_boards', 'forum_threads', 'forum_posts'] as $table) {
            try {
                $rows = $this->pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                $output = fopen('php://temp', 'r+');
                if (!empty($rows)) fputcsv($output, array_keys($rows[0]));
                foreach ($rows as $row) fputcsv($output, $row);
                rewind($output);
                $zip->addFromString("$table.csv", stream_get_contents($output));
                fclose($output);
            } catch (Exception $e) { continue; }
        }
        $zip->close();
        return $path;
    }

    public function restoreBackup(string $filePath): void {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) throw new Exception("Konnte ZIP-Datei nicht öffnen.");

        $sql = $zip->getFromName('database.sql');
        if (!$sql) {
            $zip->close();
            throw new Exception("Fehler: database.sql nicht im Backup gefunden.");
        }

        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec($sql);
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (Exception $e) {
            $zip->close();
            throw new Exception("Datenbank-Import fehlgeschlagen: " . $e->getMessage());
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_starts_with($entry, 'uploads/') && !str_ends_with($entry, '/')) {
                $fileName = basename($entry);
                $targetPath = $this->uploadDir . $fileName;
                
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($targetPath, $content);
                }
            }
        }

        $zip->close();
    }
    
    public function delete(string $filename): bool {
        $path = $this->backupDir . basename($filename);
        return file_exists($path) && unlink($path);
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
        
        $tables = [
            'users', 'categories', 'settings', 'posts', 'comments', 'files',
            'tags', 'post_tags', 'daily_stats', 'messages', 'pages', 'menu_items',
            'forum_boards', 'forum_threads', 'forum_posts', 'forum_labels'
        ];

        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SHOW CREATE TABLE `$table` ");
                $res = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;
                if ($res && isset($res[1])) {
                    $dump .= "DROP TABLE IF EXISTS `$table`;\n" . $res[1] . ";\n\n";
                    $rows = $this->pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $values = array_map(fn($v) => $v === null ? 'NULL' : $this->pdo->quote((string)$v), $row);
                        $dump .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                    }
                    $dump .= "\n";
                }
            } catch (Exception $e) {
                continue;
            }
        }
        $dump .= "SET FOREIGN_KEY_CHECKS = 1;";
        return $dump;
    }
}
