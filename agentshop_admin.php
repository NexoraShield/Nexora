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
     * The products of the agent shop that a customer already bought. They are
     * shown before the top up options, so the customer can choose the panel
     * that should be charged.
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
                $st = $pdo->prepare("SELECT id, name, description, price, credit FROM agent_panel_product WHERE status_product = 'active' ORDER BY display_order, id");
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $st = $pdo->prepare("SELECT i.id_invoice AS id, p.id AS product_id, p.name_product AS name, p.note AS description, p.price_product AS price, p.Location AS panel_name FROM invoice i INNER JOIN product p ON p.name_product = i.name_product AND p.agent = 'agentshop' WHERE i.id_user = ? AND i.Status IN $active GROUP BY p.id ORDER BY MAX(i.time_sell) DESC");
                $st->execute([$userId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log('nexora_agent_shop_bought_products: ' . $e->getMessage());
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || isset($out[$name])) {
                continue;
            }
            $out[$name] = $row;
        }
        return $out;
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
        sendmessage($userId, "❓ سوال ۱ از " . count($questions) . ":\n" . strip_tags((string) $questions[0]['question']), null, 'HTML');
        return true;
    }
}

if (!function_exists('nexora_agent_shop_finish')) {
    /**
     * The order is complete: the administrator gets the details of the payment
     * and of the answers, the customer gets the confirmation.
     */
    function nexora_agent_shop_finish(string $invoiceId, string $userId): bool
    {
        global $pdo, $textbotlang;
        $invoice = select("invoice", "*", "id_invoice", $invoiceId, "select");
        if (!$invoice) {
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
        $answers = json_decode((string) (select("user", "Processing_value_four", "id", $userId, "select")['Processing_value_four'] ?? '[]'), true);
        $answers = is_array($answers) ? $answers : [];
        $user = select("user", "*", "id", $userId, "select");
        $userName = trim((string) ($user['username'] ?? ''));
        $price = (float) ($invoice['price_product'] ?? 0);

        // the customer gets the confirmation
        $buyerText = ($textbotlang['users']['agentShop']['registered'] ?? "✅ مشخصات شما ثبت شد و سفارش نهایی شد.\n\nعامل انسانی نهایتا تا ۲۴ ساعت پنل شما رو تنظیم و به دستتون می‌رسونه. برای تحویل و دریافت پنل به پشتیبانی مراجعه کنید و پیام بدین.");
        $buyerText = strtr($buyerText, [
            '{name}' => $productName,
            '{price}' => number_format($price),
        ]);
        sendmessage($userId, $buyerText, null, 'HTML');

        // the administrator gets the details of the payment and the answers
        $adminText = "🛒 <b>سفارش فروشگاه نمایندگی</b>\n"
            . "👤 کاربر: " . $userId . ($userName !== '' ? " (" . $userName . ")" : '') . "\n"
            . "🏷 محصول: " . $productName . "\n"
            . "💳 مبلغ پرداختی: " . number_format($price) . " تومان\n"
            . "🧾 شماره فاکتور: " . $invoiceId . "\n"
            . "🖥 پنل درخواستی: " . (string) ($invoice['Service_location'] ?? '-') . "\n";
        if ($questions) {
            $adminText .= "\n❓ <b>پاسخ‌های مشتری:</b>\n" . nexora_agent_shop_format_answers($questions, $answers) . "\n";
        }
        if (!empty($marker[2])) {
            $adminText .= "📝 توضیحات محصول: " . strip_tags((string) $marker[2]) . "\n";
        }
        $adminText .= "⚠️ این سفارش باید دستی برای خریدار تحویل شود.";
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
