<?php
declare(strict_types=1);

namespace App;

final class I18n {
    private array $map = [];
    private string $locale = 'de';

    public static function fromConfig(array $ini, ?string $override = null): self {
        $i = new self();
        $locale = $override ?? ($_SESSION['lang'] ?? ($ini['app']['lang'] ?? 'de'));
        $i->setLocale((string)$locale);
        return $i;
    }

    public function setLocale(string $locale): void {
        $locale = strtolower($locale);
        $base = dirname(__DIR__, 2) . '/config/lang';
        $file = $base . '/' . $locale . '.ini';
        if (!is_file($file)) {
            $locale = 'de';
            $file = $base . '/de.ini';
        }
        $this->locale = $locale;
        $this->map = parse_ini_file($file, true, INI_SCANNER_RAW) ?: [];
        $_SESSION['lang'] = $locale;
    }

    public function t(string $key, array $repl = []): string {
        $section = null; $item = null;
        if (strpos($key, '.') !== false) {
            [$section, $item] = explode('.', $key, 2);
        } else {
            $item = $key;
        }
        $val = $section && $item && isset($this->map[$section][$item]) ? (string)$this->map[$section][$item] : ($this->map[$item] ?? $key);
        if ($repl) {
            $val = strtr($val, $repl);
        }
        return $val;
    }

    public function locale(): string {
        return $this->locale;
    }
}
