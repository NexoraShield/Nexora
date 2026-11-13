<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/auth.php';
nexora_agent_ensure_table();
$error = '';
$throttle = nexora_agent_login_throttle();
if (!$throttle['allowed']) {
    $error = $throttle['msg'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($error === '') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $pass = (string) ($_POST['pass'] ?? '');
        if ($id !== '' && $pass !== '' && preg_match('/^\d+$/', $id)) {
            try {
                $stmt = $pdo->prepare('SELECT pass_hash FROM agent_auth WHERE id_user = ? LIMIT 1');
                $stmt->execute([$id]);
                $hash = $stmt->fetchColumn();
                if (is_string($hash) && password_verify($pass, $hash)) {
                    nexora_agent_login_reset();
                    // A new session id after the login, so a session that was
                    // planted before the login cannot be used afterwards.
                    if (function_exists('session_regenerate_id')) {
                        @session_regenerate_id(true);
                    }
                    $_SESSION['nexora_agent_id'] = $id;
                    header('Location: index.php');
                    exit;
                }
            } catch (Throwable $e) {
                $error = 'خطا در بررسی اطلاعات ورود.';
            }
        }
        if ($error === '') {
            nexora_agent_login_fail();
            $error = 'نام کاربری یا رمز اشتباه است.';
        }
        sleep(1);
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ورود به پنل نمایندگی | نکسورا</title>
<link rel="icon" href="assets/nexora-logo-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Estedad:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="reseller-panel.css?v=8">
<link rel="stylesheet" href="beauty.css?v=12">
<link rel="stylesheet" href="mobile.css?v=13">
<style>
  .rs-login .agent-badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#0e9488,#7c3aed);color:#fff;font-size:11px;font-weight:900;padding:4px 12px;border-radius:20px;letter-spacing:.3px;box-shadow:0 6px 14px rgba(14,148,136,.35);margin-bottom:12px}
  .rs-login .hint{color:#9fc3d8;font-size:12px;margin:-6px 0 14px}
</style>
</head>
<body data-theme="teal">
<div class="rs-login-wrap" id="rs-login">
  <div class="rs-login">
    <img class="logo" src="assets/nexora-logo-icon.png" alt="Nexora Shield">
    <div class="agent-badge">پنل نمایندگی <i>PasarGuard</i></div>
    <h1>پنل نمایندگی نکسورا</h1>
    <p>نام کاربری و رمز پنلت را وارد کن</p>
    <form method="POST" autocomplete="off">
      <label>نام کاربری<input id="rl-user" name="id" type="text" dir="ltr" autocomplete="username" required value="<?= htmlspecialchars((string) ($_POST['id'] ?? '')) ?>"></label>
      <label>رمز عبور<input id="rl-pass" name="pass" type="password" autocomplete="current-password" required></label>
      <p class="rs-err" id="rl-err"><?= htmlspecialchars((string) $error) ?></p>
      <button class="rs-btn block" id="rl-go" type="submit">ورود به پنل</button>
    </form>
    <p class="hint">اگر رمز پنل را فراموش کرده‌اید از مدیر ربات بخواهید آن را دوباره برای شما بسازد.</p>
  </div>
</div>
<script src="beauty.js?v=7" defer></script>
<script src="mobile.js?v=11" defer></script>
</body>
</html>
