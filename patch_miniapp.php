<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
$file = $target . '/app/index.php';
if ($target === '' || !is_file($file)) { fwrite(STDERR, "app/index.php not found in target.\n"); exit(1); }
$code = file_get_contents($file);
if ($code === false) { fwrite(STDERR, "Cannot read app/index.php.\n"); exit(1); }
if (strpos($code, 'nexora-theme.css') !== false) { echo "app/index.php already patched — nothing to do.\n"; exit(0); }
$errors = [];
$check = function (string $label, int $count, int $expected) use (&$errors) { if ($count !== $expected) { $errors[] = "{$label}: found {$count}, expected {$expected}"; } else { echo "OK: {$label} ({$count})\n"; } };
$anchorTitle = '<title>';
$c = substr_count($code, $anchorTitle);
$check('title tag', $c, 1);
$cssPattern = '/<link rel="stylesheet" crossorigin href="[^"]*\.css">/';
$c = preg_match_all($cssPattern, $code);
if ($c === false || $c !== 1) { $errors[] = 'built css link: found ' . var_export($c, true) . ', expected 1'; } else { echo "OK: built css link (1)\n"; }
if ($errors !== []) { fwrite(STDERR, "Anchor check FAILED:\n - " . implode("\n - ", $errors) . "\n"); fwrite(STDERR, "Nothing was changed. Your Mirzabot version differs — aborting safely.\n"); exit(1); }
if (!$apply) { echo "Dry run OK — all anchors match. Re-run with --apply.\n"; exit(0); }
$code = str_replace($anchorTitle, '<meta name="theme-color" content="#141a2e" />' . "\n    " . $anchorTitle, $code, $c1);
$code = preg_replace_callback($cssPattern, function ($m) { return $m[0] . "\n    " . '<link rel="stylesheet" href="./assets/nexora-theme.css">'; }, $code, 1);
$code = str_replace('<div id="root"></div>', '<style>html,body{background:#141a2e;margin:0}</style><div id="root"></div>', $code);
file_put_contents($file, $code);
echo "Patched app/index.php.\n";
