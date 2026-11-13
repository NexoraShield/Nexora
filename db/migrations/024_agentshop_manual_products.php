<?php

// The products of the reseller shop (فروشگاه نمایندگی) are not services of a
// panel: the administrator delivers them by hand in a private message. The
// column of the panel is emptied for the products that still carry one, so
// no panel of the bot is used for them.
//
// The two panel shops stay apart: the products of the reseller shop live in
// the table "product" with the marker "agentshop", the packages of the agent
// panel live in the table "agent_panel_product" and the top up options of
// every shop live in the table "panel_topup_product" with the shop of the
// option.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('product')) {
        return;
    }
    try {
        $st = $pdo->prepare("UPDATE product SET Location = '' WHERE agent = 'agentshop' AND Location <> ''");
        $st->execute();
        if ($st->rowCount() > 0) {
            echo "The panel of " . $st->rowCount() . " products of the reseller shop was removed.\n";
        }
    } catch (Throwable $e) {
        error_log('migration 024: ' . $e->getMessage());
    }
};
