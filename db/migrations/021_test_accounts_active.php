<?php

// The test accounts are created active: they must not wait for the first
// connection of the customer before they can be used.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('marzban_panel')) {
        return;
    }
    try {
        $pdo->exec("UPDATE marzban_panel SET on_hold_test = '0' WHERE on_hold_test IS NULL OR on_hold_test NOT IN ('0','1')");
        // only the panels that never got a test account are switched over, so a
        // panel that was set up on purpose keeps its setting
        $pdo->exec("UPDATE marzban_panel SET on_hold_test = '0' WHERE on_hold_test = '1' AND (SELECT COUNT(*) FROM invoice WHERE Service_location = marzban_panel.name_panel AND Status = 'active') = 0");
    } catch (Throwable $e) {
        // the column does not exist yet - the schema adds it
    }
};
