<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
$file = $target . '/panel/bottext.php';
if ($target === '' || !is_file($file)) { fwrite(STDERR, "panel/bottext.php not found in target.\n"); exit(1); }
$code = file_get_contents($file);
if ($code === false) { fwrite(STDERR, "Cannot read bottext.php.\n"); exit(1); }
if (strpos($code, 'ms-emoji-field') !== false) { echo "bottext.php already patched — nothing to do.\n"; exit(0); }
$errors = [];
$check = function (string $label, int $count, int $expected) use (&$errors) { if ($count !== $expected) { $errors[] = "{$label}: found {$count}, expected {$expected}"; } else { echo "OK: {$label} ({$count})\n"; } };
$anchorNav = "\$activeNav = 'bottext';";
$c = substr_count($code, $anchorNav);
$check('activeNav assignment', $c, 1);
$anchorArea = 'class="textarea" dir="auto"';
$c = substr_count($code, $anchorArea);
$check('textarea class', $c, 1);
$anchorForm = '<form method="POST" onsubmit=';
$c = substr_count($code, $anchorForm);
$check('edit form', $c, 1);
$anchorFoot = "<?php include __DIR__ . '/inc/layout_foot.php'; ?>";
$c = substr_count($code, $anchorFoot);
$check('layout foot', $c, 1);
if ($errors !== []) { fwrite(STDERR, "Anchor check FAILED:\n - " . implode("\n - ", $errors) . "\n"); fwrite(STDERR, "Nothing was changed. Your nexorabot version differs — aborting safely.\n"); exit(1); }
if (!$apply) { echo "Dry run OK — all anchors match. Re-run with --apply.\n"; exit(0); }
$code = str_replace($anchorNav, $anchorNav . "\n\$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';\n" . "if (is_file(\$__nexoraStyleLib)) {\n    require_once \$__nexoraStyleLib;\n}\n" . "\$emojiLibrary = function_exists('nexora_emoji_list') ? nexora_emoji_list() : [];", $code);
$code = str_replace($anchorArea, 'class="textarea ms-emoji-field" data-ms-max="99" dir="auto"', $code);
$pickerBlock = <<<'PHP'
<?php
$msEmojiRows = [];
if (function_exists('nexora_emoji_placeholder')) {
    foreach ($emojiLibrary as $er) {
        $msEmojiRows[] = [
            'placeholder' => nexora_emoji_placeholder($er),
            'label' => trim((string) ($er['emoji_name'] ?? '')),
        ];
    }
}
include __DIR__ . '/inc/emoji_picker_bar.php';
?>
<p class="field-hint" style="margin:0 0 14px">روی ایموجی کلیک کنید بعد داخل متن — <code dir="ltr">{emoji:slug}</code> در همه متن‌ها (حتی چندتایی) نمایش داده می‌شود.</p>

PHP;
$code = str_replace($anchorForm, $pickerBlock . $anchorForm, $code);
$code = str_replace($anchorFoot, '<script src="js/emoji-insert.js"></script>' . "\n" . $anchorFoot, $code);
file_put_contents($file, $code);
echo "Patched bottext.php.\n";
