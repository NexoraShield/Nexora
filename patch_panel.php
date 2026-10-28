<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
$file = $target . '/panel/inc/layout_head.php';
if ($target === '' || !is_file($file)) { fwrite(STDERR, "panel/inc/layout_head.php not found in target.\n"); exit(1); }
$code = file_get_contents($file);
if ($code === false) { fwrite(STDERR, "Cannot read layout_head.php.\n"); exit(1); }
if (strpos($code, 'textemojis.php') !== false && strpos($code, 'health.php') !== false && strpos($code, 'export.php') !== false && strpos($code, 'mirza-glass.css') !== false && strpos($code, 'btnstyle.php') !== false) {
    echo "layout_head.php already patched — nothing to do.\n";
    exit(0);
}
$errors = [];
$check = function (string $label, int $count, int $expected) use (&$errors) { if ($count !== $expected) { $errors[] = "{$label}: found {$count}, expected {$expected}"; } else { echo "OK: {$label} ({$count})\n"; } };
$anchorCss = '<link rel="stylesheet" href="css/style.css">';
$c = substr_count($code, $anchorCss);
$check('css link', $c, 1);
$anchorNav = <<<'NAV'
          <a href="bottext.php" class="nav-item <?= $activeNav === 'bottext' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['bottextPageTitle'] ?>">
            <span class="nav-icon"><?= icon('edit') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['bottextPageTitle'] ?></span>
          </a>
NAV;
$c = substr_count($code, $anchorNav);
$check('bottext nav item', $c, 1);
if ($errors !== []) { fwrite(STDERR, "Anchor check FAILED:\n - " . implode("\n - ", $errors) . "\n"); fwrite(STDERR, "Nothing was changed. Your Mirzabot version differs — aborting safely.\n"); exit(1); }
if (!$apply) { echo "Dry run OK — all anchors match. Re-run with --apply.\n"; exit(0); }
$code = str_replace($anchorCss, $anchorCss . "\n  <link rel=\"stylesheet\" href=\"css/mirza-glass.css\">", $code);
$navAdd = $anchorNav . <<<'NAV'

          <a href="btnstyle.php" class="nav-item <?= $activeNav === 'btnstyle' ? 'active' : '' ?>"
            title="رنگ و ایموجی دکمه‌ها">
            <span class="nav-icon"><?= icon('settings') ?></span><span
              class="nav-label">رنگ دکمه‌ها</span>
          </a>
          <a href="emojis.php" class="nav-item <?= $activeNav === 'emojis' ? 'active' : '' ?>"
            title="کتابخانه ایموجی پرمیوم">
            <span class="nav-icon"><?= icon('edit') ?></span><span
              class="nav-label">ایموجی پرمیوم</span>
          </a>
          <a href="panels.php" class="nav-item <?= $activeNav === 'panels' ? 'active' : '' ?>"
            title="مدیریت پنل‌های VPN">
            <span class="nav-icon"><?= icon('server') ?></span><span
              class="nav-label">پنل‌های VPN</span>
          </a>
          <a href="health.php" class="nav-item <?= $activeNav === 'health' ? 'active' : '' ?>"
            title="سلامت پنل‌ها">
            <span class="nav-icon"><?= icon('server') ?></span><span
              class="nav-label">سلامت پنل‌ها</span>
          </a>
          <a href="export.php" class="nav-item <?= $activeNav === 'export' ? 'active' : '' ?>"
            title="خروجی اکسل">
            <span class="nav-icon"><?= icon('invoice') ?></span><span
              class="nav-label">خروجی اکسل</span>
          </a>
          <a href="textemojis.php" class="nav-item <?= $activeNav === 'textemojis' ? 'active' : '' ?>"
            title="ایموجی دکمه‌های اصلی">
            <span class="nav-icon"><?= icon('edit') ?></span><span
              class="nav-label">ایموجی منو</span>
          </a>
NAV;
$code = str_replace($anchorNav, $navAdd, $code);
file_put_contents($file, $code);
echo "Patched layout_head.php.\n";
