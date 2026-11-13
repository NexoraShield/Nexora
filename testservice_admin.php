<?php

// ---------------------------------------------------------------------------
// The test services of the bot.
//
// The administrator defines them here exactly like in the web panel: name,
// volume, duration, panel, inbound, note, colour, premium emoji and the
// status. Every step can be skipped with a "-".
// ---------------------------------------------------------------------------
if (!function_exists('nexora_test_wizard_state')) {
    function nexora_test_wizard_state($user): array
    {
        $state = json_decode((string) ($user['Processing_value'] ?? ''), true);
        return is_array($state) && isset($state['step']) ? $state : [];
    }
}

if (!function_exists('nexora_test_wizard_save')) {
    function nexora_test_wizard_save(array $state): void
    {
        global $pdo;
        update("user", "Processing_value", json_encode($state, JSON_UNESCAPED_UNICODE), "id", $GLOBALS['from_id']);
    }
}

if (!function_exists('nexora_test_wizard_next')) {
    /**
     * The next question of the wizard.
     */
    function nexora_test_wizard_next(array $state): array
    {
        global $pdo, $textbotlang;
        $data = is_array($state['data'] ?? null) ? $state['data'] : [];
        $id = (int) ($state['id'] ?? 0);

        switch ((string) ($state['step'] ?? 'name')) {
            case 'volume':
                return [
                    'step' => 'volume',
                    'text' => "💾 حجم سرویس تست را بفرستید.\n\nمثال: <code>512 مگابایت</code> یا <code>1 گیگابایت</code>\nبرای نگه‌داشتن مقدار فعلی <code>-</code> بفرستید.\n\nمقدار فعلی: " . nexora_volume_text($data['Volume_constraint'] ?? 0),
                    'keyboard' => null,
                ];
            case 'time':
                return [
                    'step' => 'time',
                    'text' => "⏳ مدت سرویس تست را به ساعت بفرستید.\nبرای نگه‌داشتن مقدار فعلی <code>-</code> بفرستید.\n\nمقدار فعلی: " . (int) ($data['Service_time'] ?? 0) . " ساعت",
                    'keyboard' => null,
                ];
            case 'panel':
                $rows = [];
                foreach (select("marzban_panel", "*", null, null, "fetchAll") as $panel) {
                    $code = (string) ($panel['code_panel'] ?? '');
                    if ($code === '') {
                        continue;
                    }
                    $rows[] = [['text' => (string) ($panel['name_panel'] ?? ''), 'callback_data' => 'adv_test_panel_' . $code]];
                }
                return [
                    'step' => 'panel',
                    'text' => "🖥 پنل سرویس تست را انتخاب کنید.\n\nپنل فعلی: " . (string) ($data['Location'] ?? '-'),
                    'keyboard' => $rows ? json_encode(['inline_keyboard' => $rows]) : null,
                ];
            case 'inbound':
                $inbounds = nexora_panel_inbounds($pdo, (string) ($data['Location'] ?? ''));
                $rows = [[['text' => 'پیش‌فرض پنل', 'callback_data' => 'adv_test_inb_default']]];
                foreach ($inbounds as $inbound) {
                    $rows[] = [['text' => (string) $inbound, 'callback_data' => 'adv_test_inb_' . $inbound]];
                }
                return [
                    'step' => 'inbound',
                    'text' => "🧬 اینباند سرویس تست را انتخاب کنید.\n\nاگر پیش‌فرض پنل مناسب است، دکمهٔ اول را بزنید.",
                    'keyboard' => json_encode(['inline_keyboard' => $rows]),
                ];
            case 'note':
                return [
                    'step' => 'note',
                    'text' => "📝 توضیحات سرویس تست (اختیاری).\nبرای نگه‌داشتن مقدار فعلی <code>-</code> بفرستید.\n\nتوضیح فعلی: " . (string) ($data['note'] ?? '-'),
                    'keyboard' => null,
                ];
            case 'color':
                $rows = [];
                foreach (nexora_color_palette() as $slug => $info) {
                    $rows[] = [['text' => ($slug === '' ? 'پیش‌فرض' : ($info['label'] ?? $slug)), 'callback_data' => 'adv_test_color_' . $slug]];
                }
                return [
                    'step' => 'color',
                    'text' => "🎨 رنگ دکمهٔ سرویس تست را انتخاب کنید.",
                    'keyboard' => json_encode(['inline_keyboard' => array_chunk($rows, 3)]),
                ];
            case 'status':
                return [
                    'step' => 'status',
                    'text' => "👁 وضعیت سرویس تست را انتخاب کنید.",
                    'keyboard' => json_encode(['inline_keyboard' => [
                        [['text' => '✅ فعال', 'callback_data' => 'adv_test_status_active']],
                        [['text' => '⛔️ پنهان', 'callback_data' => 'adv_test_status_hidden']],
                    ]]),
                ];
            default:
                return [
                    'step' => 'done',
                    'text' => "⭕️ یکی از گزینه‌های بالا را انتخاب کنید.",
                    'keyboard' => null,
                ];
        }    }
}

if (!function_exists('nexora_test_wizard_save_product')) {
    /**
     * Writes the test service into the database.
     */
    function nexora_test_wizard_save_product(PDO $pdo, array $state): int
    {
        $data = is_array($state['data'] ?? null) ? $state['data'] : [];
        $id = (int) ($state['id'] ?? 0);
        $name = trim((string) ($data['name_product'] ?? ''));
        if ($name === '') {
            return 0;
        }
        $inbounds = $data['inbounds'] ?? null;
        $inboundsValue = (is_array($inbounds) && $inbounds) ? json_encode(array_values($inbounds)) : null;
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE product SET name_product=?,Volume_constraint=?,Service_time=?,Location=?,note=?,inbounds=?,btn_style=?,btn_emoji_id=?,status_product=?,is_test=1 WHERE id=? AND is_test=1");
            $st->execute([
                $name,
                (float) ($data['Volume_constraint'] ?? 0),
                (int) ($data['Service_time'] ?? 0),
                (string) ($data['Location'] ?? ''),
                (string) ($data['note'] ?? ''),
                $inboundsValue,
                ($data['btn_style'] ?? '') !== '' ? $data['btn_style'] : null,
                ($data['btn_emoji_id'] ?? '') !== '' ? $data['btn_emoji_id'] : null,
                ((string) ($data['status_product'] ?? 'active')) === 'hidden' ? 'hidden' : 'active',
                $id,
            ]);
            return $id;
        }
        $code = bin2hex(random_bytes(2));
        $st = $pdo->prepare("INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,status_product,is_test,btn_style,btn_emoji_id,inbounds) VALUES (?,?,?,?,?,?,?,'no_reset',?,?,'{}','0','active',1,?,?,?)");
        $st->execute([
            $name,
            $code,
            0,
            (float) ($data['Volume_constraint'] ?? 0),
            (int) ($data['Service_time'] ?? 0),
            (string) ($data['Location'] ?? ''),
            'f',
            (string) ($data['note'] ?? ''),
            '',
            ($data['btn_style'] ?? '') !== '' ? $data['btn_style'] : null,
            ($data['btn_emoji_id'] ?? '') !== '' ? $data['btn_emoji_id'] : null,
            $inboundsValue,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE product SET display_order = id WHERE id = ?")->execute([$newId]);
        return $newId;
    }
}
