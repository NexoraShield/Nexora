<?php
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', '/var/www/html/nexorabot/debug.log');
ini_set('memory_limit', '512M');
error_log("DEBUG: 1-index.php started");

require_once 'config.php';
error_log("DEBUG: 2-config loaded");

require_once 'botapi.php';
error_log("DEBUG: 3-botapi loaded, from_id=" . ($from_id ?? 'NOT_SET') . ", text=" . ($text ?? 'NOT_SET') . ", datain=" . ($datain ?? 'NOT_SET'));

require_once 'jdf.php';
error_log("DEBUG: 3a-jdf loaded");

require_once 'function.php';
error_log("DEBUG: 4-function loaded");

nexoraEnsureInstallerRemoved();
require_once 'keyboard.php';
require_once 'vendor/autoload.php';
require_once 'panels.php';
error_log("DEBUG: 5-includes loaded");

$textbotlang = languagechange();
error_log("DEBUG: 6-languagechange done");

$text = restoreCustomEmojiLabel($text);
error_log("DEBUG: 7-emoji restored, text=" . ($text ?? 'empty') . ", from_id=" . ($from_id ?? 'NOT_SET'));

# IP check bypassed for debugging
# Webhook secret check bypassed for debugging

if ($is_bot) {
    error_log("DEBUG: 8-is_bot=true, returning");
    return;
}
error_log("DEBUG: 8-is_bot=false, continuing");

$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
error_log("DEBUG: 9-admin_ids loaded, count=" . (is_array($admin_ids) ? count($admin_ids) : 'not_array'));

if (!is_array($admin_ids)) {
    $admin_ids = [];
}

if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    error_log("DEBUG: 10-PHP version too low");
    if ($callback_query_id)
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
    if (intval($from_id) != 0 && $Chat_type == "private")
        sendmessage($from_id, sprintf($textbotlang['phpVersion'][in_array($from_id, $admin_ids) ? 'admin' : 'user'], PHP_VERSION, MIN_PHP_VERSION), null, 'html');
    return;
}
error_log("DEBUG: 10-PHP version OK");

if (isset($update['chat_member'])) {
    error_log("DEBUG: 11-chat_member set");
    $status = $update['chat_member']['new_chat_member']['status'];
    $from_id = $update['chat_member']['new_chat_member']['user']['id'];
    $user = select("user", "id", $from_id);
    $keyboard_channel_left = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['rejoin'], 'url' => "https://t.me/{$update['chat_member']['chat']['username']}"],
            ],
        ]
    ]);
    if (in_array($status, ['left', 'kicked', 'restricted'])) {
        sendmessage($from_id, $textbotlang['users']['channel']['left_channel'], $keyboard_channel_left, 'html');
        return;
    }
}
error_log("DEBUG: 11-no chat_member or passed");

if (!in_array($Chat_type, ["private", "supergroup"])) {
    error_log("DEBUG: 12-chat type not private/supergroup: " . ($Chat_type ?? 'NOT_SET'));
    return;
}
error_log("DEBUG: 12-chat type OK: " . ($Chat_type ?? 'NOT_SET'));

if (isset($chat_member)) {
    error_log("DEBUG: 13-chat_member set, returning");
    return;
}
error_log("DEBUG: 13-no chat_member variable");

$first_name = sanitizeUserName($first_name);
$setting = select("setting", "*");
error_log("DEBUG: 14-setting loaded");

$ManagePanel = new ManagePanel();
$keyboard_check = json_decode($setting['keyboardmain'], true);
if (is_array($keyboard_check) && preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $keyboard_check['keyboard'][0][0]['text'])) {
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}],[{"text":"text_agentpanel"},{"text":"text_requestagent"}]]}';
    update("setting", "keyboardmain", $keyboardmain, null, null);
}
error_log("DEBUG: 15-keyboard check done");

if (intval($from_id) == 0) {
    error_log("DEBUG: 16-from_id is 0, returning");
    return;
}
error_log("DEBUG: 16-from_id=" . $from_id);

$lockWait = isset($update['pre_checkout_query']) || isset($update['message']['successful_payment']) ? 10 : 0;
$userLock = $pdo->prepare("SELECT GET_LOCK(?, $lockWait)");
$userLock->execute(["nexora_user_" . $from_id]);
if ((int) $userLock->fetchColumn() !== 1) {
    error_log("DEBUG: 17-could not get lock");
    if ($callback_query_id) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
    }
    return;
}
error_log("DEBUG: 17-lock acquired");

if (isset($update['pre_checkout_query'])) {
    error_log("DEBUG: 18-pre_checkout_query");
    $userid = $update['pre_checkout_query']['from']['id'];
    $id_order = $update['pre_checkout_query']['invoice_payload'];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        return;
    } else {
        telegram('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
            'ok' => true,
        ]);
    }
    if ($Payment_report['payment_Status'] == "paid") {
        return;
    }
    update("Payment_report", "dec_not_confirmed", json_encode($update['pre_checkout_query']), "id_order", $Payment_report['id_order']);
    return;
}
error_log("DEBUG: 18-not pre_checkout");

if (isset($update['message']['successful_payment'])) {
    error_log("DEBUG: 19-successful_payment");
    $id_order = $update['message']['successful_payment']['invoice_payload'];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        return;
    }
    if (!claimPaymentPaid($Payment_report['id_order'])) {
        return;
    }
    update("Payment_report", "dec_not_confirmed", $Payment_report['dec_not_confirmed'] . json_encode($update['message']['successful_payment']), "id_order", $Payment_report['id_order']);
    DirectPayment($Payment_report['id_order']);
    $pricecashback = getPaySetting("chashbackstar")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) + $result;
        addBalance($Balance_id['id'], $result);
        $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], $result);
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => select("topicid", "idreport", "report", "paymentreport", "select")['idreport'],
            'text' => sprintf($textbotlang['Admin']['reportgroup']['newPaymentStar'], $Balance_id['username'], $Balance_id['id'], $Payment_report['price'], $update['message']['successful_payment']['total_amount']),
            'parse_mode' => "HTML"
        ]);
    }
    return;
}
error_log("DEBUG: 19-not successful_payment");

# Variable setup
$otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
$user = select("user", "*", "id", $from_id, "select");
error_log("DEBUG: 20-user loaded, id=" . ($from_id ?? 'null') . ", user_found=" . ($user ? 'yes' : 'no'));

$isNewUser = ($user === null || $user === false);
if ($setting['statusnewuser'] == "onnewuser" && $isNewUser) {
    error_log("DEBUG: 21-new user, sending admin notification");
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $newuser = sprintf($textbotlang['Admin']['reportgroup']['newUser'], $first_name, $username, "<a href = \"tg://user?id=$from_id\">$from_id</a>");
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $newuser,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
error_log("DEBUG: 21-new user check done");

$date = time();
if ($from_id != 0) {
    if ($setting['verifystart'] != "onverify") {
        $valueverify = 1;
    } else {
        $valueverify = 0;
    }
    $randomString = bin2hex(random_bytes(6));
    $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1')");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':showcard', $setting['showcard']);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':verifycode', $valueverify);
    $stmt->bindParam(':codeInvitation', $randomString);
    $stmt->execute();
    error_log("DEBUG: 22-user insert executed, rowCount=" . $stmt->rowCount());
    if ($stmt->rowCount() > 0) {
        clearSelectCache('user');
        if (isset($GLOBALS['nexora_user_cache'][$from_id])) {
            unset($GLOBALS['nexora_user_cache'][$from_id]);
        }
    }
}
error_log("DEBUG: 22-user insert done");

$user = select("user", "*", "id", $from_id, "select");
error_log("DEBUG: 23-user re-selected, found=" . ($user ? 'yes' : 'no'));

if ($user == false) {
    $user = array(
        'step' => '',
        'Processing_value' => '',
        'User_Status' => '',
        'agent' => '',
        'username' => '',
        'limit_usertest' => '',
        'message_count' => '',
        'affiliates' => '',
        'last_message_time' => '',
        'cardpayment' => '',
        'roll_Status' => '',
        'number_username' => '',
        'number' => '',
        'register' => '',
        'codeInvitation' => '',
        'pricediscount' => '',
        'joinchannel' => '',
        'score' => "",
        'limitchangeloc' => ''
    );
}
error_log("DEBUG: 23-user array ensured, roll_Status=" . ($user['roll_Status'] ?? 'NOT_SET'));

$channels_id = select("channels", "link", null, null, "FETCH_COLUMN");
$topic_id = select("topicid", "*", null, null, "fetchAll");
error_log("DEBUG: 24-topics loaded, channels_count=" . count($channels_id ?? []));

$statusnote = false;
foreach ($topic_id as $topic) {
    if ($topic['report'] == "reportnight")
        $reportnight = $topic['idreport'];
    if ($topic['report'] == 'reporttest')
        $reporttest = $topic['idreport'];
    if ($topic['report'] == 'errorreport')
        $errorreport = $topic['idreport'];
    if ($topic['report'] == 'porsantreport')
        $porsantreport = $topic['idreport'];
    if ($topic['report'] == 'backupfile')
        $reportbackup = $topic['idreport'];
    if ($topic['report'] == 'buyreport')
        $buyreport = $topic['idreport'];
    if ($topic['report'] == 'otherservice')
        $otherservice = $topic['idreport'];
    if ($topic['report'] == 'paymentreport')
        $paymentreports = $topic['idreport'];
}
error_log("DEBUG: 25-topic loop done");

if ($setting['statusnamecustom'] == 'onnamecustom')
    $statusnote = true;
if ($setting['statusnoteforf'] == "0" && $user['agent'] == "f")
    $statusnote = false;
if ($user['username'] == "none" || $user['username'] == null || $user['username'] != $username) {
    update("user", "username", $username, "id", $from_id);
}
$lang_array = ['fa', 'en', 'ru', 'zh'];
if (!in_array($user['lang'], $lang_array)) {
    update("user", "lang", 'fa', "id", $from_id);
}
if ($user['register'] == "none") {
    update("user", "register", time(), "id", $from_id);
}
if (!in_array($user['agent'], ["n", "n2", "f"]))
    update("user", "agent", "f", "id", $from_id);
error_log("DEBUG: 26-user settings updated");

# User_Status block check
if ($user['User_Status'] == "block" && !in_array($from_id, $admin_ids)) {
    error_log("DEBUG: 27-user blocked");
    $textblock = sprintf($textbotlang['users']['block']['descriptions'], $user['description_blocking']);
    sendmessage($from_id, $textblock, null, 'html');
    return;
}
error_log("DEBUG: 27-user not blocked");

# Anti-spam
$timebot = time();
$TimeLastMessage = $timebot - intval($user['last_message_time']);
if (floor($TimeLastMessage / 60) >= 1) {
    $stmt = $pdo->prepare("UPDATE user SET last_message_time = ?, message_count = '1' WHERE id = ?");
    $stmt->execute([$timebot, $from_id]);
    clearSelectCache('user');
    if (isset($GLOBALS['nexora_user_cache'][$from_id])) {
        unset($GLOBALS['nexora_user_cache'][$from_id]);
    }
} else {
    if (!in_array($from_id, $admin_ids)) {
        $addmessage = intval($user['message_count']) + 1;
        update("user", "message_count", $addmessage, "id", $from_id);
        if (intval($addmessage) >= 35) {
            $User_Status = "block";
            $textblok = sprintf($textbotlang['users']['spam']['spamedReport'], $from_id);
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
                    ],
                ]
            ]);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $textblok,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ]);
            }
            update("user", "User_Status", $User_Status, "id", $from_id);
            update("user", "description_blocking", $textbotlang['users']['spam']['spamed'], "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['spam']['spamedMessage'], null, 'html');
            return;
        }
    }
}
error_log("DEBUG: 27-anti-spam done");

# /start handler
if ((strpos($text, "/start ") !== false || $text == "/start") && $user['step'] != "gettextSystemMessage") {
    error_log("DEBUG: 28-START HANDLER REACHED, from_id=$from_id, text=$text");
    $affiliatesid = (strpos($text, " ") !== false) ? explode(" ", $text)[1] : '';
    if (!in_array($affiliatesid, ['start', "usertest", "/start", "buy", "help"])) {
        isValidInvitationCode($setting, $from_id, $user['verify']);
        if (!check_active_btn($setting['keyboardmain'], "text_affiliates")) {
            error_log("DEBUG: 28a-affiliates btn off");
            sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
            return;
        }
        if (is_numeric($affiliatesid) && rowExists("user", "id", $affiliatesid)) {
            if ($affiliatesid == $from_id) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                return;
            }
            $user = select("user", "*", "id", $from_id, "select");
            if (intval($user['affiliates']) != 0) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                return;
            }
            update("user", "affiliates", $affiliatesid, "id", $from_id);
            $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
            sendmessage($from_id, sprintf($textbotlang['users']['affiliates']['welcomeInvited'], $useraffiliates['username']), $keyboard, 'html');
            sendmessage($affiliatesid, sprintf($textbotlang['users']['affiliates']['newReferralJoined'], $username), $keyboard, 'html');
            $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
            update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
            $stmt = $pdo->prepare("INSERT IGNORE INTO reagent_report (user_id, get_gift,time,reagent) VALUES (?, ?,?, ?)");
            $dateacc = date('Y/m/d H:i:s');
            $type_gift = false;
            $stmt->execute([$from_id, $type_gift, $dateacc, $affiliatesid]);
        } else {
            error_log("DEBUG: 28b-sending start message");
            sendmessage($from_id, $textbotlang['users']['text_start'], $keyboard, 'html');
            update("user", "Processing_value", "0", "id", $from_id);
            update("user", "Processing_value_one", "0", "id", $from_id);
            update("user", "Processing_value_tow", "0", "id", $from_id);
            update("user", "Processing_value_four", "0", "id", $from_id);
            step('home', $from_id);
        }
        return;
    }
}
error_log("DEBUG: 28-not /start handler or step=gettextSystemMessage");

# Roll check
if ($setting['roll_Status'] == "rolleon" && $user['roll_Status'] == 0 && ($text != $textbotlang['keyboard']['acceptRulesButton'] and $datain != "acceptrule") && !in_array($from_id, $admin_ids)) {
    error_log("DEBUG: 29-ROLL CHECK TRIGGERED, sending rules");
    sendmessage($from_id, $textbotlang['textbot']['rules'], $confrimrolls, 'html');
    return;
}
error_log("DEBUG: 29-roll check passed");

# Bot status check
if ($setting['Bot_Status'] == "botstatusoff" && !in_array($from_id, $admin_ids)) {
    error_log("DEBUG: 30-bot status off");
    sendmessage($from_id, $textbotlang['textbot']['botOff'], null, 'html');
    return;
}
error_log("DEBUG: 30-bot status on");

# Channel join check
if ($user['joinchannel'] != "active") {
    if (count($channels_id) != 0) {
        $channels = channel($channels_id);
        if ($datain == "confirmchannel") {
            if (count($channels) == 0) {
                deletemessage($from_id, $message_id);
                sendmessage($from_id, $textbotlang['users']['text_start'], $keyboard, 'html');
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['channel']['confirmed'],
                    'show_alert' => false,
                    'cache_time' => 5,
                ]);
                return;
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select");
                if ($channelremark['remark'] == null)
                    continue;
                if ($channelremark['linkjoin'] == null)
                    continue;
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            Editmessagetext($from_id, $message_id, $textbotlang['textbot']['channel'], $keyboardchannel);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['channel']['notconfirmed'],
                'show_alert' => true,
                'cache_time' => 5,
            ]);
            $partsaffiliates = explode("_", $user['Processing_value_four']);
            if ($partsaffiliates[0] == "affiliates") {
                $affiliatesid = $partsaffiliates[1];
                if (!rowExists("user", "id", $affiliatesid)) {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['affiliatesidyou'], null, 'html');
                    return;
                }
                if ($affiliatesid == $from_id) {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                    return;
                }
                $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
                $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
                if ($marzbanDiscountaffiliates['Discount'] == "onDiscountaffiliates") {
                    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
                    $Balance_add_user = $useraffiliates['Balance'] + $marzbanDiscountaffiliates['price_Discount'];
                    addBalance($affiliatesid, $marzbanDiscountaffiliates['price_Discount']);
                    $addbalancediscount = number_format($marzbanDiscountaffiliates['price_Discount'], 0);
                    sendmessage($affiliatesid, strtr($textbotlang['users']['affiliates']['balanceGift'], ['{addbalancediscount}' => $addbalancediscount, '{from_id}' => $from_id]), null, 'html');
                }
                sendmessage($from_id, $textbotlang['users']['text_start'], $keyboard, 'html');
                $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
                update("user", "affiliates", $affiliatesid, "id", $from_id);
                update("user", "Processing_value_four", "none", "id", $from_id);
                update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
            }
            return;
        }
        if (count($channels) != 0 && !in_array($from_id, $admin_ids)) {
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select");
                if ($channelremark['remark'] == null)
                    continue;
                if ($channelremark['linkjoin'] == null)
                    continue;
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            sendmessage($from_id, $textbotlang['textbot']['channel'], $keyboardchannel, 'html');
            return;
        }
    }
}
error_log("DEBUG: 31-channel check done");

# Final /start handler
if ($text == "/start" || $datain == "start" || $text == "start") {
    error_log("DEBUG: 32-FINAL /start HANDLER, sending message");
    sendmessage($from_id, $textbotlang['users']['text_start'], $keyboard, "html");
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
}
error_log("DEBUG: 32-end of script, no handler matched");