<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
$file = $target . '/admin.php';
if ($target === '' || !is_file($file)) { fwrite(STDERR, "admin.php not found in target.\n"); exit(1); }
$code = file_get_contents($file);
if ($code === false) { fwrite(STDERR, "Cannot read admin.php.\n"); exit(1); }
if (strpos($code, '/savemoji') !== false) { echo "admin.php already patched — nothing to do.\n"; exit(0); }
$errors = [];
$check = function (string $label, int $count, int $expected) use (&$errors) { if ($count !== $expected) { $errors[] = "{$label}: found {$count}, expected {$expected}"; } else { echo "OK: {$label} ({$count})\n"; } };
$anchor = '} elseif ($text == $textbotlang[\'keyboard\'][\'channelSettings\'] && $adminrulecheck[\'rule\'] == "administrator") {';
$c = substr_count($code, $anchor);
$check('channelSettings anchor', $c, 1);
if ($errors !== []) { fwrite(STDERR, "Anchor check FAILED:\n - " . implode("\n - ", $errors) . "\n"); fwrite(STDERR, "Nothing was changed. Your Mirzabot version differs — aborting safely.\n"); exit(1); }
if (!$apply) { echo "Dry run OK — all anchors match. Re-run with --apply.\n"; exit(0); }
$insert = <<<'PHP'
} elseif (strpos($text, '/savemoji') === 0 && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('mirza_emoji_save') && preg_match('#<tg-emoji\b[^>]*\bemoji-id\s*=\s*"(\d+)"#isu', $text, $sv_mm)) {
        $sv_name = trim(preg_replace('#<tg-emoji\b[^>]*>.*?</tg-emoji>#isu', '', $text));
        $sv_name = trim(preg_replace('/^\/savemoji\s*/u', '', $sv_name));
        if ($sv_name === '') $sv_name = 'emoji';
        $sv_fb = '';
        if (preg_match('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', $text, $sv_mf)) $sv_fb = $sv_mf[1];
        $sv_res = mirza_emoji_save($sv_name, $sv_mm[1], $sv_fb, (string) $from_id);
        if (!empty($sv_res['ok'])) sendmessage($from_id, "ذخیره شد: {emoji:" . $sv_res['slug'] . "}\nاز این کد در نام محصول/دکمه/متن‌ها استفاده کنید.", $backadmin, 'HTML');
        else sendmessage($from_id, "خطا: " . ($sv_res['error'] ?? '؟'), $backadmin, 'HTML');
    } else {
        sendmessage($from_id, "مثال: /savemoji kharid + ایموجی پرمیوم (در یک پیام)", $backadmin, 'HTML');
    }
} elseif ($text == "ایموجی پرمیوم" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "نام انگلیسی برای ایموجی بفرستید (مثال: kharid).\n\nیا یک‌مرحله‌ای: /savemoji نام + ایموجی (در یک پیام)", $backadmin, 'HTML');
    step('savemoji_name', $from_id);
} elseif ($user['step'] == "savemoji_name" && $adminrulecheck['rule'] == "administrator") {
    $sv_name = trim(preg_replace('#<tg-emoji\b[^>]*>.*?</tg-emoji>#isu', '', $text));
    if ($sv_name === '' || mb_strlen($sv_name) > 60) {
        sendmessage($from_id, "نام معتبر بفرستید (متن کوتاه انگلیسی).", $backadmin, 'HTML');
    } else {
        savedata("save", "emoji_name", $sv_name);
        sendmessage($from_id, "حالا ایموجی پرمیوم را بفرستید (یک ایموجی از پک پرمیوم).", $backadmin, 'HTML');
        step('savemoji_emoji', $from_id);
    }
} elseif ($user['step'] == "savemoji_emoji" && $adminrulecheck['rule'] == "administrator") {
    if (function_exists('mirza_emoji_save') && preg_match('#<tg-emoji\b[^>]*\bemoji-id\s*=\s*"(\d+)"#isu', $text, $sv_mm)) {
        $sv_ud = json_decode((string) ($user['Processing_value'] ?? ''), true);
        $sv_name = trim((string) (is_array($sv_ud) ? ($sv_ud['emoji_name'] ?? '') : ''));
        if ($sv_name === '') $sv_name = 'emoji';
        $sv_fb = '';
        if (preg_match('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', $text, $sv_mf)) $sv_fb = $sv_mf[1];
        $sv_res = mirza_emoji_save($sv_name, $sv_mm[1], $sv_fb, (string) $from_id);
        if (!empty($sv_res['ok'])) sendmessage($from_id, "ذخیره شد: {emoji:" . $sv_res['slug'] . "}\nاز این کد در نام محصول/دکمه/متن‌ها استفاده کنید.", $backadmin, 'HTML');
        else sendmessage($from_id, "خطا: " . ($sv_res['error'] ?? '؟'), $backadmin, 'HTML');
        step('home', $from_id);
    } else {
        sendmessage($from_id, "ایموجی پرمیوم پیدا نشد. یک ایموجی پرمیوم بفرستید.", $backadmin, 'HTML');
    }
} elseif (strpos($text, '/agentpass') === 0 && $adminrulecheck['rule'] == "administrator") {
    $ap_parts = preg_split('/\s+/', trim($text), 3);
    if (count($ap_parts) === 3 && function_exists('mirza_agent_setpass')) {
        $ap_err = mirza_agent_setpass($ap_parts[1], $ap_parts[2]);
        if ($ap_err === '') sendmessage($from_id, "رمز پنل نمایندگی برای {$ap_parts[1]} تنظیم شد.", $backadmin, 'HTML');
        else sendmessage($from_id, "خطا: " . $ap_err, $backadmin, 'HTML');
    } else {
        sendmessage($from_id, "مثال: /agentpass 123456789 ramz123", $backadmin, 'HTML');
    }
} elseif ($text == $textbotlang['keyboard']['channelSettings'] && $adminrulecheck['rule'] == "administrator") {
PHP;
$code = str_replace($anchor, $insert, $code);
file_put_contents($file, $code);
echo "Patched admin.php.\n";
