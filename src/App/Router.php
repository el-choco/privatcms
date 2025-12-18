<?php
declare(strict_types=1);

namespace App;

class Router {
    /** Basic GET helper */
    public static function get(string $key, string $default = ''): string {
        return isset($_GET[$key]) ? (string)$_GET[$key] : $default;
    }
}
