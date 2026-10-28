<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['nexora_agent_id']);
header('Location: login.php');
exit;
