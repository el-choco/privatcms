<?php
declare(strict_types=1);

namespace App;

class CSRF {
    public static function token(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }
    public static function verify(?string $t): bool {
        return is_string($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
    }
}
