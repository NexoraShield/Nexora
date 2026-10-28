<?php
$target = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$target = rtrim($target, "/\\");
if ($target === '' || !is_dir($target)) { fwrite(STDERR, "Target directory not found.\n"); exit(1); }

$pairs = [
    ['lang/fa.php', '⚠️ تیم میرزا هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد', '⚠️ تیم نکسورا هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد'],
    ['lang/fa.php', 'در <b>گروه میرزا</b> پیام بگذارید:', 'در <b>گروه نکسورا</b> پیام بگذارید:'],
    ['lang/fa.php', 'توسط تیم میرزا توسعه داده شده است</blockquote>', 'توسط تیم نکسورا توسعه داده شده است</blockquote>'],
    ['lang/fa.php', "'mirzaAgentPanel' => 'نمایندگی میرزا',", "'mirzaAgentPanel' => 'نمایندگی نکسورا',"],
    ['lang/fa.php', "'keyboardManageTitle' => 'پنل مدیریت ربات میرزا',", "'keyboardManageTitle' => 'پنل مدیریت ربات نکسورا',"],
    ['lang/fa.php', "'layoutBrandName' => 'پنل مدیریت میرزا بات',", "'layoutBrandName' => 'پنل مدیریت نکسورا بات',"],
    ['lang/fa.php', "'layoutNavProducts' => 'میرزا',", "'layoutNavProducts' => 'نکسورا',"],
    ['lang/fa.php', "'layoutPageTitleSuffix' => 'میرزا',", "'layoutPageTitleSuffix' => 'نکسورا',"],
    ['lang/fa.php', "'loginHeading' => 'پنل مدیریت میرزا',", "'loginHeading' => 'پنل مدیریت نکسورا',"],
    ['lang/fa.php', "'loginPanelTitle' => 'ورود — پنل مدیریت میرزا',", "'loginPanelTitle' => 'ورود — پنل مدیریت نکسورا',"],
    ['lang/fa.php', "'loginPasswordLabel' => 'پنل مدیریت میرزا',", "'loginPasswordLabel' => 'پنل مدیریت نکسورا',"],
    ['lang/fa.php', "'loginPasswordPlaceholder' => '· نسخه 1.0 میرزا',", "'loginPasswordPlaceholder' => '· نسخه 1.0 نکسورا',"],
    ['lang/fa.php', '<a href="https://t.me/mirzapanelgroup" rel="nofollow" target="_blank">Mirza Group</a>', '<a href="https://t.me/NexoraSupport" rel="nofollow" target="_blank">Nexora Group</a>'],
    ['lang/en.php', 'The Mirza team gives no guarantee for the gateways', 'The Nexora team gives no guarantee for the gateways'],
    ['lang/en.php', 'leave a message in the <b>Mirza group</b>:', 'leave a message in the <b>Nexora group</b>:'],
    ['lang/en.php', '<a href="https://t.me/mirzapanelgroup" rel="nofollow" target="_blank">Mirza Group</a>', '<a href="https://t.me/NexoraSupport" rel="nofollow" target="_blank">Nexora Group</a>'],
    ['lang/en.php', 'is developed by the Mirza team</blockquote>', 'is developed by the Nexora team</blockquote>'],
    ['lang/en.php', "'mirzaAgentPanel' => 'Mirza Agent',", "'mirzaAgentPanel' => 'Nexora Agent',"],
    ['lang/en.php', "'keyboardManageTitle' => 'Mirza Bot Admin Panel',", "'keyboardManageTitle' => 'Nexora Bot Admin Panel',"],
    ['lang/ru.php', 'Команда Mirza не даёт никаких гарантий по шлюзам', 'Команда Nexora не даёт никаких гарантий по шлюзам'],
    ['lang/ru.php', 'оставьте сообщение в <b>группе Mirza</b>:', 'оставьте сообщение в <b>группе Nexora</b>:'],
    ['lang/ru.php', '<a href="https://t.me/mirzapanelgroup" rel="nofollow" target="_blank">Mirza Group</a>', '<a href="https://t.me/NexoraSupport" rel="nofollow" target="_blank">Nexora Group</a>'],
    ['lang/ru.php', 'разработан командой Mirza</blockquote>', 'разработан командой Nexora</blockquote>'],
    ['lang/ru.php', "'mirzaAgentPanel' => 'Агент Mirza',", "'mirzaAgentPanel' => 'Агент Nexora',"],
    ['lang/ru.php', "'keyboardManageTitle' => 'Панель администратора Mirza Bot',", "'keyboardManageTitle' => 'Панель администратора Nexora Bot',"],
    ['lang/zh.php', '⚠️ Mirza 团队不对网关提供任何保证', '⚠️ Nexora 团队不对网关提供任何保证'],
    ['lang/zh.php', '请在 <b>Mirza 群组</b>中留言：', '请在 <b>Nexora 群组</b>中留言：'],
    ['lang/zh.php', '<a href="https://t.me/mirzapanelgroup" rel="nofollow" target="_blank">Mirza Group</a>', '<a href="https://t.me/NexoraSupport" rel="nofollow" target="_blank">Nexora Group</a>'],
    ['lang/zh.php', '由 Mirza 团队开发</blockquote>', '由 Nexora 团队开发</blockquote>'],
    ['lang/zh.php', "'mirzaAgentPanel' => 'Mirza 代理',", "'mirzaAgentPanel' => 'Nexora 代理',"],
    ['install/checks.php', "'panels/Api/mirza_agent.php' => 'ایجنت میرزا',", "'panels/Api/mirza_agent.php' => 'ایجنت نکسورا',"],
    ['install/index.php', "ربات میرزا روی هاست نصب شد. برای شروع دستور /start را بفرستید.", "ربات نکسورا روی هاست نصب شد. برای شروع دستور /start را بفرستید."],
    ['install/index.php', '<h1>نصب ربات میرزا</h1>', '<h1>نصب ربات نکسورا</h1>'],
    ['install/index.php', '<title>نصب ربات میرزا</title>', '<title>نصب ربات نکسورا</title>'],
    ['app/index.php', '<title>Mirza Web App</title>', '<title>Nexora Web App</title>'],
    ['panel/inc/layout_head.php', '<div class="brand-mark">M</div>', '<div class="brand-mark">N</div>'],
    ['api/index.html', '<title>مستندات API - MirzaBot</title>', '<title>مستندات API - NexoraBot</title>'],
    ['api/index.html', '<span>MirzaBot</span>', '<span>NexoraBot</span>'],
    ['composer.json', '"mirzapro/mirzabot"', '"nexora/nexora"'],
    ['composer.json', '"Mirza Bot - Telegram VPN sales and management bot"', '"Nexora Bot - Telegram VPN sales and management bot"'],
    ['composer.json', '"homepage": "https://github.com/mahdiMGF2/mirzabot"', '"homepage": "https://github.com/nexora/Nexora"'],
];

$missing = 0;
$done = 0;
foreach ($pairs as [$rel, $old, $new]) {
    $path = $target . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) { echo "SKIP (no file): {$rel}\n"; continue; }
    $code = file_get_contents($path);
    if ($code === false) { echo "SKIP (unreadable): {$rel}\n"; continue; }
    $c = substr_count($code, $old);
    if ($c < 1) { echo "SKIP (not found, probably already applied): {$rel} :: " . mb_substr($old, 0, 40) . "\n"; $missing++; continue; }
    $code = str_replace($old, $new, $code);
    if ($apply) { file_put_contents($path, $code); }
    echo "OK ({$c}x): {$rel} :: " . mb_substr($old, 0, 40) . "\n";
    $done++;
}
echo $apply ? "Rebrand applied ({$done} pairs, {$missing} skipped).\n" : "Dry run OK ({$done} pairs would apply, {$missing} skipped).\n";
