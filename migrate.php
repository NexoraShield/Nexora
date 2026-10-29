<?php
$target = $argv[1] ?? '';
$target = rtrim($target, "/\\");
if ($target === '' || !is_dir($target)) { fwrite(STDERR, "Target directory not found.\n"); exit(1); }
$configFile = $target . '/config.php';
$helperFile = __DIR__ . '/nexora_style.php';
if (!is_file($configFile)) { fwrite(STDERR, "config.php not found in target.\n"); exit(1); }
if (!is_file($helperFile)) { fwrite(STDERR, "nexora_style.php not found.\n"); exit(1); }
require $configFile;
require $helperFile;
if (!isset($pdo) || !($pdo instanceof PDO)) { fwrite(STDERR, "Database connection (\$pdo) not available.\n"); exit(1); }
try {
    $added = nexora_ensure_style_columns($pdo);
    if (function_exists('nexora_ensure_emoji_table')) nexora_ensure_emoji_table($pdo);
    $pdo->exec('CREATE TABLE IF NOT EXISTS agent_auth (id_user VARCHAR(32) NOT NULL PRIMARY KEY, pass_hash VARCHAR(255) NOT NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    echo "agent_auth table ensured.\n";
    $pdo->exec('CREATE TABLE IF NOT EXISTS agent_login_attempts (ip VARCHAR(64) NOT NULL PRIMARY KEY, attempts INT UNSIGNED NOT NULL DEFAULT 0, first_attempt INT UNSIGNED NOT NULL DEFAULT 0, last_attempt INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    echo "agent_login_attempts table ensured.\n";
    if ($added === []) echo "Columns already exist — nothing to do.\n";
    else echo "Added columns: " . implode(', ', $added) . "\n";
    $frenzyDefaults = [
        'frenzyexstatus' => 'offfrenzyex',
        'frenzyex_api_key' => '',
        'frenzyex_base_url' => 'https://frenzy.fastsnap.info',
        'frenzyex_callback_secret' => '',
        'cashbackfrenzyex' => '0',
    ];
    foreach ($frenzyDefaults as $name => $val) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
        $chk->execute([$name]);
        if ((int) $chk->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$name, $val]);
            echo "Added PaySetting: {$name}\n";
        }
    }
    echo "Migration OK.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
