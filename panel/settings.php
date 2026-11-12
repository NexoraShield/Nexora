<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/botconfig.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bot') {
    csrf_check_post();
    $domain = panel_clean_domain((string) ($_POST['domainhosts'] ?? ''));
    $ownerId = panel_clean_user_id((string) ($_POST['adminnumber'] ?? ''));
    $current = panel_config_values();
    $problems = [];
    if ($domain === '') {
        $problems[] = 'دامنه ربات نمی‌تواند خالی باشد.';
    } elseif (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9\-\._])*(\.[A-Za-z0-9]([A-Za-z0-9\-\._])*)+$/', $domain)) {
        $problems[] = 'دامنه ربات معتبر نیست (مثال: bot.example.com).';
    }
    if ($ownerId === '') {
        $problems[] = 'شناسه ادمین صاحب ربات باید یک عدد باشد.';
    }
    if ($problems) {
        flash('error', implode(' ', $problems));
        header('Location: settings.php?tab=bot');
        exit;
    }
    // The owner has to exist as an administrator of the bot.
    try {
        $owner = db_fetch($pdo, "SELECT id_admin, rule FROM admin WHERE id_admin = ?", [$ownerId]);
        if (!$owner) {
            db_query($pdo, "INSERT INTO admin (id_admin, username, password, rule) VALUES (?,?,?,?)", [
                $ownerId,
                'admin',
                password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'administrator',
            ]);
        } elseif ($owner['rule'] !== 'administrator') {
            db_query($pdo, "UPDATE admin SET rule = 'administrator' WHERE id_admin = ?", [$ownerId]);
        }
    } catch (Exception $e) {
        flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        header('Location: settings.php?tab=bot');
        exit;
    }
    $write = panel_config_write([
        'domainhosts' => $domain,
        'adminnumber' => $ownerId,
    ]);
    if (!$write['ok']) {
        flash('error', $write['message']);
        header('Location: settings.php?tab=bot');
        exit;
    }
    // The webhook of the bot points to the old domain, so it is moved as well.
    $webhookMessage = '';
    if ($domain !== (string) ($current['domainhosts'] ?? '')) {
        try {
            $secret = (string) (db_fetch($pdo, "SELECT webhook_secret FROM setting LIMIT 1")['webhook_secret'] ?? '');
            if ($secret !== '' && function_exists('telegram')) {
                $result = telegram('setWebhook', ['url' => "https://$domain/index.php?secret=$secret"]);
                $webhookMessage = !empty($result['ok'])
                    ? '✅ وب‌هوک ربات روی دامنه جدید ثبت شد.'
                    : '⚠️ وب‌هوک ثبت نشد؛ آن را دستی در BotFather تنظیم کنید.';
            } else {
                $webhookMessage = '⚠️ سکرت وب‌هوک خالی است؛ وب‌هوک را دستی تنظیم کنید.';
            }
        } catch (Throwable $e) {
            $webhookMessage = '⚠️ وب‌هوک ثبت نشد: ' . $e->getMessage();
        }
    }
    flash('success', 'تنظیمات ربات ذخیره شد. ' . $webhookMessage);
    header('Location: settings.php?tab=bot');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_check_post();
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $admin = db_fetch($pdo, "SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
    $storedPassword = (string) ($admin['password'] ?? '');
    $storedIsHash = str_starts_with($storedPassword, '$2') || str_starts_with($storedPassword, '$argon2');
    $valid = $storedIsHash ? password_verify($cur, $storedPassword) : hash_equals($storedPassword, $cur);

    if (!$valid) {
        flash('error', $textbotlang['panel']['settingsCurrentPasswordWrong']);
    } elseif ($new !== $confirm) {
        flash('error', $textbotlang['panel']['settingsNewPasswordMismatch']);
    } elseif (strlen($new) < 6) {
        flash('error', $textbotlang['panel']['settingsPasswordMinLength']);
    } else {
        db_query(
            $pdo,
            "UPDATE admin SET password = ? WHERE username = ?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $_SESSION['admin_user']]
        );
        flash('success', $textbotlang['panel']['settingsPasswordChanged']);
    }
    header('Location: settings.php?tab=security');
    exit;
}

$tab = $_GET['tab'] ?? 'appearance';

$themes = [
    'navy' => ['name' => $textbotlang['panel']['settingsThemeBlueSea'], 'desc' => $textbotlang['panel']['settingsThemeBlueSeaDesc'], 'c' => ['#0F172A', '#1E293B', '#06B6D4', '#22C55E'], 'dark' => true],
    'purple' => ['name' => $textbotlang['panel']['settingsThemeDreamPurple'], 'desc' => $textbotlang['panel']['settingsThemeDreamPurpleDesc'], 'c' => ['#180D2E', '#231545', '#A855F7', '#F43F5E'], 'dark' => true],
    'emerald' => ['name' => $textbotlang['panel']['settingsThemeEmeraldGreen'], 'desc' => $textbotlang['panel']['settingsThemeEmeraldGreenDesc'], 'c' => ['#0A1F1C', '#132E2A', '#10B981', '#84CC16'], 'dark' => true],
    'sunset' => ['name' => $textbotlang['panel']['settingsThemeWarmSunset'], 'desc' => $textbotlang['panel']['settingsThemeWarmSunsetDesc'], 'c' => ['#1A0D0D', '#2A1615', '#F97316', '#FBBF24'], 'dark' => true],
    'slate' => ['name' => $textbotlang['panel']['settingsThemeBlack'], 'desc' => $textbotlang['panel']['settingsThemeBlackDesc'], 'c' => ['#080808', '#141414', '#E2E8F0', '#22C55E'], 'dark' => true],
    'light' => ['name' => $textbotlang['panel']['settingsThemeLightWhite'], 'desc' => $textbotlang['panel']['settingsThemeLightWhiteDesc'], 'c' => ['#F1F5F9', '#FFFFFF', '#0891B2', '#16A34A'], 'dark' => false],
    'linen' => ['name' => $textbotlang['panel']['settingsThemeCreamPaper'], 'desc' => $textbotlang['panel']['settingsThemeCreamPaperDesc'], 'c' => ['#FAF7F2', '#FFFFFF', '#B87333', '#5D7C4A'], 'dark' => false],
    'mint' => ['name' => $textbotlang['panel']['settingsThemeMintGreen'], 'desc' => $textbotlang['panel']['settingsThemeMintGreenDesc'], 'c' => ['#F0FDF4', '#FFFFFF', '#166534', '#1D4ED8'], 'dark' => false],
    'lavender' => ['name' => $textbotlang['panel']['settingsThemeLavender'], 'desc' => $textbotlang['panel']['settingsThemeLavenderDesc'], 'c' => ['#FAF5FF', '#FFFFFF', '#6D28D9', '#15803D'], 'dark' => false],
    'rose' => ['name' => $textbotlang['panel']['settingsThemeRose'], 'desc' => $textbotlang['panel']['settingsThemeRoseDesc'], 'c' => ['#1A0A14', '#2A1525', '#F43F5E', '#FB7185'], 'dark' => true],
    'indigo' => ['name' => $textbotlang['panel']['settingsThemeIndigo'], 'desc' => $textbotlang['panel']['settingsThemeIndigoDesc'], 'c' => ['#0F0F2A', '#1A1A3E', '#6366F1', '#818CF8'], 'dark' => true],
    'teal' => ['name' => $textbotlang['panel']['settingsThemeTeal'], 'desc' => $textbotlang['panel']['settingsThemeTealDesc'], 'c' => ['#0A1F1F', '#132E2E', '#14B8A6', '#2DD4BF'], 'dark' => true],
    'amber' => ['name' => $textbotlang['panel']['settingsThemeAmber'], 'desc' => $textbotlang['panel']['settingsThemeAmberDesc'], 'c' => ['#1A150A', '#2A2215', '#F59E0B', '#FBBF24'], 'dark' => true],
    'sky' => ['name' => $textbotlang['panel']['settingsThemeSky'], 'desc' => $textbotlang['panel']['settingsThemeSkyDesc'], 'c' => ['#F0F9FF', '#FFFFFF', '#0EA5E9', '#0284C7'], 'dark' => false],
    'peach' => ['name' => $textbotlang['panel']['settingsThemePeach'], 'desc' => $textbotlang['panel']['settingsThemePeachDesc'], 'c' => ['#FFF7ED', '#FFFFFF', '#F97316', '#EA580C'], 'dark' => false],
];

$tabs = [
    'appearance' => ['icon' => 'settings', 'label' => $textbotlang['panel']['settingsTabAppearance']],
    'security' => ['icon' => 'block', 'label' => $textbotlang['panel']['settingsTabSecurity']],
    'bot' => ['icon' => 'robot', 'label' => 'ربات'],
    'system' => ['icon' => 'dashboard', 'label' => $textbotlang['panel']['settingsTabSystem']],
];

$pageTitle = $textbotlang['panel']['settingsTitle'];
$activeNav = 'settings';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto"
    class="fade-up">
    <?php foreach ($tabs as $key => $tab_data): ?>
        <a href="?tab=<?= $key ?>"
            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .15s;text-decoration:none;
                  <?= $tab === $key ? 'background:var(--ac);color:var(--btn-ac-text, #fff);box-shadow:0 0 14px var(--acg)' : 'color:var(--mute)' ?>">
            <?= icon($tab_data['icon'], 15) ?>     <?= $tab_data['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'appearance'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['settingsHeading'] ?></div>
                <div class="card-subtitle"><?= $textbotlang['panel']['settingsAppearanceSection'] ?></div>
            </div>
        </div>
        <div class="card-body">
            <div
                style="font-size:.75rem;font-weight:700;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">
                <?= $textbotlang['panel']['settingsThemeLabel'] ?></div>
            <div class="theme-grid" style="margin-bottom:20px">
                <?php foreach ($themes as $key => $theme):
                    if (!$theme['dark'])
                        continue; ?>
                    <div class="theme-card" data-tk="<?= $key ?>" onclick="pickTheme('<?= $key ?>')">
                        <div class="theme-preview">
                            <?php foreach ($theme['c'] as $color): ?>
                                <div style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-desc"><?= htmlspecialchars($theme['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div
                style="font-size:.75rem;font-weight:700;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">
                <?= $textbotlang['panel']['settingsSecuritySection'] ?></div>
            <div class="theme-grid">
                <?php foreach ($themes as $key => $theme):
                    if ($theme['dark'])
                        continue; ?>
                    <div class="theme-card" data-tk="<?= $key ?>" onclick="pickTheme('<?= $key ?>')">
                        <div class="theme-preview" style="border:1px solid var(--bd)">
                            <?php foreach ($theme['c'] as $color): ?>
                                <div style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-desc"><?= htmlspecialchars($theme['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card fade-up d1" style="margin-top:14px">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['settingsCurrentPasswordLabel'] ?></div>
            </div>
        </div>
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
            <button onclick="setSidebarMode(false)" class="btn btn-ghost" id="modeExpanded"
                style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 20px;flex:1;min-width:120px">
                <svg width="44" height="32" viewBox="0 0 44 32" fill="none">
                    <rect x="0" y="0" width="13" height="32" rx="3" fill="var(--sf3)" />
                    <rect x="2" y="5" width="9" height="2" rx="1" fill="var(--ac)" />
                    <rect x="2" y="10" width="9" height="2" rx="1" fill="var(--bd)" />
                    <rect x="15" y="0" width="29" height="32" rx="3" fill="var(--sf3)" />
                </svg>
                <span style="font-size:.78rem;font-weight:600"><?= $textbotlang['panel']['settingsNewPasswordLabel'] ?></span>
            </button>
            <button onclick="setSidebarMode(true)" class="btn btn-ghost" id="modeCollapsed"
                style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 20px;flex:1;min-width:120px">
                <svg width="44" height="32" viewBox="0 0 44 32" fill="none">
                    <rect x="0" y="0" width="7" height="32" rx="3" fill="var(--sf3)" />
                    <rect x="2" y="5" width="3" height="2" rx="1" fill="var(--ac)" />
                    <rect x="2" y="10" width="3" height="2" rx="1" fill="var(--bd)" />
                    <rect x="9" y="0" width="35" height="32" rx="3" fill="var(--sf3)" />
                </svg>
                <span style="font-size:.78rem;font-weight:600"><?= $textbotlang['panel']['settingsConfirmPasswordLabel'] ?></span>
            </button>
        </div>
    </div>

<?php elseif ($tab === 'security'): ?>

    <div class="two-col">
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['settingsChangePasswordBtn'] ?></div>
                    <div class="card-subtitle"><?= $textbotlang['panel']['settingsSystemSection'] ?></div>
                </div>
            </div>
            <form method="POST" class="card-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="field">
                        <label><?= $textbotlang['panel']['settingsSaveBtn'] ?></label>
                        <div style="position:relative">
                            <input type="password" name="current_password" id="pw1" class="input" required
                                autocomplete="current-password" style="padding-left:40px">
                            <button type="button" onclick="togglePw('pw1', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--dim);cursor:pointer">
                                <?= icon('eye', 16) ?>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label><?= $textbotlang['panel']['settingsCurrentPasswordPlaceholder'] ?></label>
                        <div style="position:relative">
                            <input type="password" name="new_password" id="pw2" class="input" minlength="6" required
                                autocomplete="new-password" style="padding-left:40px" oninput="checkPwStr(this.value)">
                            <button type="button" onclick="togglePw('pw2', this)"
                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--dim);cursor:pointer">
                                <?= icon('eye', 16) ?>
                            </button>
                        </div>
                        <div style="height:4px;background:var(--sf3);border-radius:99px;margin-top:5px">
                            <div id="pwBar"
                                style="height:100%;width:0;border-radius:99px;transition:all .3s;background:var(--no)">
                            </div>
                        </div>
                        <span id="pwHint" class="field-hint"><?= $textbotlang['panel']['settingsNewPasswordPlaceholder'] ?></span>
                    </div>
                    <div class="field">
                        <label><?= $textbotlang['panel']['settingsConfirmPasswordPlaceholder'] ?></label>
                        <input type="password" name="confirm_password" class="input" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= $textbotlang['panel']['settingsPasswordStrengthLabel'] ?></button>
                </div>
            </form>
        </div>

        <div class="card fade-up d1" style="height:fit-content">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['settingsLogoutBtn'] ?></div>
                </div>
                <a href="logout.php" class="btn btn-no btn-sm"><?= icon('logout', 13) ?> <?= $textbotlang['panel']['settingsSystemInfoTitle'] ?></a>
            </div>
            <div class="kv-list">
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['settingsThemePreviewLabel'] ?></span>
                    <span class="kv-val"><?= htmlspecialchars($_SESSION['admin_user']) ?></span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['settingsApplyThemeBtn'] ?></span>
                    <span class="kv-val">
                        <?= isset($_SESSION['login_time']) ? date('Y/m/d H:i:s', $_SESSION['login_time']) : '—' ?>
                    </span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['settingsSidebarToggleLabel'] ?></span>
                    <span class="kv-val cm"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—') ?></span>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'bot'):

    $configValues = panel_config_values();
    $currentDomain = (string) ($configValues['domainhosts'] ?? ($domainhosts ?? ''));
    $currentOwner = (string) ($configValues['adminnumber'] ?? ($adminnumber ?? ''));
    $admins = db_fetchAll($pdo, "SELECT id_admin, username, rule FROM admin ORDER BY rule, id_admin");
    ?>
    <div class="two-col">
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title">🤖 دامنه و ادمین صاحب ربات</div>
                    <div class="card-subtitle">این دو مقدار در فایل config.php نگهداری می‌شوند. قبل از هر تغییر یک نسخه پشتیبان از آن ساخته می‌شود.</div>
                </div>
            </div>
            <form method="POST" class="card-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_bot">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="field">
                        <label>دامنه ربات</label>
                        <input type="text" name="domainhosts" class="input" dir="ltr" required
                            value="<?= htmlspecialchars($currentDomain) ?>" placeholder="bot.example.com">
                        <span class="field-hint">دامنه‌ای که ربات روی آن نصب شده است. آدرس وب‌هوک و لینک‌های پرداخت با این دامنه ساخته می‌شوند.</span>
                    </div>
                    <div class="field">
                        <label>شناسه ادمین صاحب ربات</label>
                        <input type="text" name="adminnumber" class="input" dir="ltr" required
                            value="<?= htmlspecialchars($currentOwner) ?>" placeholder="8988995603">
                        <span class="field-hint">این شناسه به عنوان ادمین اصلی ثبت می‌شود و مدیریت کامل ربات را دارد.</span>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> ذخیره تنظیمات ربات</button>
                </div>
            </form>
        </div>

        <div class="card fade-up d1" style="height:fit-content">
            <div class="card-head">
                <div>
                    <div class="card-title">👥 مدیران فعلی</div>
                    <div class="card-subtitle"><?= count($admins) ?> مدیر</div>
                </div>
            </div>
            <div class="kv-list">
                <?php foreach ($admins as $adminRow): ?>
                    <div class="kv">
                        <span class="kv-key cm"><?= htmlspecialchars((string) ($adminRow['id_admin'] ?? '')) ?></span>
                        <span class="kv-val"><?= htmlspecialchars((string) ($adminRow['username'] ?? '')) ?>
                            <?= ($adminRow['rule'] ?? '') === 'administrator' ? '<span class="tag tag-ok">مدیر</span>' : '<span class="tag tag-plain">فروشنده</span>' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'system'): ?>

    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['settingsAboutPanelLabel'] ?></div>
            </div>
        </div>
        <div class="kv-list">
            <?php
            $dbVer = '—';
            try {
                $dbVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Exception $e) {
            }
            $sysInfo = [
                [$textbotlang['panel']['settingsPanelVersion'], 1.0],
                ['PHP', phpversion()],
                ['MySQL', $dbVer],
                [$textbotlang['panel']['settingsWebServer'], $_SERVER['SERVER_SOFTWARE'] ?? '—'],
                [$textbotlang['panel']['settingsCurrentAdmin'], $_SESSION['admin_user']],
                [$textbotlang['panel']['settingsServerTime'], date('Y/m/d H:i:s')],
                [$textbotlang['panel']['settingsPhpMemory'], ini_get('memory_limit')],
            ];
            foreach ($sysInfo as [$key, $value]):
                ?>
                <div class="kv">
                    <span class="kv-key"><?= $key ?></span>
                    <span class="kv-val cm" style="font-size:.78rem"><?= htmlspecialchars($value) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="kv">
                <span class="kv-key"><?= $textbotlang['panel']['settingsReportIssue'] ?></span>
                <a class="kv-val btn-link" style="font-size:.78rem" href="https://t.me/nexorapanel?direct" target="_blank" rel="noopener"><?= $textbotlang['panel']['settingsReportIssueLink'] ?></a>
            </div>
            <div class="kv">
                <span class="kv-key"><?= $textbotlang['panel']['settingsDonate'] ?></span>
                <a class="btn-glass" href="https://nowpayments.io/donation/mahdi" target="_blank" rel="noopener"><?= icon('heart', 14) ?> <?= $textbotlang['panel']['settingsDonateLink'] ?></a>
            </div>
        </div>
    </div>

<?php endif; ?>

<script src="js/settings.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>