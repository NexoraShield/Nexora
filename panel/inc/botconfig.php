<?php

// ---------------------------------------------------------------------------
// The settings of the bot itself: the domain of the installation and the
// administrator that owns the bot.
//
// Both values live in config.php, so they are written there. The file is
// backed up before every change.
// ---------------------------------------------------------------------------
if (!function_exists('panel_config_path')) {
    function panel_config_path(): string
    {
        return dirname(__DIR__, 2) . '/config.php';
    }
}

if (!function_exists('panel_config_values')) {
    /**
     * The values of config.php that the panel manages.
     */
    function panel_config_values(): array
    {
        $values = [];
        $file = panel_config_path();
        if (!is_file($file)) {
            return $values;
        }
        $code = (string) @file_get_contents($file);
        foreach (['domainhosts', 'adminnumber', 'usernamebot'] as $key) {
            if (preg_match('/\$' . $key . '\s*=\s*([\'"])(.*?)\1\s*;/s', $code, $m)) {
                $values[$key] = $m[2];
            }
        }
        return $values;
    }
}

if (!function_exists('panel_config_write')) {
    /**
     * Writes new values into config.php. Only the given keys are touched.
     */
    function panel_config_write(array $values): array
    {
        $file = panel_config_path();
        if (!is_file($file) || !is_writable($file)) {
            return ['ok' => false, 'message' => 'فایل config.php قابل نوشتن نیست. دسترسی فایل را بررسی کنید.'];
        }
        $code = (string) @file_get_contents($file);
        $backup = $file . '.bak';
        @copy($file, $backup);
        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)) {
                continue;
            }
            $replacement = '$' . $key . ' = ' . var_export((string) $value, true) . ';';
            $new = preg_replace('/\$' . $key . '\s*=\s*([\'"]).*?\1\s*;/s', $replacement, $code, 1, $count);
            if ($new !== null && $count > 0) {
                $code = $new;
            }
        }
        if (@file_put_contents($file, $code) === false) {
            return ['ok' => false, 'message' => 'ذخیره فایل config.php ناموفق بود.'];
        }
        return ['ok' => true, 'message' => 'فایل config.php به‌روزرسانی شد.'];
    }
}

if (!function_exists('panel_clean_domain')) {
    /**
     * A domain without a scheme, a path and a trailing slash.
     */
    function panel_clean_domain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^[a-zA-Z]+://#', '', (string) $domain);
        $domain = trim((string) preg_replace('#/.*$#', '', (string) $domain));
        $domain = rtrim((string) $domain, '/');
        return (string) $domain;
    }
}

if (!function_exists('panel_clean_user_id')) {
    function panel_clean_user_id(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        if (preg_match('/^-?\d+$/', $id)) {
            return ltrim($id, '-');
        }
        return '';
    }
}
