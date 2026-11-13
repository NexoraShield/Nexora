<?php

// ---------------------------------------------------------------------------
// The questions of the agent shop. The customer answers them after the payment
// and the answers are sent to the administrator with the details.
// ---------------------------------------------------------------------------
if (!function_exists('nexora_agent_shop_questions')) {
    /**
     * The questions of a shop that the customer has to answer.
     */
    function nexora_agent_shop_questions(string $shop = 'agentshop'): array
    {
        global $pdo;
        $shop = in_array($shop, ['agentshop', 'agentpanelshop'], true) ? $shop : 'agentshop';
        try {
            $st = $pdo->prepare("SELECT * FROM agent_shop_question WHERE shop = ? AND status_product = 'active' ORDER BY display_order, id");
            $st->execute([$shop]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('nexora_agent_shop_questions: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('nexora_agent_shop_set_questions')) {
    /**
     * Writes the questions of a shop.
     */
    function nexora_agent_shop_set_questions(string $shop, array $questions): void
    {
        global $pdo;
        $shop = in_array($shop, ['agentshop', 'agentpanelshop'], true) ? $shop : 'agentshop';
        try {
            $pdo->prepare("DELETE FROM agent_shop_question WHERE shop = ?")->execute([$shop]);
            $order = 0;
            foreach ($questions as $question) {
                $question = trim((string) $question);
                if ($question === '') {
                    continue;
                }
                $pdo->prepare("INSERT INTO agent_shop_question (shop, question, display_order, status_product, created_at) VALUES (?,?,?,'active',?)")
                    ->execute([$shop, $question, ++$order, time()]);
            }
        } catch (Throwable $e) {
            error_log('nexora_agent_shop_set_questions: ' . $e->getMessage());
        }
    }
}

if (!function_exists('nexora_agent_shop_bought_products')) {
    /**
     * The products of a shop that a customer already bought. They are shown
     * before the top up options, so the customer can choose the panel that
     * should be charged.
     *
     * The result is a list (numeric keys) of rows: the name of the product,
     * its price, the credit and the panel it belongs to.
     */
    function nexora_agent_shop_bought_products(string $userId, string $shop = 'agentshop'): array
    {
        global $pdo;
        $userId = trim((string) $userId);
        if ($userId === '') {
            return [];
        }
        $active = "('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')";
        try {
            if ($shop === 'agentpanelshop') {
                // Only the agent panels the customer really bought: the name of
                // the product is stored in the invoice of the purchase. The
                // marker of the note and the catalog of the agent panel are
                // both used, so a product that was renamed still counts.
                $st = $pdo->prepare("SELECT i.name_product AS name, i.Service_location AS panel_name, MAX(i.time_sell) AS last_time FROM invoice i WHERE i.id_user = ? AND i.name_product <> '' AND i.note NOT LIKE 'topup:%' AND i.Status IN $active AND (i.note LIKE 'agentpanelshop:%' OR i.name_product IN (SELECT name FROM agent_panel_product)) GROUP BY i.name_product, i.Service_location ORDER BY last_time DESC");
                $st->execute([$userId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                // The catalog of the agent panel is added to the row, so the
                // price and the credit of the product are known as well.
                foreach ($rows as $index => $row) {
                    $product = nexora_agent_panel_product_by_name((string) ($row['name'] ?? ''));
                    $rows[$index]['price'] = $product['price'] ?? 0;
                    $rows[$index]['credit'] = $product['credit'] ?? 0;
                    $rows[$index]['description'] = $product['description'] ?? '';
                }
            } else {
                // The top up orders are not products of the shop, so they are
                // left out. Every column of the select is in the GROUP BY, so
                // the query also works with ONLY_FULL_GROUP_BY turned on.
                $st = $pdo->prepare("SELECT COALESCE(p.name_product, i.name_product) AS name, p.note AS description, p.price_product AS price, COALESCE(p.Location, i.Service_location) AS panel_name, MAX(i.time_sell) AS last_time FROM invoice i LEFT JOIN product p ON p.name_product = i.name_product AND p.agent = 'agentshop' WHERE i.id_user = ? AND i.name_product <> '' AND i.note NOT LIKE 'topup:%' AND i.Status IN $active GROUP BY i.name_product, i.Service_location, p.id, p.name_product, p.note, p.price_product, p.Location ORDER BY last_time DESC");
                $st->execute([$userId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log('nexora_agent_shop_bought_products: ' . $e->getMessage());
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('nexora_agent_shop_bought_names')) {
    /**
     * The names of the products of a shop that a customer already bought. The
     * top up menu works with the names, because the name is what the customer
     * sees and what the administrator needs in the report.
     */
    function nexora_agent_shop_bought_names(string $userId, string $shop = 'agentshop'): array
    {
        $names = [];
        foreach (nexora_agent_shop_bought_products((string) $userId, $shop) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }
}

if (!function_exists('nexora_agent_shop_format_answers')) {
    /**
     * The answers of the customer in a readable form.
     */
    function nexora_agent_shop_format_answers(array $questions, array $answers): string
    {
        $lines = [];
        $i = 0;
        foreach ($questions as $question) {
            $i++;
            $answer = trim((string) ($answers[$i - 1] ?? ''));
            $lines[] = $i . ') ' . strip_tags((string) ($question['question'] ?? '')) . ' → ' . ($answer !== '' ? $answer : '-');
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('nexora_agent_shop_question_text')) {
    /**
     * The text of a question, safe for the HTML mode of Telegram.
     *
     * A question that carries a "&" or a "<" would break the message of
     * Telegram ("can't parse entities"), so the text is escaped here. The
     * customer always gets the question, never an empty answer.
     */
    function nexora_agent_shop_question_text($question): string
    {
        $text = is_array($question) ? (string) ($question['question'] ?? '') : (string) $question;
        $text = (string) preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $text);
        $text = trim(strip_tags($text));
        if ($text === '') {
            return '';
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('nexora_agent_shop_digit')) {
    /**
     * A number in Persian digits, so the numbers of the questions look like
     * the rest of the texts of the bot.
     */
    function nexora_agent_shop_digit($number): string
    {
        return strtr((string) $number, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}

if (!function_exists('nexora_agent_shop_send_question')) {
    /**
     * Sends one question of the shop to the customer.
     *
     * @return bool true when the question really left the bot.
     */
    function nexora_agent_shop_send_question(string $userId, array $questions, int $index): bool
    {
        $total = count($questions);
        if ($index < 0 || $index >= $total) {
            return false;
        }
        $text = nexora_agent_shop_question_text($questions[$index]);
        if ($text === '') {
            return false;
        }
        $message = "❓ سوال " . nexora_agent_shop_digit($index + 1) . " از " . nexora_agent_shop_digit($total) . ":\n" . $text;
        $result = sendmessage($userId, $message, null, 'HTML');
        return !empty($result['ok']);
    }
}

if (!function_exists('nexora_agent_shop_ask_questions')) {
    /**
     * Asks the customer the questions of the shop. The answers are kept until
     * every question is answered.
     */
    function nexora_agent_shop_ask_questions(string $invoiceId, string $userId): bool
    {
        $questions = nexora_agent_shop_questions('agentshop');
        if (!$questions) {
            return false;
        }
        update("user", "Processing_value_tow", $invoiceId, "id", $userId);
        update("user", "Processing_value_four", json_encode([], JSON_UNESCAPED_UNICODE), "id", $userId);
        step('agentshop_questions', $userId);
        nexora_agent_shop_send_question($userId, $questions, 0);
        return true;
    }
}

if (!function_exists('nexora_agent_shop_handle_answer')) {
    /**
     * Handles one answer of the customer of the reseller shop.
     *
     * The function is called before every other part of the bot, so no other
     * branch can swallow the answer. Every answer gets a reaction, so the
     * customer always knows that his answer was saved.
     *
     * @param array $menuTexts the labels of the menu of the bot: a message
     *                         that is one of them is a menu button and not an
     *                         answer, so the round of the questions is closed.
     *
     * @return bool true when the message was handled as an answer.
     */
    function nexora_agent_shop_handle_answer(string $userId, string $text, array $menuTexts = []): bool
    {
        global $textbotlang;
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }
        $user = select("user", "*", "id", $userId, "select");
        if (!$user) {
            return false;
        }
        $questions = nexora_agent_shop_questions('agentshop');
        $invoiceId = trim((string) ($user['Processing_value_tow'] ?? ''));
        $invoice = ($invoiceId !== '' && $invoiceId !== '0')
            ? select("invoice", "*", "id_invoice", $invoiceId, "select")
            : false;
        // The order has to be an order of the reseller shop, otherwise the
        // customer is not in a round of the questions at all.
        if (!$questions || !$invoice || !preg_match('/^agentshop:/', (string) ($invoice['note'] ?? ''))) {
            if ((string) ($user['step'] ?? '') === 'agentshop_questions') {
                step('home', $userId);
                update("user", "Processing_value_tow", '', "id", $userId);
                update("user", "Processing_value_four", '', "id", $userId);
            }
            return false;
        }
        $answer = trim((string) $text);
        if ($answer !== '' && (in_array($answer, $menuTexts, true) || strpos($answer, '/') === 0)) {
            // A button of the menu and a command of the bot are not answers:
            // the button does its usual work. The order is not lost: it goes
            // to the administrator with the answers that were given.
            $cancelAnswers = json_decode((string) ($user['Processing_value_four'] ?? '[]'), true);
            $cancelAnswers = is_array($cancelAnswers) ? array_values($cancelAnswers) : [];
            if (function_exists('nexora_agent_shop_finish')) {
                nexora_agent_shop_finish($invoiceId, $userId, $cancelAnswers, true);
            }
            step('home', $userId);
            update("user", "Processing_value_tow", '', "id", $userId);
            update("user", "Processing_value_four", '', "id", $userId);
            return false;
        }
        $answers = json_decode((string) ($user['Processing_value_four'] ?? '[]'), true);
        $answers = is_array($answers) ? array_values($answers) : [];
        if ($answer === '') {
            // An empty message is not an answer: the question is asked again.
            nexora_agent_shop_send_question($userId, $questions, count($answers));
            return true;
        }
        $answers[] = $answer;
        update("user", "Processing_value_four", json_encode($answers, JSON_UNESCAPED_UNICODE), "id", $userId);
        $index = count($answers);
        if ($index < count($questions)) {
            // The answer was saved: the customer gets the next question.
            if (!nexora_agent_shop_send_question($userId, $questions, $index)) {
                sendmessage($userId, "❓ سوال " . nexora_agent_shop_digit($index + 1) . " از " . nexora_agent_shop_digit(count($questions)) . ":\n" . nexora_agent_shop_question_text($questions[$index]), null, 'HTML');
            }
            return true;
        }
        // Every question is answered: the order goes to the administrator.
        if (function_exists('nexora_agent_shop_finish')) {
            nexora_agent_shop_finish($invoiceId, $userId, $answers);
        }
        update("user", "Processing_value_tow", '', "id", $userId);
        update("user", "Processing_value_four", '', "id", $userId);
        step('home', $userId);
        return true;
    }
}

if (!function_exists('nexora_agent_shop_finish')) {
    /**
     * The order is complete: the administrator gets the details of the payment
     * and of the answers, the customer gets the confirmation.
     *
     * The answers can be handed over directly. When they are not, they are
     * read from the user, so the function can also be called right after the
     * last answer was stored.
     */
    function nexora_agent_shop_finish(string $invoiceId, string $userId, ?array $answers = null, bool $cancelled = false): bool
    {
        global $pdo, $textbotlang;
        $invoice = $invoiceId !== '' ? select("invoice", "*", "id_invoice", $invoiceId, "select") : false;
        if (!$invoice) {
            error_log('nexora_agent_shop_finish: the invoice ' . $invoiceId . ' does not exist');
            return false;
        }
        $marker = [];
        preg_match('/^agentshop:([^|]+)\|?(.*)$/s', (string) ($invoice['note'] ?? ''), $marker);
        $productName = (string) ($invoice['name_product'] ?? '—');
        $product = null;
        if (!empty($marker[1])) {
            try {
                $st = $pdo->prepare("SELECT * FROM product WHERE code_product = ? AND agent = 'agentshop' LIMIT 1");
                $st->execute([trim((string) $marker[1])]);
                $product = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                $product = null;
            }
        }
        if ($product && !empty($product['name_product'])) {
            $productName = (string) $product['name_product'];
        }
        $questions = nexora_agent_shop_questions('agentshop');
        if ($answers === null) {
            $answers = json_decode((string) (select("user", "Processing_value_four", "id", $userId, "select")['Processing_value_four'] ?? '[]'), true);
        }
        $answers = is_array($answers) ? array_values($answers) : [];
        $user = select("user", "*", "id", $userId, "select");
        $userName = trim((string) ($user['username'] ?? ''));
        $price = (float) ($invoice['price_product'] ?? 0);

        // the customer gets the confirmation
        if ($cancelled) {
            $buyerText = "✅ پاسخ‌های شما ثبت شد و سفارش برای پشتیبانی ارسال شد.\n\nپشتیبانی تا ۲۴ ساعت آینده محصول را برای شما ارسال می‌کند.";
        } else {
            $buyerText = ($textbotlang['users']['agentShop']['registered'] ?? "✅ مشخصات شما ثبت شد و سفارش نهایی شد.\n\nعامل انسانی نهایتا تا ۲۴ ساعت پنل شما رو تنظیم و به دستتون می‌رسونه. برای تحویل و دریافت پنل به پشتیبانی مراجعه کنید و پیام بدین.");
        }
        $buyerText = strtr($buyerText, [
            '{name}' => $productName,
            '{price}' => number_format($price),
        ]);
        sendmessage($userId, $buyerText, null, 'HTML');

        // the administrator gets the details of the payment and the answers
        $adminText = "🛒 <b>سفارش فروشگاه نمایندگی</b>\n"
            . "👤 کاربر: <a href=\"tg://user?id=" . htmlspecialchars((string) $userId, ENT_QUOTES) . "\">" . htmlspecialchars((string) $userId, ENT_QUOTES) . "</a>"
            . ($userName !== '' ? " (" . $userName . ")" : '') . "\n"
            . "🏷 محصول: " . $productName . "\n"
            . "💳 مبلغ پرداختی: " . number_format($price) . " تومان\n"
            . "🧾 شماره فاکتور: " . $invoiceId . "\n";
        if ($questions) {
            $adminText .= "\n❓ <b>پاسخ‌های مشتری:</b>\n" . nexora_agent_shop_format_answers($questions, $answers) . "\n";
            if (count($answers) < count($questions)) {
                $adminText .= "⚠️ همهٔ سوال‌ها پاسخ داده نشده است (" . count($answers) . " از " . count($questions) . ").\n";
            }
        }
        if (!empty($marker[2])) {
            $adminText .= "📝 توضیحات محصول: " . strip_tags((string) $marker[2]) . "\n";
        }
        $adminText .= "⚠️ این سفارش باید دستی برای خریدار تحویل شود (پیام مستقیم در تلگرام).";
        $setting = select("setting", "*");
        $adminIds = select("admin", "id_admin", null, null, "FETCH_COLUMN");
        $buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'] ?? 0;
        if (strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => $adminText,
                'parse_mode' => 'HTML'
            ]);
        }
        foreach ((array) $adminIds as $adminId) {
            if (trim((string) $adminId) === '') {
                continue;
            }
            telegram('sendmessage', [
                'chat_id' => $adminId,
                'text' => $adminText,
                'parse_mode' => 'HTML'
            ]);
        }
        return true;
    }
}
