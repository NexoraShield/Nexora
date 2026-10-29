<?php
/**
 * nexoraStyle — button colors + premium emoji library for nexorabot.
 * Additive only: no existing behavior changes when btn_style / btn_emoji_id
 * are empty and no {emoji:} placeholders are used.
 */

if (!function_exists('nexora_color_palette')) {
    function nexora_color_palette(): array
    {
        return [
            ''        => ['label' => 'پیش‌فرض', 'hex' => '#888888', 'native' => ''],
            'primary' => ['label' => 'آبی', 'hex' => '#3390ec', 'native' => 'primary'],
            'success' => ['label' => 'سبز', 'hex' => '#4fae4e', 'native' => 'success'],
            'danger'  => ['label' => 'قرمز', 'hex' => '#e17076', 'native' => 'danger'],
            'warning' => ['label' => 'نارنجی', 'hex' => '#f59e0b', 'native' => 'danger'],
            'info'    => ['label' => 'آبی روشن', 'hex' => '#38bdf8', 'native' => 'primary'],
            'purple'  => ['label' => 'بنفش', 'hex' => '#8a2be2', 'native' => 'primary'],
            'pink'    => ['label' => 'صورتی', 'hex' => '#ec4899', 'native' => 'danger'],
            'teal'    => ['label' => 'فیروزه‌ای', 'hex' => '#14b8a6', 'native' => 'success'],
            'gray'    => ['label' => 'خاکستری', 'hex' => '#6b7280', 'native' => ''],
        ];
    }
}
if (!function_exists('nexora_color_slugs')) {
    function nexora_color_slugs(): array { return array_keys(nexora_color_palette()); }
}
if (!function_exists('nexora_btn_style')) {
    function nexora_btn_style($style): string
    {
        $style = trim((string) $style);
        $palette = nexora_color_palette();
        if (!isset($palette[$style])) return '';
        return $palette[$style]['native'];
    }
}

if (!function_exists('nexora_btn_emoji_id')) {
    function nexora_btn_emoji_id($raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return '';
        if (preg_match('/^\d{1,32}$/', $raw)) return $raw;
        if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $raw, $m)) return $m[1];
        return '';
    }
}

if (!function_exists('nexora_ensure_style_columns')) {
    function nexora_ensure_style_columns(PDO $pdo): array
    {
        $targets = [
            'product' => [['btn_style', 'VARCHAR(20) NULL'], ['btn_emoji_id', 'VARCHAR(32) NULL']],
            'category' => [['btn_style', 'VARCHAR(20) NULL'], ['btn_emoji_id', 'VARCHAR(32) NULL']],
            'marzban_panel' => [['btn_style', 'VARCHAR(20) NULL'], ['btn_emoji_id', 'VARCHAR(32) NULL']],
        ];
        $added = [];
        foreach ($targets as $table => $cols) {
            foreach ($cols as [$col, $def]) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $check->execute([$table, $col]);
                if ((int) $check->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD `{$col}` {$def}");
                    $added[] = "{$table}.{$col}";
                }
            }
        }
        return $added;
    }
}

if (!function_exists('nexora_ensure_emoji_table')) {
    function nexora_ensure_emoji_table($pdo = null): void
    {
        $pdo = $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
        if (!($pdo instanceof PDO)) return;
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS bot_custom_emoji (id INT UNSIGNED NOT NULL AUTO_INCREMENT, emoji_name VARCHAR(120) NOT NULL, emoji_slug VARCHAR(64) DEFAULT NULL, custom_emoji_id VARCHAR(32) NOT NULL, emoji_utf8 VARCHAR(16) DEFAULT NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0, created_by VARCHAR(32) DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY uq_custom_emoji_id (custom_emoji_id), UNIQUE KEY uq_emoji_slug (emoji_slug), KEY idx_emoji_name (emoji_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS nexora_emoji_map (target_type VARCHAR(32) NOT NULL, target_name VARCHAR(190) NOT NULL, emoji_id VARCHAR(32) NOT NULL, PRIMARY KEY (target_type, target_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (Throwable $e) { error_log('nexora_ensure_emoji_table: ' . $e->getMessage()); }
    }
}

if (!function_exists('nexora_emoji_make_slug')) {
    function nexora_emoji_make_slug(string $name, ?int $excludeId = null): string
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        $slug = function_exists('mb_strtolower') ? mb_strtolower(trim($name), 'UTF-8') : strtolower(trim($name));
        $slug = preg_replace('/[\s]+/u', '_', $slug);
        $slug = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) $slug);
        if ($slug === '' || $slug === null) $slug = 'emoji';
        $slug = function_exists('mb_substr') ? mb_substr($slug, 0, 58, 'UTF-8') : substr($slug, 0, 58);
        if (!($pdo instanceof PDO)) return $slug;
        $base = $slug; $try = 0;
        while ($try < 100) {
            $candidate = $try === 0 ? $base : $base . '_' . $try;
            try {
                $stmt = $pdo->prepare('SELECT id FROM bot_custom_emoji WHERE emoji_slug = ? LIMIT 1');
                $stmt->execute([$candidate]);
                $found = (int) ($stmt->fetchColumn() ?: 0);
            } catch (Throwable $e) { return $candidate; }
            if ($found === 0 || ($excludeId !== null && $found === $excludeId)) return $candidate;
            $try++;
        }
        return $base . '_' . time();
    }
}

if (!function_exists('nexora_emoji_list')) {
    function nexora_emoji_list($pdo = null, bool $fresh = false): array
    {
        static $mem = null;
        if ($mem !== null && !$fresh) return $mem;
        $pdo = $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
        if (!($pdo instanceof PDO)) return [];
        try {
            $stmt = $pdo->query('SELECT * FROM bot_custom_emoji ORDER BY emoji_name ASC, id ASC');
            $mem = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return $mem;
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('nexora_emoji_lookup')) {
    function nexora_emoji_lookup(string $ref, $pdo = null): ?array
    {
        $ref = trim($ref);
        if ($ref === '') return null;
        try { $list = nexora_emoji_list($pdo); } catch (Throwable $e) { return null; }
        if ($list === []) return null;
        if (preg_match('/^#?(\d+)$/', $ref, $num)) {
            foreach ($list as $row) {
                if ((int) ($row['id'] ?? 0) === (int) $num[1]) return $row;
                if (trim((string) ($row['custom_emoji_id'] ?? '')) === $num[1]) return $row;
            }
            return null;
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($ref, 'UTF-8') : strtolower($ref);
        foreach ($list as $row) {
            $slug = function_exists('mb_strtolower') ? mb_strtolower(trim((string) ($row['emoji_slug'] ?? '')), 'UTF-8') : strtolower(trim((string) ($row['emoji_slug'] ?? '')));
            if ($slug !== '' && $slug === $lower) return $row;
        }
        foreach ($list as $row) {
            $name = function_exists('mb_strtolower') ? mb_strtolower(trim((string) ($row['emoji_name'] ?? '')), 'UTF-8') : strtolower(trim((string) ($row['emoji_name'] ?? '')));
            if ($name !== '' && $name === $lower) return $row;
        }
        return null;
    }
}

if (!function_exists('nexora_emoji_placeholder')) {
    function nexora_emoji_placeholder(array $row): string
    {
        $slug = trim((string) ($row['emoji_slug'] ?? ''));
        if ($slug === '') $slug = (string) (int) ($row['id'] ?? 0);
        return ($slug !== '' && $slug !== '0') ? '{emoji:' . $slug . '}' : '';
    }
}

if (!function_exists('nexora_emoji_save')) {
    function nexora_emoji_save(string $name, string $customEmojiId, string $emojiUtf8 = '', ?string $createdBy = null): array
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) return ['ok' => false, 'error' => 'DB unavailable'];
        $name = trim($name);
        $customEmojiId = trim($customEmojiId);
        if ($name === '' || $customEmojiId === '') return ['ok' => false, 'error' => 'نام یا شناسه ایموجی خالی است.'];
        if (!preg_match('/^\d{1,32}$/', $customEmojiId)) return ['ok' => false, 'error' => 'شناسه ایموجی باید عدد باشد.'];
        try {
            $find = $pdo->prepare('SELECT id, emoji_slug FROM bot_custom_emoji WHERE custom_emoji_id = ? LIMIT 1');
            $find->execute([$customEmojiId]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $slug = trim((string) ($existing['emoji_slug'] ?? ''));
                if ($slug === '') $slug = nexora_emoji_make_slug($name, (int) $existing['id']);
                $stmt = $pdo->prepare('UPDATE bot_custom_emoji SET emoji_name = ?, emoji_slug = ?, emoji_utf8 = ? WHERE id = ?');
                $stmt->execute([$name, $slug, $emojiUtf8, (int) $existing['id']]);
                return ['ok' => true, 'id' => (int) $existing['id'], 'slug' => $slug];
            }
            $slug = nexora_emoji_make_slug($name);
            $stmt = $pdo->prepare('INSERT INTO bot_custom_emoji (emoji_name, emoji_slug, custom_emoji_id, emoji_utf8, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $slug, $customEmojiId, $emojiUtf8, time(), $createdBy]);
            return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'slug' => $slug];
        } catch (Throwable $e) {
            error_log('nexora_emoji_save: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'ذخیره در پایگاه داده ناموفق بود.'];
        }
    }
}

if (!function_exists('nexora_emoji_delete')) {
    function nexora_emoji_delete(int $id): bool
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO) || $id <= 0) return false;
        try {
            $stmt = $pdo->prepare('DELETE FROM bot_custom_emoji WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) { return false; }
    }
}

if (!function_exists('nexora_emoji_rename')) {
    function nexora_emoji_rename(int $id, string $name): bool
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        $name = trim($name);
        if (!($pdo instanceof PDO) || $id <= 0 || $name === '') return false;
        try {
            $stmt = $pdo->prepare('UPDATE bot_custom_emoji SET emoji_name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) { return false; }
    }
}

if (!function_exists('nexora_emoji_chip_meta')) {
    function nexora_emoji_chip_meta(array $row): array
    {
        $name = trim((string) ($row['emoji_name'] ?? ''));
        $slug = trim((string) ($row['emoji_slug'] ?? ''));
        $placeholder = nexora_emoji_placeholder($row);
        $label = $name !== '' ? $name : ($slug !== '' ? $slug : 'emoji');
        $titleParts = array_filter([$label, ($slug !== '' && $slug !== $name) ? $slug : '', $placeholder]);
        return ['label' => $label, 'slug' => $slug, 'placeholder' => $placeholder, 'title' => implode(' · ', $titleParts)];
    }
}

if (!function_exists('nexora_resolve_btn_label')) {
    function nexora_resolve_btn_label(string $raw): array
    {
        try {
            if (strpos($raw, '{emoji:') === false) {
                return ['text' => $raw, 'icon' => ''];
            }
            $icon = '';
            $used = false;
            $text = preg_replace_callback('/\{emoji:([^}]+)\}/u', function ($m) use (&$icon, &$used) {
                try { $row = nexora_emoji_lookup(trim($m[1])); } catch (Throwable $e) { return ''; }
                if (!$row || empty($row['custom_emoji_id'])) return '';
                if (!$used) {
                    $used = true;
                    $icon = (string) $row['custom_emoji_id'];
                }
                return '';
            }, $raw);
            $text = trim(preg_replace('/\s{2,}/u', ' ', (string) $text));
            return ['text' => $text, 'icon' => $icon];
        } catch (Throwable $e) {
            return ['text' => $raw, 'icon' => ''];
        }
    }
}

if (!function_exists('nexora_resolve_btn_emoji_field')) {
    function nexora_resolve_btn_emoji_field($raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return '';
        if (preg_match('/^\d{1,32}$/', $raw)) return $raw;
        if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $raw, $m)) return $m[1];
        if (preg_match('/^\{emoji:([^}]+)\}$/u', $raw, $m)) {
            try { $row = nexora_emoji_lookup(trim($m[1])); } catch (Throwable $e) { return ''; }
            if ($row && !empty($row['custom_emoji_id'])) return (string) $row['custom_emoji_id'];
        }
        return '';
    }
}

if (!function_exists('nexora_render_text_emoji')) {
    function nexora_render_text_emoji(string $text): string
    {
        try {
            if (strpos($text, '{emoji:') === false) return $text;
            return (string) preg_replace_callback('/\{emoji:([^}]+)\}/u', function ($m) {
                try { $row = nexora_emoji_lookup(trim($m[1])); } catch (Throwable $e) { return ''; }
                if (!$row || empty($row['custom_emoji_id'])) return '';
                $id = (string) $row['custom_emoji_id'];
                $fb = trim((string) ($row['emoji_utf8'] ?? ''));
                if ($fb === '') $fb = '•';
                return '<tg-emoji emoji-id="' . $id . '">' . $fb . '</tg-emoji>';
            }, $text);
        } catch (Throwable $e) { return $text; }
    }
}

if (!function_exists('nexora_strip_placeholders')) {
    function nexora_strip_placeholders(string $text): string
    {
        try {
            if (strpos($text, '{emoji:') === false) return $text;
            $out = preg_replace('/\{emoji:[^}]+\}/u', '', $text);
            $out = trim(preg_replace('/\s{2,}/u', ' ', (string) $out));
            return $out;
        } catch (Throwable $e) { return $text; }
    }
}

if (!function_exists('nexora_render_markup_emoji')) {
    function nexora_render_markup_emoji(string $json): string
    {
        try {
            if (strpos($json, '{emoji:') === false) return $json;
            $markup = json_decode($json, true);
            if (!is_array($markup)) return $json;
            $changed = false;
            foreach (['keyboard', 'inline_keyboard'] as $mkey) {
                if (!isset($markup[$mkey]) || !is_array($markup[$mkey])) continue;
                foreach ($markup[$mkey] as $rk => $row) {
                    if (!is_array($row)) continue;
                    foreach ($row as $bk => $btn) {
                        if (!is_array($btn) || !isset($btn['text']) || !is_string($btn['text'])) continue;
                        if (strpos($btn['text'], '{emoji:') === false) continue;
                        $r = nexora_resolve_btn_label($btn['text']);
                        $btn['text'] = $r['text'] !== '' ? $r['text'] : $btn['text'];
                        if ($r['icon'] !== '' && !isset($btn['icon_custom_emoji_id'])) {
                            $btn['icon_custom_emoji_id'] = $r['icon'];
                        }
                        $markup[$mkey][$rk][$bk] = $btn;
                        $changed = true;
                    }
                }
            }
            if (!$changed) return $json;
            $enc = json_encode($markup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $enc === false ? $json : $enc;
        } catch (Throwable $e) { return $json; }
    }
}

if (!function_exists('nexora_strip_markup_placeholders')) {
    function nexora_strip_markup_placeholders(string $json): string
    {
        try {
            if (strpos($json, '{emoji:') === false) return $json;
            $markup = json_decode($json, true);
            if (!is_array($markup)) return $json;
            $changed = false;
            foreach (['keyboard', 'inline_keyboard'] as $mkey) {
                if (!isset($markup[$mkey]) || !is_array($markup[$mkey])) continue;
                foreach ($markup[$mkey] as $rk => $row) {
                    if (!is_array($row)) continue;
                    foreach ($row as $bk => $btn) {
                        if (!is_array($btn) || !isset($btn['text']) || !is_string($btn['text'])) continue;
                        if (strpos($btn['text'], '{emoji:') === false) continue;
                        $btn['text'] = nexora_strip_placeholders($btn['text']);
                        unset($btn['icon_custom_emoji_id']);
                        $markup[$mkey][$rk][$bk] = $btn;
                        $changed = true;
                    }
                }
            }
            if (!$changed) return $json;
            $enc = json_encode($markup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $enc === false ? $json : $enc;
        } catch (Throwable $e) { return $json; }
    }
}

if (!function_exists('nexora_style_btn')) {
    function nexora_style_btn($text, $callback_data, $row = []): array
    {
        try {
            if (!is_array($row)) $row = [];
            $resolved = nexora_resolve_btn_label((string) $text);
            $label = $resolved['text'] !== '' ? $resolved['text'] : (string) $text;
            $btn = ['text' => $label, 'callback_data' => (string) $callback_data];
            if ($resolved['icon'] !== '') {
                $btn['icon_custom_emoji_id'] = $resolved['icon'];
            } else {
                $emoji = nexora_resolve_btn_emoji_field($row['btn_emoji_id'] ?? '');
                if ($emoji !== '') $btn['icon_custom_emoji_id'] = $emoji;
            }
            $style = nexora_btn_style($row['btn_style'] ?? '');
            if ($style !== '') $btn['style'] = $style;
            return $btn;
        } catch (Throwable $e) {
            return ['text' => (string) $text, 'callback_data' => (string) $callback_data];
        }
    }
}

if (!function_exists('nexora_menu_emoji_map')) {
    function nexora_menu_emoji_map($pdo = null): array
    {
        try {
            $pdo = $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
            if (!($pdo instanceof PDO)) return [];
            $stmt = $pdo->query("SELECT NamePay, ValuePay FROM PaySetting WHERE NamePay LIKE 'nx\\_emoji\\_%' ESCAPE '\\'");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $map = [];
            foreach ($rows as $r) {
                $k = trim((string) ($r['NamePay'] ?? ''));
                $v = trim((string) ($r['ValuePay'] ?? ''));
                if (strpos($k, 'nx_emoji_') === 0 && $v !== '') {
                    $map[substr($k, 9)] = $v;
                }
            }
            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('nexora_apply_menu_emoji')) {
    function nexora_apply_menu_emoji(array $rows, array $labels = []): array
    {
        try {
            $flip = [];
            foreach ($labels as $k => $v) {
                if (is_string($v) && $v !== '' && !isset($flip[$v])) {
                    $flip[$v] = $k;
                }
            }
            $map = nexora_menu_emoji_map();
            if ($map === []) return $rows;
            foreach ($rows as $rk => $row) {
                if (!is_array($row)) continue;
                foreach ($row as $bk => $btn) {
                    if (!is_array($btn) || !isset($btn['text']) || !is_string($btn['text'])) continue;
                    $key = $flip[$btn['text']] ?? null;
                    if ($key === null || !isset($map[$key])) continue;
                    $ref = $map[$key];
                    $icon = '';
                    $char = '';
                    if (preg_match('/^\d{1,32}$/', $ref)) {
                        $icon = $ref;
                    } elseif (preg_match('/^\{emoji:([^}]+)\}$/u', $ref, $mm)) {
                        try { $erow = nexora_emoji_lookup(trim($mm[1])); } catch (Throwable $e) { $erow = null; }
                        if ($erow && !empty($erow['custom_emoji_id'])) {
                            $icon = (string) $erow['custom_emoji_id'];
                            $char = trim((string) ($erow['emoji_utf8'] ?? ''));
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    if (isset($btn['callback_data']) || isset($btn['url'])) {
                        if ($icon !== '' && !isset($btn['icon_custom_emoji_id'])) {
                            $btn['icon_custom_emoji_id'] = $icon;
                            $rows[$rk][$bk] = $btn;
                        }
                    } elseif ($char !== '' && strpos($btn['text'], $char) !== 0) {
                        $btn['text'] = $char . ' ' . $btn['text'];
                        $rows[$rk][$bk] = $btn;
                    }
                }
            }
            return $rows;
        } catch (Throwable $e) {
            return $rows;
        }
    }
}

if (!function_exists('nexora_agent_setpass')) {
    function nexora_agent_setpass(string $id, string $pass): string
    {
        $id = trim($id);
        if (!preg_match('/^\d+$/', $id)) return 'آیدی عددی معتبر نیست.';
        if (mb_strlen($pass) < 4) return 'رمز حداقل ۴ کاراکتر باشد.';
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) return 'دیتابیس در دسترس نیست.';
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_auth (id_user VARCHAR(32) NOT NULL PRIMARY KEY, pass_hash VARCHAR(255) NOT NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            if (!is_string($hash)) return 'خطا در ساخت رمز.';
            $stmt = $pdo->prepare('INSERT INTO agent_auth (id_user, pass_hash, created_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE pass_hash = VALUES(pass_hash), created_at = VALUES(created_at)');
            $stmt->execute([$id, $hash, time()]);
            return '';
        } catch (Throwable $e) {
            error_log('nexora_agent_setpass: ' . $e->getMessage());
            return 'خطای دیتابیس.';
        }
    }
}

// === Runtime application of nx_bb_* (color+emoji for keyboard-group buttons) ===
if (!function_exists('nexora_bb_map')) {
    function nexora_bb_map(): array
    {
        try {
            global $pdo;
            $pdo = $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
            if (!($pdo instanceof PDO)) return ['emoji' => [], 'style' => []];
            $rows = $pdo->query("SELECT NamePay, ValuePay FROM PaySetting WHERE NamePay LIKE 'nx_bb_emoji_%' OR NamePay LIKE 'nx_bb_style_%'")->fetchAll(PDO::FETCH_ASSOC);
            $emoji = []; $style = [];
            foreach ($rows as $r) {
                $k = trim((string) ($r['NamePay'] ?? ''));
                $v = trim((string) ($r['ValuePay'] ?? ''));
                if ($v === '') continue;
                if (strpos($k, 'nx_bb_emoji_') === 0) $emoji[substr($k, 12)] = $v;
                elseif (strpos($k, 'nx_bb_style_') === 0) $style[substr($k, 12)] = $v;
            }
            return ['emoji' => $emoji, 'style' => $style];
        } catch (Throwable $e) { return ['emoji' => [], 'style' => []]; }
    }
}
if (!function_exists('nexora_apply_bb_style')) {
    function nexora_apply_bb_style(array $rows, array $flip = []): array
    {
        try {
            $map = nexora_bb_map();
            if ($map['emoji'] === [] && $map['style'] === []) return $rows;
            foreach ($rows as $rk => $row) {
                if (!is_array($row)) continue;
                foreach ($row as $bk => $btn) {
                    if (!is_array($btn) || !isset($btn['text']) || !is_string($btn['text'])) continue;
                    $key = $flip[$btn['text']] ?? null;
                    if ($key === null && $btn['text'] !== '') {
                        // Tolerant prefix-match fallback: nexora_apply_menu_emoji()
                        // prepends a leading emoji glyph to non-inline menu buttons
                        // (e.g. "🎯 خانه") before this runs, which defeats the exact
                        // flip lookup. Strip a leading run of symbol/mark/whitespace
                        // so the plain label still resolves to its nx_bb_* override.
                        $stripped = preg_replace('/^[\p{S}\p{M}\s]+/u', '', $btn['text']);
                        if ($stripped !== $btn['text'] && $stripped !== '') {
                            $key = $flip[$stripped] ?? null;
                        }
                    }
                    if ($key === null) continue;
                    $changed = false;
                    if (isset($map['emoji'][$key])) {
                        $ref = $map['emoji'][$key];
                        $icon = '';
                        if (preg_match('/^\d{1,32}$/', $ref)) $icon = $ref;
                        elseif (preg_match('/^\{emoji:([^}]+)\}$/', $ref, $m)) {
                            try { $erow = nexora_emoji_lookup(trim($m[1])); } catch (Throwable $e) { $erow = null; }
                            if ($erow && !empty($erow['custom_emoji_id'])) $icon = (string) $erow['custom_emoji_id'];
                        }
                        if ($icon !== '' && !isset($btn['icon_custom_emoji_id'])) {
                            $btn['icon_custom_emoji_id'] = $icon; $changed = true;
                        }
                    }
                    if (isset($map['style'][$key])) {
                        $native = nexora_btn_style($map['style'][$key]);
                        if ($native !== '' && !isset($btn['style'])) {
                            $btn['style'] = $native; $changed = true;
                        }
                    }
                    if ($changed) $rows[$rk][$bk] = $btn;
                }
            }
            return $rows;
        } catch (Throwable $e) { return $rows; }
    }
}
