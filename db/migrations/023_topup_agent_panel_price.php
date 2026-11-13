<?php

// The top up of the agent panel shop (اجنت پنل) costs exactly what it puts
// into the wallet of the customer: a charge of one million costs one million.
//
// The first version of the default options charged half of the credit, so the
// options that still carry that price are repaired here. An option the
// administrator has re-priced is never touched (only the price that is
// exactly the half of the credit is).
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('panel_topup_product')) {
        return;
    }
    try {
        $st = $pdo->prepare(
            "UPDATE panel_topup_product SET price = credit
             WHERE shop = 'agentpanelshop' AND credit > 0 AND price > 0 AND price = credit / 2"
        );
        $st->execute();
        if ($st->rowCount() > 0) {
            echo "The price of " . $st->rowCount() . " top up options of the agent panel shop was repaired.\n";
        }
    } catch (Throwable $e) {
        error_log('migration 023: ' . $e->getMessage());
    }
};
