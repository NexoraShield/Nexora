<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
$file = $target . '/keyboard.php';
if ($target === '' || !is_file($file)) { fwrite(STDERR, "keyboard.php not found in target.\n"); exit(1); }
$code = file_get_contents($file);
if ($code === false) { fwrite(STDERR, "Cannot read keyboard.php.\n"); exit(1); }
if (strpos($code, 'nexora_style.php') !== false) { echo "keyboard.php already patched — nothing to do.\n"; exit(0); }
$errors = [];
$check = function (string $label, int $count, int $expected) use (&$errors) { if ($count !== $expected) { $errors[] = "{$label}: found {$count}, expected {$expected}"; } else { echo "OK: {$label} ({$count})\n"; } };
$anchorRequire = "require_once 'config.php';";
$c = substr_count($code, $anchorRequire);
$check('require config.php', $c, 1);
$anchorProduct = '[\'text\' => $result[\'name_product\'], \'callback_data\' => "{$datakeyboard}{$result[\'code_product\']}{$valuetow}"]';
$c = substr_count($code, $anchorProduct);
$check('product button', $c, 1);
$anchorSoldout = '[\'text\' => $result[\'name_product\'] . " | " . $textbotlang[\'keyboard\'][\'productSoldOut\'], \'callback_data\' => "productsoldout"]';
$c = substr_count($code, $anchorSoldout);
if ($c === 1) { echo "OK: sold-out button (1)\n"; $hasSoldout = true; }
elseif ($c === 0) { echo "SKIP: sold-out button (not present in this version)\n"; $hasSoldout = false; }
else { $errors[] = "sold-out button: found {$c}, expected 0 or 1"; $hasSoldout = false; }
$anchorCategory = '[[\'text\' => $row[\'remark\'], \'callback_data\' => "categorynames_" . $row[\'id\']]];';
$c = substr_count($code, $anchorCategory);
$check('category button', $c, 1);
$panelPattern = '/\[\'text\' => \$result\[\'name_panel\'\], \'callback_data\' => ("(?:locationnotuser_|locationom_|locationtest_|changelocselectlo-|location_)[^"]*")\]/';
$c = preg_match_all($panelPattern, $code);
if ($c === false) { $errors[] = 'panel buttons: regex failed'; } else { $check('panel buttons', $c, 8); }
$anchorMenu = '$keyboardcustom = applyKeyboardLabels($keyboardcustom, $replacements);';
$c = substr_count($code, $anchorMenu);
if ($c === 2) { echo "OK: main-menu labels hook (2)\n"; $hasMenu = true; }
elseif ($c === 0) { echo "SKIP: main-menu labels hook (not present in this version)\n"; $hasMenu = false; }
else { $errors[] = "main-menu labels hook: found {$c}, expected 0 or 2"; $hasMenu = false; }
$anchorOrder = "    \$statusshowprice = select(\"shopSetting\", \"*\", \"Namevalue\", \"statusshowprice\", \"select\")['value'];\n    \$stmt = \$pdo->prepare(\$query);\n    \$stmt->execute(\$queryParams);";
$c = substr_count($code, $anchorOrder);
if ($c === 1) { echo "OK: product order anchor (1)\n"; $hasOrder = true; }
elseif ($c === 0) { echo "SKIP: product order anchor (not present in this version)\n"; $hasOrder = false; }
else { $errors[] = "product order anchor: found {$c}, expected 0 or 1"; $hasOrder = false; }
if ($errors !== []) {
    fwrite(STDERR, "Anchor check FAILED:\n - " . implode("\n - ", $errors) . "\n");
    fwrite(STDERR, "Nothing was changed. Your nexorabot version differs — aborting safely.\n");
    exit(1);
}
if (!$apply) { echo "Dry run OK — all anchors match. Re-run with --apply.\n"; exit(0); }
$code = str_replace($anchorRequire, "require_once 'config.php';\nrequire_once __DIR__ . '/nexora_style.php';", $code);
$code = str_replace($anchorProduct, 'nexora_style_btn($result[\'name_product\'], "{$datakeyboard}{$result[\'code_product\']}{$valuetow}", $result)', $code);
if (!empty($hasSoldout)) {
    $code = str_replace($anchorSoldout, 'nexora_style_btn($result[\'name_product\'] . " | " . $textbotlang[\'keyboard\'][\'productSoldOut\'], "productsoldout", $result)', $code);
}
$code = str_replace($anchorCategory, '[[nexora_style_btn($row[\'remark\'], "categorynames_" . $row[\'id\'], $row)]];', $code);
$code = preg_replace_callback($panelPattern, function ($m) { return 'nexora_style_btn($result[\'name_panel\'], ' . $m[1] . ', $result)'; }, $code);
if (!empty($hasMenu)) {
    $code = str_replace($anchorMenu, $anchorMenu . "\n    if (function_exists('nexora_apply_menu_emoji')) {\n        \$keyboardcustom = nexora_apply_menu_emoji(\$keyboardcustom, \$replacements);\n    }", $code);
}
if (!empty($hasOrder)) {
    $code = str_replace($anchorOrder, "    \$statusshowprice = select(\"shopSetting\", \"*\", \"Namevalue\", \"statusshowprice\", \"select\")['value'];\n    if (stripos(\$query, 'ORDER BY') === false) {\n        \$query .= ' ORDER BY id ASC';\n    }\n    \$stmt = \$pdo->prepare(\$query);\n    \$stmt->execute(\$queryParams);", $code);
}
file_put_contents($file, $code);
echo "Patched keyboard.php.\n";
