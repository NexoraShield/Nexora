<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
$file = $target . '/botapi.php';
if ($target === '' || !is_file($file)) { fwrite(STDERR, "botapi.php not found in target.\n"); exit(1); }
$code = file_get_contents($file);
if ($code === false) { fwrite(STDERR, "Cannot read botapi.php.\n"); exit(1); }
if (strpos($code, 'nexora_render_text_emoji') !== false) { echo "botapi.php already patched — nothing to do.\n"; exit(0); }
$errors = [];
$check = function (string $label, int $count, int $expected) use (&$errors) { if ($count !== $expected) { $errors[] = "{$label}: found {$count}, expected {$expected}"; } else { echo "OK: {$label} ({$count})\n"; } };
$anchorHas = "if (isset(\$datas[\$key]) && is_string(\$datas[\$key]) && stripos(\$datas[\$key], '<tg-emoji') !== false) {";
$c = substr_count($code, $anchorHas);
$check('payloadHasCustomEmoji condition', $c, 1);
$anchorApply = <<<'PHP'
function applyCustomEmojiPayload(array $datas)
{
    if (!payloadRendersHtml($datas)) {
        foreach (['text', 'caption'] as $key) {
            if (isset($datas[$key]) && is_string($datas[$key])) {
                $datas[$key] = stripCustomEmojiTags($datas[$key]);
            }
        }
    }
    if (isset($datas['reply_markup'])) {
        $datas['reply_markup'] = applyCustomEmojiToMarkup($datas['reply_markup']);
    }
    return $datas;
}
PHP;
$c = substr_count($code, $anchorApply);
$check('applyCustomEmojiPayload', $c, 1);
$anchorStrip = <<<'PHP'
function stripCustomEmojiPayload(array $datas)
{
    foreach (['text', 'caption'] as $key) {
        if (isset($datas[$key]) && is_string($datas[$key])) {
            $datas[$key] = stripCustomEmojiTags($datas[$key]);
        }
    }
    if (isset($datas['reply_markup'])) {
        $datas['reply_markup'] = stripCustomEmojiFromMarkup($datas['reply_markup']);
    }
    return $datas;
}
PHP;
$c = substr_count($code, $anchorStrip);
$check('stripCustomEmojiPayload', $c, 1);
if ($errors !== []) { fwrite(STDERR, "Anchor check FAILED:\n - " . implode("\n - ", $errors) . "\n"); fwrite(STDERR, "Nothing was changed. Your nexorabot version differs — aborting safely.\n"); exit(1); }
if (!$apply) { echo "Dry run OK — all anchors match. Re-run with --apply.\n"; exit(0); }
$code = str_replace($anchorHas, "if (isset(\$datas[\$key]) && is_string(\$datas[\$key]) && (stripos(\$datas[\$key], '<tg-emoji') !== false || strpos(\$datas[\$key], '{emoji:') !== false)) {", $code);
$newApply = <<<'PHP'
function applyCustomEmojiPayload(array $datas)
{
    if (function_exists('nexora_render_text_emoji')) {
        foreach (['text', 'caption'] as $key) {
            if (isset($datas[$key]) && is_string($datas[$key]) && strpos($datas[$key], '{emoji:') !== false) {
                $datas[$key] = nexora_render_text_emoji($datas[$key]);
            }
        }
        if (isset($datas['reply_markup']) && is_string($datas['reply_markup']) && strpos($datas['reply_markup'], '{emoji:') !== false) {
            $datas['reply_markup'] = nexora_render_markup_emoji($datas['reply_markup']);
        }
    }
    if (!payloadRendersHtml($datas)) {
        foreach (['text', 'caption'] as $key) {
            if (isset($datas[$key]) && is_string($datas[$key])) {
                $datas[$key] = stripCustomEmojiTags($datas[$key]);
            }
        }
    }
    if (isset($datas['reply_markup'])) {
        $datas['reply_markup'] = applyCustomEmojiToMarkup($datas['reply_markup']);
    }
    return $datas;
}
PHP;
$code = str_replace($anchorApply, $newApply, $code);
$newStrip = <<<'PHP'
function stripCustomEmojiPayload(array $datas)
{
    if (function_exists('nexora_strip_placeholders')) {
        foreach (['text', 'caption'] as $key) {
            if (isset($datas[$key]) && is_string($datas[$key]) && strpos($datas[$key], '{emoji:') !== false) {
                $datas[$key] = nexora_strip_placeholders($datas[$key]);
            }
        }
        if (isset($datas['reply_markup']) && is_string($datas['reply_markup']) && strpos($datas['reply_markup'], '{emoji:') !== false) {
            $datas['reply_markup'] = nexora_strip_markup_placeholders($datas['reply_markup']);
        }
    }
    foreach (['text', 'caption'] as $key) {
        if (isset($datas[$key]) && is_string($datas[$key])) {
            $datas[$key] = stripCustomEmojiTags($datas[$key]);
        }
    }
    if (isset($datas['reply_markup'])) {
        $datas['reply_markup'] = stripCustomEmojiFromMarkup($datas['reply_markup']);
    }
    return $datas;
}
PHP;
$code = str_replace($anchorStrip, $newStrip, $code);
file_put_contents($file, $code);
echo "Patched botapi.php.\n";
