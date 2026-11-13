<?php

// The test accounts are created active: they must not wait for the first
// connection of the customer before they can be used. The administrator can
// switch the behaviour on again in the panel management of the bot.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('marzban_panel')) {
        return;
    }
    try {
        $pdo->exec("UPDATE marzban_panel SET on_hold_test = '0' WHERE on_hold_test IS NULL OR on_hold_test <> '0'");
    } catch (Throwable $e) {
        // the column does not exist yet - the schema adds it
    }
};
