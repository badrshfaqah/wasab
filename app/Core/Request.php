<?php

namespace App\Core;

class Request
{
    public static function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }
        return $method;
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        $base = self::basePath();
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . ltrim($uri, '/');
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return $uri === '' ? '/' : $uri;
    }

    public static function basePath(): string
    {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
    }

    public static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . self::basePath();
    }

    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function file(string $key): ?array
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    public static function files(string $key): array
    {
        if (!isset($_FILES[$key])) {
            return [];
        }
        $f = $_FILES[$key];
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $f['name'][$i],
                'type' => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
            ];
        }
        return $out;
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function wantsJson(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }
}
