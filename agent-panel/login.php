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
                    $_SESSION['nexora_agent_id'] = $id;
                    header('Location: index.php');
                    exit;
                }
            } catch (Throwable $e) {
                $error = 'خطای دیتابیس.';
            }
        }
        if ($error === '') {
            nexora_agent_login_fail();
            $error = 'آیدی یا رمز اشتباه است.';
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
<title>ورود پنل نمایندگی — نکسورا</title>
<link rel="icon" href="nexora-logo-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="reseller-panel.css?v=8">
<link rel="stylesheet" href="beauty.css?v=12">
<link rel="stylesheet" href="mobile.css?v=13">
<style>
.rs-login .agent-badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#0e9488,#7c3aed);color:#fff;font-size:11px;font-weight:900;padding:4px 12px;border-radius:20px;letter-spacing:.3px;box-shadow:0 6px 14px rgba(14,148,136,.35);margin-bottom:12px}
.rs-login label{display:block;margin:10px 0 4px;font-weight:700;font-size:13px}
.rs-login input{width:100%;padding:10px 12px;border-radius:12px;border:1.5px solid var(--line,#d7e2ea);background:rgba(255,255,255,.6);font:inherit}
.rs-login .rs-err{color:#e11d48;font-size:13px;margin:8px 0 0;min-height:18px}
</style>
</head>
<body data-theme="teal">
<div class="rs-login-wrap" id="rs-login">
  <div class="rs-login">
    <img class="logo" src="nexora-logo-icon.png" alt="Nexora Shield">
    <div class="agent-badge">🤖 پنل نمایندگی</div>
    <h1>ورود پنل نمایندگی</h1>
    <p>ورود مخصوص نمایندگان — رمز را از مدیر اصلی (داخل ربات: <code dir="ltr">/agentpass</code>) بگیرید.</p>
    <?php if ($error !== ''): ?><p class="rs-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="POST" autocomplete="on">
      <label>آیدی عددی تلگرام<input type="text" name="id" dir="ltr" inputmode="numeric" autocomplete="username" required value="<?= htmlspecialchars((string)($_POST['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
      <label>رمز عبور<input type="password" name="pass" autocomplete="current-password" required></label>
      <button class="rs-btn block" type="submit" style="margin-top:14px">ورود به پنل نمایندگی</button>
    </form>
    <p style="margin-top:14px;font-size:11.5px;color:#9fc3d8">پنل مدیریت: <a href="../panel/" style="color:#5fd4ff">panel/</a></p>
  </div>
</div>
</body>
</html>

