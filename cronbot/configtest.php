<?php
// The job of the test services: the accounts of the tests that are over are
// removed from the panel and the invoice of the test is closed.
//
// Before, the job only looked at fifteen invoices and only closed an account
// whose state was not "active", so the test accounts stayed on the panel
// forever and piled up there.
//
// The invoice of a test is never deleted (the job of the cleanup of the
// orders keeps it), because it is the only trace of the account: without it
// the account of the test stays on the panel and the administrator can not
// remove it any more.
chdir(__DIR__);
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
$ManagePanel = new ManagePanel();
$textbotlang = languagechange();

// The test service is only a few hours long: everything that is older than
// that is removed from the panel.
$testHours = 0;
try {
    $panelRow = $pdo->query("SELECT MAX(CAST(val_usertest AS INTEGER)) AS v, MAX(CAST(time_usertest AS INTEGER)) AS h FROM marzban_panel WHERE type = 'marzban'")->fetch(PDO::FETCH_ASSOC);
    $testHours = (int) ($panelRow['h'] ?? 0);
} catch (Throwable $e) {
    $testHours = 0;
}
if ($testHours <= 0) {
    $testHours = 12;
}
$testLabel = (string) ($textbotlang['common']['labels']['testServiceName'] ?? 'سرویس تست');
$testLabel5 = (string) ($textbotlang['common']['labels']['testService5'] ?? '');

// The invoices of the tests: the old label of the bot and the products that
// are marked as a test. A database that does not know the column of the test
// still finds the old label.
$hasTestColumn = false;
try {
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'is_test' LIMIT 1");
    $st->execute();
    $hasTestColumn = $st->fetchColumn() !== false;
} catch (Throwable $e) {
    $hasTestColumn = false;
}
$sql = "SELECT * FROM invoice WHERE status NOT IN ('disabled','removebyadmin','removebyuser','removeTime','removevolume') AND (name_product = :mp1";
$params = [':mp1' => $testLabel];
if ($testLabel5 !== '' && $testLabel5 !== $testLabel) {
    $sql .= " OR name_product = :mp2";
    $params[':mp2'] = $testLabel5;
}
if ($hasTestColumn) {
    $sql .= " OR name_product IN (SELECT name_product FROM product WHERE is_test = 1)";
}
$sql .= ") ORDER BY time_sell ASC LIMIT 200";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('configtest: the invoices of the tests could not be read: ' . $e->getMessage());
    $rows = [];
}
$closed = 0;
$removed = 0;
foreach ($rows as $result) {
    $username = trim((string) ($result['username'] ?? ''));
    if ($username === '') {
        continue;
    }
    $panel = select("marzban_panel", "*", "name_panel", $result['Service_location'], "select");
    if (!$panel) {
        continue;
    }
    $soldAt = (int) ($result['time_sell'] ?? 0);
    $hours = (int) ($result['Service_time'] ?? 0);
    // The account of the test is only removed when the test is really over:
    // the invoice knows how long the test was.
    $isOver = false;
    if ($hours > 0 && $soldAt > 0) {
        $isOver = (time() - $soldAt) > ($hours * 3600) + 3600;
    } elseif ($soldAt > 0) {
        $isOver = (time() - $soldAt) > ($testHours * 3600) + 3600;
    }
    $dataUser = $ManagePanel->DataUser($result['Service_location'], $username);
    $status = (string) ($dataUser['status'] ?? '');
    if ($status === 'Unsuccessful') {
        // The account is not on the panel (any more): the invoice of the test
        // is closed, so it does not come back.
        update("invoice", "status", "disabled", "username", $username);
        $closed++;
        continue;
    }
    if (!$isOver) {
        // The test is still running: the account stays.
        continue;
    }
    $ManagePanel->RemoveUser($result['Service_location'], $username);
    update("invoice", "status", "disabled", "username", $username);
    $removed++;
    $closed++;
    $user = select("user", "*", "id", $result['id_user'], "select");
    if ($user && intval($user['status_cron']) != 0) {
        $response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['keyboard']['buyService'], 'callback_data' => 'buy'],
                ],
            ]
        ]);
        $textExpire = str_replace('{username}', $username, $textbotlang['textbot']['testExpired']);
        sendmessage($result['id_user'], $textExpire, $response, 'HTML');
    }
}
echo "The test services are closed: " . $closed . " (" . $removed . " accounts removed from the panel)\n";
