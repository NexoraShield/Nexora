<?php

// The default products of the agent panel shop (اجنت پنل): ten packages with
// one to ten million of credit. The price of a package is the same as the
// credit it puts into the wallet of the customer.
//
// The administrator can rename them, re-price them, give them a premium emoji
// or hide them in the web panel (مدیریت نمایندگان → فروش پنل به نمایندگان)
// or in the bot (مدیریت ربات → محصولات اجنت پنل).
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('agent_panel_product')) {
        return;
    }
    // The marker makes sure that the defaults are only created once: when the
    // administrator deletes every product on purpose, they stay deleted.
    try {
        $chk = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = ? LIMIT 1");
        $chk->execute(['nx_seeded_agent_panel_products']);
        if ((string) $chk->fetchColumn() !== '') {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    $count = (int) $pdo->query("SELECT COUNT(*) FROM agent_panel_product")->fetchColumn();
    if ($count === 0) {
        $digits = ['۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '۱۰'];
        $insert = $pdo->prepare(
            "INSERT INTO agent_panel_product (name, price, credit, description, btn_style, status_product, display_order) VALUES (?,?,?,?,?,'active',?)"
        );
        $order = 0;
        foreach ($digits as $digit) {
            $order++;
            $credit = $order * 1000000;
            $insert->execute([
                'پنل اجنت نمایندگی با ' . $digit . ' میلیون اعتبار',
                $credit,
                $credit,
                'پنل نمایندگی با ' . $digit . ' میلیون تومان اعتبار داخل کیف پول شما.',
                'primary',
                $order,
            ]);
        }
        echo "The default agent panel products were created (10 packages).\n";
    }
    try {
        $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?,?) ON DUPLICATE KEY UPDATE ValuePay = VALUES(ValuePay)")
            ->execute(['nx_seeded_agent_panel_products', '1']);
    } catch (Throwable $e) {
        error_log('migration 022: ' . $e->getMessage());
    }
};
