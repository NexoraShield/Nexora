<?php

// ---------------------------------------------------------------------------
// Sets the inbound / protocol of a panel with the data of an account that
// already exists inside that panel. The same logic is used by the bot
// ("تنظیم پروتکل و اینباند") and by the web panel (product form).
// ---------------------------------------------------------------------------
if (!function_exists('nexora_panel_protocol_helper_bootstrap')) {
    function nexora_panel_protocol_helper_bootstrap(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $bootstrap = __DIR__ . '/panels/bootstrap.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }
    }
}

if (!function_exists('nexora_panel_protocol_error_text')) {
    function nexora_panel_protocol_error_text($error): string
    {
        $error = is_array($error) ? ($error['msg'] ?? json_encode($error)) : (string) $error;
        $decoded = json_decode((string) $error, true);
        if (is_array($decoded)) {
            $error = (string) ($decoded['message'] ?? $decoded['msg'] ?? $error);
        }
        return trim((string) $error) !== '' ? (string) $error : 'خطای نامشخص';
    }
}

if (!function_exists('nexora_panel_protocol_clear_cache')) {
    function nexora_panel_protocol_clear_cache(): void
    {
        if (function_exists('clearSelectCache')) {
            clearSelectCache('marzban_panel');
        }
        $dir = __DIR__ . '/storage/cache';
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/pc_marzban_panel_*.json') as $file) {
            @unlink((string) $file);
        }
    }
}

// $username = the name of an account that already exists inside the panel.
// Returns ['ok' => bool, 'message' => string].
if (!function_exists('nexora_apply_panel_protocol')) {
    function nexora_apply_panel_protocol(PDO $pdo, string $panelName, string $username): array
    {
        $result = nexora_apply_panel_protocol_inner($pdo, $panelName, $username);
        // the panel is read from the cache, so it has to be cleared
        nexora_panel_protocol_clear_cache();
        return $result;
    }
}

if (!function_exists('nexora_apply_panel_protocol_inner')) {
    function nexora_apply_panel_protocol_inner(PDO $pdo, string $panelName, string $username): array
    {
        $panelName = trim($panelName);
        $username = trim($username);
        if ($panelName === '') {
            return ['ok' => false, 'message' => 'پنل انتخاب نشده است.'];
        }
        if ($username === '') {
            return ['ok' => false, 'message' => 'نام کاربری اکانت داخل پنل را وارد کنید.'];
        }
        nexora_panel_protocol_helper_bootstrap();

        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1");
        $stmt->execute([$panelName]);
        $panel = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$panel) {
            return ['ok' => false, 'message' => 'این پنل در دیتابیس پیدا نشد.'];
        }

        $type = (string) ($panel['type'] ?? '');

        try {
            if ($type === 'marzban') {
                if (($panel['version_panel'] ?? '1') === '1') {
                    $out = getuser($username, $panelName);
                    if (!empty($out['error'])) {
                        return ['ok' => false, 'message' => nexora_panel_protocol_error_text($out['error'])];
                    }
                    if (!empty($out['status']) && (int) $out['status'] !== 200) {
                        return ['ok' => false, 'message' => 'کد خطای پنل: ' . (int) $out['status']];
                    }
                    $data = json_decode((string) $out['body'], true);
                    if (!is_array($data) || !isset($data['group_ids'])) {
                        return ['ok' => false, 'message' => 'اکانت «' . $username . '» در این پنل پیدا نشد.'];
                    }
                    $up = $pdo->prepare("UPDATE marzban_panel SET inbounds = ? WHERE name_panel = ?");
                    $up->execute([json_encode($data['group_ids']), $panelName]);
                    return ['ok' => true, 'message' => 'پروتکل و اینباند پنل با اکانت «' . $username . '» تنظیم شد.'];
                }
                $out = getuser($username, $panelName);
                if (!empty($out['error'])) {
                    return ['ok' => false, 'message' => nexora_panel_protocol_error_text($out['error'])];
                }
                if (!empty($out['status']) && (int) $out['status'] !== 200) {
                    return ['ok' => false, 'message' => 'کد خطای پنل: ' . (int) $out['status']];
                }
                $data = json_decode((string) $out['body'], true);
                if (!is_array($data) || !isset($data['proxies'])) {
                    return ['ok' => false, 'message' => 'اکانت «' . $username . '» در این پنل پیدا نشد.'];
                }
                foreach ($data['proxies'] as $key => &$value) {
                    if ($key === 'shadowsocks' || $key === 'trojan') {
                        unset($data['proxies'][$key]['password']);
                    } else {
                        unset($data['proxies'][$key]['id']);
                    }
                    if (count($data['proxies'][$key]) === 0) {
                        $data['proxies'][$key] = new stdClass();
                    }
                }
                unset($value);
                $up = $pdo->prepare("UPDATE marzban_panel SET inbounds = ?, proxies = ? WHERE name_panel = ?");
                $up->execute([json_encode($data['inbounds'] ?? []), json_encode($data['proxies'], true), $panelName]);
                return ['ok' => true, 'message' => 'پروتکل و اینباند پنل با اکانت «' . $username . '» تنظیم شد.'];
            }

            if ($type === 'x-ui_single') {
                $out = get_clinets($username, $panel);
                if (!empty($out['error'])) {
                    return ['ok' => false, 'message' => nexora_panel_protocol_error_text($out['error'])];
                }
                if (!empty($out['status']) && (int) $out['status'] !== 200) {
                    return ['ok' => false, 'message' => 'کد خطای پنل: ' . (int) $out['status']];
                }
                $data = json_decode((string) $out['body'], true);
                if (!is_array($data) || empty($data['success'])) {
                    return ['ok' => false, 'message' => 'اکانت «' . $username . '» در این پنل پیدا نشد.'];
                }
                $up = $pdo->prepare("UPDATE marzban_panel SET inbounds = ? WHERE name_panel = ?");
                $up->execute([json_encode($data['obj']['inboundIds'] ?? []), $panelName]);
                return ['ok' => true, 'message' => 'پروتکل و اینباند پنل با اکانت «' . $username . '» تنظیم شد.'];
            }

            if ($type === 'wg_mate') {
                $data = getuser_wgmate($username, $panelName)['user'] ?? null;
                if (!is_array($data)) {
                    return ['ok' => false, 'message' => 'اکانت «' . $username . '» در این پنل پیدا نشد.'];
                }
                $up = $pdo->prepare("UPDATE marzban_panel SET proxies = ? WHERE name_panel = ?");
                $up->execute([json_encode(['protocols' => $data['protocols'] ?? [], 'serverId' => $data['serverId'] ?? null]), $panelName]);
                return ['ok' => true, 'message' => 'پروتکل و اینباند پنل با اکانت «' . $username . '» تنظیم شد.'];
            }

            if ($type === 'nexora') {
                $data = getuser_nexora($username, $panelName)['user'] ?? null;
                if (!is_array($data)) {
                    return ['ok' => false, 'message' => 'اکانت «' . $username . '» در این پنل پیدا نشد.'];
                }
                $up = $pdo->prepare("UPDATE marzban_panel SET proxies = ? WHERE name_panel = ?");
                $up->execute([json_encode([
                    'templateIds' => $data['templateIds'] ?? [],
                    'allTemplates' => $data['allTemplates'] ?? [],
                    'group' => $data['group'] ?? '',
                ]), $panelName]);
                return ['ok' => true, 'message' => 'پروتکل و اینباند پنل با اکانت «' . $username . '» تنظیم شد.'];
            }

            if ($type === 'rebecca') {
                $data = json_decode((string) getuser_rebecca($username, $panelName)['body'], true);
                if (!is_array($data) || !isset($data['service_id'])) {
                    return ['ok' => false, 'message' => 'اکانت «' . $username . '» در این پنل پیدا نشد.'];
                }
                $up = $pdo->prepare("UPDATE marzban_panel SET proxies = ? WHERE name_panel = ?");
                $up->execute([json_encode([$data['service_id']]), $panelName]);
                return ['ok' => true, 'message' => 'پروتکل و اینباند پنل با اکانت «' . $username . '» تنظیم شد.'];
            }

            if ($type === 'ibsng' || $type === 'mikrotik') {
                $up = $pdo->prepare("UPDATE marzban_panel SET proxies = ? WHERE name_panel = ?");
                $up->execute([$username, $panelName]);
                return ['ok' => true, 'message' => 'نام گروه پنل با «' . $username . '» تنظیم شد.'];
            }
        } catch (Throwable $e) {
            error_log('nexora_apply_panel_protocol: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }

        return ['ok' => false, 'message' => 'این نوع پنل از تنظیم پروتکل با اکانت پشتیبانی نمی‌کند.'];
    }
}