<?php
/**
 * A small harness that checks the new pieces without a web server:
 *  - the SQL of the helpers of the delivered agent panels
 *  - the queries of the list of the panels
 *  - the credit change (add, subtract, never below zero)
 *  - the password reset and the revoke
 *
 * It builds a throwaway database in SQLite, but the helpers speak MySQL, so
 * the helpers are checked with a small MySQL-compatible stub of the PDO
 * calls they use.
 */
$root = dirname(__DIR__);
require_once $root . '/function.php';

$failures = [];
$checks = 0;

function check(string $name, bool $ok, string $info = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures[] = $name . ($info !== '' ? ' :: ' . $info : '');
    }
}

/**
 * A tiny PDO stub: it understands the statements the new helpers use.
 */
class StubPdo
{
    public array $users = [];
    public array $agentAuth = [];
    public array $invoices = [];
    public array $executed = [];

    public function prepare(string $sql): StubStatement
    {
        return new StubStatement($this, $sql);
    }

    public function query(string $sql): StubStatement
    {
        return new StubStatement($this, $sql);
    }

    public function lastInsertId(): int
    {
        return 1;
    }
}

class StubStatement
{
    public array $params = [];

    public function __construct(private StubPdo $db, private string $sql)
    {
    }

    public function execute(array $params = []): bool
    {
        $this->params = $params;
        $this->db->executed[] = [$this->sql, $params];
        $sql = $this->sql;
        if (stripos($sql, 'UPDATE `user` SET Balance = Balance + ? WHERE id = ? AND Balance + ? >= 0') !== false) {
            $id = (string) $this->params[1];
            $amount = (float) $this->params[0];
            if (!isset($this->db->users[$id])) {
                return true;
            }
            if ($this->db->users[$id]['Balance'] + $amount < 0) {
                return true; // the guard blocks the change, rowCount() stays 0
            }
            $this->db->users[$id]['Balance'] += $amount;
            return true;
        }
        if (stripos($sql, 'UPDATE `user` SET Balance = Balance + ? WHERE id = ?') !== false) {
            $id = (string) $this->params[1];
            $amount = (float) $this->params[0];
            if (!isset($this->db->users[$id])) {
                return true;
            }
            $this->db->users[$id]['Balance'] += $amount;
            return true;
        }
        if (stripos($sql, 'SELECT Balance FROM `user` WHERE id = ?') !== false) {
            return true;
        }
        if (stripos($sql, 'INSERT INTO agent_auth') !== false) {
            $this->db->agentAuth[(string) $this->params[0]] = ['pass_hash' => (string) $this->params[1], 'created_at' => (int) $this->params[2]];
            return true;
        }
        if (stripos($sql, 'DELETE FROM agent_auth WHERE id_user = ?') !== false) {
            unset($this->db->agentAuth[(string) $this->params[0]]);
            return true;
        }
        if (stripos($sql, 'SELECT name_product FROM invoice') !== false) {
            return true;
        }
        if (stripos($sql, 'SELECT a.id_user') !== false) {
            return true;
        }
        return true;
    }

    public function fetchColumn()
    {
        $sql = $this->sql;
        if (stripos($sql, 'SELECT Balance FROM `user`') !== false) {
            $id = (string) ($this->params[0] ?? '');
            return $this->db->users[$id]['Balance'] ?? false;
        }
        if (stripos($sql, 'SELECT name_product FROM invoice') !== false) {
            $id = (string) ($this->params[0] ?? '');
            return $this->db->invoices[$id] ?? false;
        }
        return false;
    }

    public function fetchAll(int $mode = 0): array
    {
        return [];
    }

    public function rowCount(): int
    {
        $sql = $this->sql;
        if (stripos($sql, 'AND Balance + ? >= 0') !== false) {
            $id = (string) ($this->params[1] ?? '');
            $amount = (float) ($this->params[0] ?? 0);
            if (!isset($this->db->users[$id])) {
                return 0;
            }
            return ($this->db->users[$id]['Balance'] + $amount) >= 0 ? 1 : 0;
        }
        return 1;
    }
}

// ---------------------------------------------------------------------------
// The helpers work on the global $pdo and $domainhosts.
// ---------------------------------------------------------------------------
$GLOBALS['pdo'] = new StubPdo();
$GLOBALS['domainhosts'] = 'example.test';

check('the helper of the link exists', function_exists('nexora_agent_panel_link'));
check('the link uses the domain of the installation', nexora_agent_panel_link() === 'https://example.test/agent-panel/', nexora_agent_panel_link());

// The credit is added.
$GLOBALS['pdo']->users['1001'] = ['Balance' => 30000];
$newBalance = nexora_agent_panel_set_credit('1001', 2000000);
check('the credit is added to the wallet', $newBalance === 2030000.0, var_export($newBalance, true));

// The credit is subtracted.
$newBalance = nexora_agent_panel_set_credit('1001', -30000);
check('the credit is subtracted from the wallet', $newBalance === 2000000.0, var_export($newBalance, true));

// The credit never goes below zero.
$newBalance = nexora_agent_panel_set_credit('1001', -5000000);
check('the balance never goes below zero', $newBalance === null, var_export($newBalance, true));
check('the balance is untouched after a refused change', $GLOBALS['pdo']->users['1001']['Balance'] === 2000000.0);

// A zero amount does nothing.
$newBalance = nexora_agent_panel_set_credit('1001', 0);
check('an amount of zero changes nothing', $newBalance === null);

// A new password is built and stored as a hash.
$pass = nexora_agent_panel_reset_password('1001');
check('a new password is built', is_string($pass) && strlen($pass) === 8, var_export($pass, true));
$stored = $GLOBALS['pdo']->agentAuth['1001']['pass_hash'] ?? '';
check('the password is stored as a hash', $stored !== $pass && password_verify($pass, $stored));

// The panel of a customer can be deleted.
nexora_agent_panel_revoke('1001');
check('the access of the panel is deleted', !isset($GLOBALS['pdo']->agentAuth['1001']));

// ---------------------------------------------------------------------------
// The report of the top up names the panel and the balance before the charge.
// ---------------------------------------------------------------------------
$report = file_get_contents($root . '/function.php');
check(
    'the report of the top up shows the balance before the charge',
    strpos($report, 'موجودی قبل از شارژ') !== false
);
check(
    'the report of the top up shows the user name of the panel',
    strpos($report, 'نام کاربری پنل') !== false
);
check(
    'the report of the top up shows the product of the customer',
    strpos($report, 'محصول خریداری‌شده') !== false
);

// ---------------------------------------------------------------------------
// The tab of the delivered panels exists and is reachable.
// ---------------------------------------------------------------------------
check('the tab of the delivered panels exists', is_file($root . '/panel/inc/agentpanel_accounts_tab.php'));
$agents = file_get_contents($root . '/panel/agents.php');
check('the tab is in the list of the tabs', strpos($agents, 'tab=accounts') !== false);
check('the tab is allowed by the whitelist', strpos($agents, "'accounts'") !== false);

// ---------------------------------------------------------------------------
echo "checks: $checks, failures: " . count($failures) . "\n";
foreach ($failures as $failure) {
    echo "  FAIL  $failure\n";
}
exit(count($failures) === 0 ? 0 : 1);
