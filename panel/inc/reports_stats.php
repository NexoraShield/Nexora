<?php
// ---------------------------------------------------------------------------
// The numbers of the page "گزارش‌ها و آمار" of the web panel.
//
// Every number of the page is read from the database of the bot: the invoices
// of the services, the payments of the customers and the rows of the users.
// A number that the database can not answer is shown as a dash and never as a
// wrong zero, so an empty table never looks like a table full of zeros.
//
// The helpers of this file are used by the page only, but they are written so
// that a database that was never migrated still works: every column that may
// be missing is checked before it is used in a query.
// ---------------------------------------------------------------------------

if (!function_exists('panel_reports_sold_statuses')) {
    /**
     * The states of an invoice that mean "this service was really sold".
     */
    function panel_reports_sold_statuses(): array
    {
        return ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'];
    }
}

if (!function_exists('panel_reports_running_statuses')) {
    /**
     * The states of a service that is still running (the "اشتراک" money).
     */
    function panel_reports_running_statuses(): array
    {
        return ['active', 'end_of_volume', 'sendedwarn', 'send_on_hold'];
    }
}

if (!function_exists('panel_reports_range_days')) {
    /**
     * The length of the period of the page. The administrator can pick it.
     */
    function panel_reports_range_days(): int
    {
        $allowed = [7, 14, 30, 90, 365];
        $days = isset($_GET['range']) ? (int) $_GET['range'] : 30;
        return in_array($days, $allowed, true) ? $days : 30;
    }
}

if (!function_exists('panel_reports_weekday_names')) {
    /**
     * The names of the week in Persian. The week of Iran starts on Saturday.
     */
    function panel_reports_weekday_names(): array
    {
        return ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
    }
}

if (!function_exists('panel_reports_weekday_index')) {
    /**
     * The number of a day of the week, counted from Saturday.
     */
    function panel_reports_weekday_index(int $timestamp): int
    {
        return ((int) date('w', $timestamp) + 1) % 7;
    }
}

if (!function_exists('panel_reports_gateway_name')) {
    /**
     * The name of a gateway in Persian. The list of the gateways of the bot is
     * the one of the payment page, so the name of a gateway never changes from
     * one page of the panel to the other.
     */
    function panel_reports_gateway_name(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return 'نامشخص';
        }
        if (function_exists('nexora_payment_gateways')) {
            $gateways = nexora_payment_gateways();
            if (isset($gateways[$key]['label']) && trim((string) $gateways[$key]['label']) !== '') {
                return (string) $gateways[$key]['label'];
            }
        }
        return $key;
    }
}

if (!function_exists('panel_reports_time_expression')) {
    /**
     * The time of a sale as the number of the seconds, in a query.
     *
     * The column of the time carries two kinds of values: the number of the
     * seconds and the text of the date ("2026/11/13 14:53:58"). A query that
     * only reads the number of the seconds sees the year 2026 for a date of
     * the year 2026 and misses every sale of the database.
     */
    function panel_reports_time_expression(string $column = 'time_sell'): string
    {
        return "CASE"
            . " WHEN $column REGEXP '^[0-9]{4}[-/][0-9]{1,2}[-/][0-9]{1,2}' THEN IFNULL(UNIX_TIMESTAMP(REPLACE($column, '-', '/')), 0)"
            . " WHEN $column REGEXP '^[0-9]{9,11}$' THEN CAST($column AS UNSIGNED)"
            . " ELSE 0 END";
    }
}

if (!function_exists('panel_reports_parse_time')) {
    /**
     * The time of a sale as the number of the seconds, in php.
     */
    function panel_reports_parse_time($value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $timestamp = strtotime(str_replace('-', '/', $value));
        return $timestamp ? (int) $timestamp : 0;
    }
}

if (!function_exists('panel_reports_plain_name')) {
    /**
     * The name of a product or of a panel without the tags of the premium
     * emoji (the panel shows the emoji as a picture).
     */
    function panel_reports_plain_name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === 'none') {
            return '—';
        }
        // The premium emoji of the bot is written as {emoji:slug}: it becomes
        // the character of the emoji, so the name of the product is readable.
        if (strpos($name, '{emoji:') !== false) {
            $name = (string) preg_replace_callback('/\{emoji:([^}]+)\}/u', function ($m) {
                if (function_exists('nexora_emoji_lookup')) {
                    $row = nexora_emoji_lookup(trim((string) $m[1]));
                    if (is_array($row) && trim((string) ($row['emoji_utf8'] ?? '')) !== '') {
                        return (string) $row['emoji_utf8'];
                    }
                }
                return '';
            }, $name);
        }
        $clean = trim((string) preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $name));
        $clean = trim((string) preg_replace('/\s{2,}/u', ' ', $clean));
        return $clean !== '' ? $clean : $name;
    }
}

if (!function_exists('panel_reports_sum')) {
    /**
     * The sum of a list of numbers.
     */
    function panel_reports_sum(array $numbers): float
    {
        $sum = 0.0;
        foreach ($numbers as $number) {
            $sum += (float) $number;
        }
        return $sum;
    }
}

if (!function_exists('panel_reports_data')) {
    /**
     * Every number of the page.
     *
     * @return array The whole report: the cards, the charts and the tables.
     */
    function panel_reports_data(PDO $pdo, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $sold = panel_reports_sold_statuses();
        $running = panel_reports_running_statuses();
        $invoiceColumns = panel_table_columns($pdo, 'invoice');
        $paymentColumns = panel_table_columns($pdo, 'Payment_report');
        $userColumns = panel_table_columns($pdo, 'user');

        $report = [
            'days' => $days,
            'from' => strtotime('-' . ($days - 1) . ' days', strtotime('today')),
            'to' => time(),
            'has_invoice' => (bool) $invoiceColumns,
            'has_payment' => (bool) $paymentColumns,
            'kpi' => [
                'revenue' => 0.0,        // درآمد کل
                'subscription' => 0.0,   // درآمد اشتراک
                'expired' => 0.0,        // درآمد سررسیدهای جایگاهی
                'services' => 0,         // تعداد سرویس
                'remaining_days' => 0,   // مدت باقیمانده
                'purchases' => 0,        // تعداد خرید
                'buyers' => 0,           // the number of the customers that bought
                'per_buyer' => 0.0,      // مبلغ کل درآمد هر خریدار
            ],
            'peak' => [
                'product' => ['name' => '', 'count' => 0, 'price' => 0.0],
                'day' => ['name' => '', 'count' => 0],
                'hour' => ['hour' => 0, 'count' => 0],
                'gateway' => ['name' => '', 'amount' => 0.0],
            ],
            'timeline' => [],     // the two series of the "ترکیب زمان"
            'trend' => [],        // the sales and the revenue of every day
            'week' => [],         // the sales of every day of the week
            'hours' => [],        // the sales of every hour of the day
            'growth' => [],       // the new users and the users in total
            'products' => [],     // the best selling products
            'locations' => [],    // the money per panel
            'services' => [],     // the money per service (the category)
            'payments' => [
                'paid' => 0,
                'unpaid' => 0,
                'success' => 0,
                'failed' => 0,
                'pending' => 0,
                'conversion' => 0.0,
                'success_rate' => 0.0,
                'gateways' => [],
            ],
        ];

        // ------------------------------------------------------------------
        // The cards: the invoices of the bot.
        // ------------------------------------------------------------------
        if ($invoiceColumns) {
            $priceColumn = in_array('price_product', $invoiceColumns, true) ? 'price_product' : "'0'";
            $statusColumn = in_array('Status', $invoiceColumns, true) ? 'Status' : (in_array('status', $invoiceColumns, true) ? 'status' : "''");
            $statusList = "'" . implode("','", array_map('strval', $sold)) . "'";
            $runningList = "'" . implode("','", array_map('strval', $running)) . "'";
            try {
                $row = $pdo->query(
                    "SELECT COALESCE(SUM(CAST($priceColumn AS DECIMAL(20,2))),0) AS total,"
                    . " COALESCE(SUM(CASE WHEN $statusColumn IN ($runningList) THEN CAST($priceColumn AS DECIMAL(20,2)) ELSE 0 END),0) AS running,"
                    . " COALESCE(SUM(CASE WHEN $statusColumn = 'end_of_time' THEN CAST($priceColumn AS DECIMAL(20,2)) ELSE 0 END),0) AS expired,"
                    . " COUNT(*) AS services"
                    . " FROM invoice WHERE $statusColumn IN ($statusList)"
                )->fetch(PDO::FETCH_ASSOC) ?: [];
                $report['kpi']['revenue'] = (float) ($row['total'] ?? 0);
                $report['kpi']['subscription'] = (float) ($row['running'] ?? 0);
                $report['kpi']['expired'] = (float) ($row['expired'] ?? 0);
                $report['kpi']['services'] = (int) ($row['services'] ?? 0);
            } catch (Throwable $e) {
                error_log('panel_reports_data (kpi): ' . $e->getMessage());
            }

            // The days that are left on the running services.
            $timeColumn = in_array('time_sell', $invoiceColumns, true) ? panel_reports_time_expression('time_sell') : '0';
            // The length of a service is capped: a row with a broken number can
            // never make the sum of the query overflow.
            $durationColumn = in_array('Service_time', $invoiceColumns, true)
                ? "LEAST(CAST(Service_time AS SIGNED), 36500)"
                : '0';
            try {
                $row = $pdo->query(
                    "SELECT COALESCE(SUM(GREATEST(0, (GREATEST($timeColumn, 0) + $durationColumn * 86400) - " . time() . ")),0) AS remaining"
                    . " FROM invoice WHERE $statusColumn IN ($runningList)"
                )->fetch(PDO::FETCH_ASSOC) ?: [];
                $report['kpi']['remaining_days'] = (int) round(((float) ($row['remaining'] ?? 0)) / 86400);
            } catch (Throwable $e) {
                error_log('panel_reports_data (remaining): ' . $e->getMessage());
            }

            // The number of the customers that bought something.
            try {
                $row = $pdo->query(
                    "SELECT COUNT(DISTINCT id_user) AS buyers FROM invoice WHERE $statusColumn IN ($statusList)"
                )->fetch(PDO::FETCH_ASSOC) ?: [];
                $report['kpi']['buyers'] = (int) ($row['buyers'] ?? 0);
            } catch (Throwable $e) {
                error_log('panel_reports_data (buyers): ' . $e->getMessage());
            }
            if ($report['kpi']['buyers'] > 0) {
                $report['kpi']['per_buyer'] = $report['kpi']['revenue'] / $report['kpi']['buyers'];
            }

            // The best selling product (of the whole time).
            $nameColumn = in_array('name_product', $invoiceColumns, true) ? 'name_product' : "''";
            try {
                $rows = $pdo->query(
                    "SELECT $nameColumn AS name, COUNT(*) AS total, MAX(CAST($priceColumn AS DECIMAL(20,2))) AS price"
                    . " FROM invoice WHERE $statusColumn IN ($statusList)"
                    . " GROUP BY $nameColumn ORDER BY total DESC LIMIT 1"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($rows) {
                    $report['peak']['product'] = [
                        'name' => panel_reports_plain_name((string) ($rows[0]['name'] ?? '')),
                        'count' => (int) ($rows[0]['total'] ?? 0),
                        'price' => (float) ($rows[0]['price'] ?? 0),
                    ];
                }
            } catch (Throwable $e) {
                error_log('panel_reports_data (top product): ' . $e->getMessage());
            }
        }

        // ------------------------------------------------------------------
        // The payments: the number of the buys and the state of the orders.
        // ------------------------------------------------------------------
        if ($paymentColumns) {
            $statusColumn = in_array('payment_Status', $paymentColumns, true) ? 'payment_Status' : "''";
            $priceColumn = in_array('price', $paymentColumns, true) ? 'CAST(price AS DECIMAL(20,2))' : '0';
            try {
                $rows = $pdo->query(
                    "SELECT $statusColumn AS state, COUNT(*) AS total, COALESCE(SUM($priceColumn),0) AS amount"
                    . " FROM Payment_report GROUP BY $statusColumn"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $paid = 0;
                $unpaid = 0;
                $success = 0;
                $failed = 0;
                $pending = 0;
                foreach ($rows as $row) {
                    $state = strtolower(trim((string) ($row['state'] ?? '')));
                    $total = (int) ($row['total'] ?? 0);
                    if ($state === 'paid') {
                        $paid += $total;
                        $success += $total;
                        continue;
                    }
                    if ($state === 'reject' || $state === 'expire' || $state === 'failed') {
                        $failed += $total;
                        $unpaid += $total;
                        continue;
                    }
                    // Everything else is still waiting for its money.
                    $pending += $total;
                    $unpaid += $total;
                }
                $report['payments']['paid'] = $paid;
                $report['payments']['unpaid'] = $unpaid;
                $report['payments']['success'] = $success;
                $report['payments']['failed'] = $failed;
                $report['payments']['pending'] = $pending;
                $report['kpi']['purchases'] = $paid;
                $all = $paid + $unpaid;
                $report['payments']['conversion'] = $all > 0 ? round($paid / $all * 100, 1) : 0.0;
                $finished = $success + $failed;
                $report['payments']['success_rate'] = $finished > 0 ? round($success / $finished * 100, 1) : 0.0;
            } catch (Throwable $e) {
                error_log('panel_reports_data (payments): ' . $e->getMessage());
            }

            // The money of every gateway.
            $methodColumn = in_array('Payment_Method', $paymentColumns, true) ? 'Payment_Method' : "''";
            try {
                $rows = $pdo->query(
                    "SELECT $methodColumn AS method, COALESCE(SUM($priceColumn),0) AS amount, COUNT(*) AS total"
                    . " FROM Payment_report WHERE $statusColumn = 'paid'"
                    . " GROUP BY $methodColumn ORDER BY amount DESC LIMIT 12"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    $report['payments']['gateways'][] = [
                        'name' => panel_reports_gateway_name((string) ($row['method'] ?? '')),
                        'amount' => (float) ($row['amount'] ?? 0),
                        'count' => (int) ($row['total'] ?? 0),
                    ];
                }
            } catch (Throwable $e) {
                error_log('panel_reports_data (gateways): ' . $e->getMessage());
            }
        }

        // ------------------------------------------------------------------
        // The charts of the period: every invoice of the range.
        // ------------------------------------------------------------------
        if ($invoiceColumns) {
            $priceColumn = in_array('price_product', $invoiceColumns, true) ? "CAST(price_product AS DECIMAL(20,2))" : '0';
            $statusColumn = in_array('Status', $invoiceColumns, true) ? 'Status' : (in_array('status', $invoiceColumns, true) ? 'status' : "''");
            // The raw time of the sale: it is read in php, so the day of a sale
            // is the day of the shop and not the day of the server.
            $rawTimeColumn = in_array('time_sell', $invoiceColumns, true) ? 'time_sell' : "''";
            $timeColumn = in_array('time_sell', $invoiceColumns, true) ? panel_reports_time_expression('time_sell') : '0';
            $locationColumn = in_array('Service_location', $invoiceColumns, true) ? 'Service_location' : "''";
            $nameColumn = in_array('name_product', $invoiceColumns, true) ? 'name_product' : "''";
            $statusList = "'" . implode("','", array_map('strval', $sold)) . "'";
            try {
                // The filter of the period is a day wider: the exact borders
                // are checked in php, so a sale of the first day is never lost.
                $rows = $pdo->query(
                    "SELECT $rawTimeColumn AS sold_raw, $priceColumn AS price, $statusColumn AS state,"
                    . " $locationColumn AS location, $nameColumn AS name"
                    . " FROM invoice WHERE $statusColumn IN ($statusList) AND $timeColumn >= " . ((int) $report['from'] - 86400)
                    . " AND $timeColumn <= " . ((int) $report['to'] + 86400)
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                error_log('panel_reports_data (range): ' . $e->getMessage());
                $rows = [];
            }

            // The days of the period.
            $dayBuckets = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $day = date('Y/m/d', strtotime('-' . $i . ' days'));
                $dayBuckets[$day] = [
                    'day' => $day,
                    'label' => date('m/d', strtotime($day)),
                    'subscription' => 0.0,
                    'expired' => 0.0,
                    'revenue' => 0.0,
                    'count' => 0,
                ];
            }
            $week = array_fill(0, 7, ['count' => 0, 'revenue' => 0.0]);
            $hours = array_fill(0, 24, ['count' => 0, 'revenue' => 0.0]);
            $products = [];
            $locations = [];
            $services = [];
            $runningStates = panel_reports_running_statuses();

            foreach ($rows as $row) {
                $soldAt = panel_reports_parse_time($row['sold_raw'] ?? 0);
                if ($soldAt <= 0 || $soldAt < $report['from'] || $soldAt > $report['to']) {
                    continue;
                }
                $price = (float) ($row['price'] ?? 0);
                $state = (string) ($row['state'] ?? '');
                $day = date('Y/m/d', $soldAt);
                if (isset($dayBuckets[$day])) {
                    $dayBuckets[$day]['count']++;
                    $dayBuckets[$day]['revenue'] += $price;
                    if (in_array($state, $runningStates, true)) {
                        $dayBuckets[$day]['subscription'] += $price;
                    } else {
                        $dayBuckets[$day]['expired'] += $price;
                    }
                }
                $weekIndex = panel_reports_weekday_index($soldAt);
                $week[$weekIndex]['count']++;
                $week[$weekIndex]['revenue'] += $price;
                $hour = (int) date('G', $soldAt);
                $hours[$hour]['count']++;
                $hours[$hour]['revenue'] += $price;

                $productName = panel_reports_plain_name((string) ($row['name'] ?? ''));
                if (!isset($products[$productName])) {
                    $products[$productName] = ['name' => $productName, 'count' => 0, 'revenue' => 0.0];
                }
                $products[$productName]['count']++;
                $products[$productName]['revenue'] += $price;

                $location = trim((string) ($row['location'] ?? ''));
                $location = $location !== '' ? panel_reports_plain_name($location) : 'بدون لوکیشن';
                if (!isset($locations[$location])) {
                    $locations[$location] = ['name' => $location, 'count' => 0, 'revenue' => 0.0];
                }
                $locations[$location]['count']++;
                $locations[$location]['revenue'] += $price;

                // The type of the service: the category of the product, and the
                // name of the product when the category is empty.
                $type = panel_reports_service_type($pdo, (string) ($row['name'] ?? ''));
                if (!isset($services[$type])) {
                    $services[$type] = ['name' => $type, 'count' => 0, 'revenue' => 0.0];
                }
                $services[$type]['count']++;
                $services[$type]['revenue'] += $price;
            }

            $report['timeline'] = array_values($dayBuckets);
            $report['trend'] = array_values($dayBuckets);
            $weekNames = panel_reports_weekday_names();
            $report['week'] = [];
            foreach ($week as $index => $data) {
                $report['week'][] = [
                    'name' => $weekNames[$index],
                    'count' => $data['count'],
                    'revenue' => $data['revenue'],
                ];
            }
            $report['hours'] = [];
            foreach ($hours as $hour => $data) {
                $report['hours'][] = [
                    'hour' => $hour,
                    'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
                    'count' => $data['count'],
                    'revenue' => $data['revenue'],
                ];
            }
            uasort($products, function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });
            $report['products'] = array_slice(array_values($products), 0, 12);
            uasort($locations, function ($a, $b) {
                return $b['revenue'] <=> $a['revenue'];
            });
            $report['locations'] = array_slice(array_values($locations), 0, 8);
            uasort($services, function ($a, $b) {
                return $b['revenue'] <=> $a['revenue'];
            });
            $report['services'] = array_slice(array_values($services), 0, 8);

            // The busiest day of the week and the busiest hour of the day.
            $bestDay = null;
            foreach ($report['week'] as $day) {
                if ($bestDay === null || $day['count'] > $bestDay['count']) {
                    $bestDay = $day;
                }
            }
            if ($bestDay && $bestDay['count'] > 0) {
                $report['peak']['day'] = ['name' => $bestDay['name'], 'count' => $bestDay['count']];
            }
            $bestHour = null;
            foreach ($report['hours'] as $hour) {
                if ($bestHour === null || $hour['count'] > $bestHour['count']) {
                    $bestHour = $hour;
                }
            }
            if ($bestHour && $bestHour['count'] > 0) {
                $report['peak']['hour'] = ['hour' => $bestHour['hour'], 'count' => $bestHour['count']];
            }
        }

        // The best gateway of the period.
        if ($report['payments']['gateways']) {
            $report['peak']['gateway'] = $report['payments']['gateways'][0];
        }

        // ------------------------------------------------------------------
        // The growth of the users: the new users of every day and the number of
        // the users in total.
        // ------------------------------------------------------------------
        if ($userColumns && in_array('register', $userColumns, true)) {
            try {
                $rows = $pdo->query(
                    "SELECT CAST(register AS UNSIGNED) AS joined FROM user"
                    . " WHERE CAST(register AS UNSIGNED) >= " . (int) $report['from']
                    . " ORDER BY joined ASC"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                error_log('panel_reports_data (growth): ' . $e->getMessage());
                $rows = [];
            }
            $growth = [];
            foreach ($report['timeline'] as $day) {
                $growth[$day['day']] = ['day' => $day['day'], 'label' => $day['label'], 'new' => 0, 'total' => 0];
            }
            foreach ($rows as $row) {
                $joined = (int) ($row['joined'] ?? 0);
                if ($joined <= 0) {
                    continue;
                }
                $day = date('Y/m/d', $joined);
                if (isset($growth[$day])) {
                    $growth[$day]['new']++;
                }
            }
            // The users that were already there before the period.
            try {
                $row = $pdo->query(
                    "SELECT COUNT(*) AS total FROM user WHERE CAST(register AS UNSIGNED) < " . (int) $report['from']
                )->fetch(PDO::FETCH_ASSOC) ?: [];
                $running = (int) ($row['total'] ?? 0);
            } catch (Throwable $e) {
                $running = 0;
            }
            foreach ($growth as $day => $data) {
                $running += $data['new'];
                $growth[$day]['total'] = $running;
            }
            $report['growth'] = array_values($growth);
        }

        return $report;
    }
}

if (!function_exists('panel_reports_service_type')) {
    /**
     * The type of a service: the category of the product. The name of the
     * product is used when the product has no category.
     */
    function panel_reports_service_type(PDO $pdo, string $productName): string
    {
        static $cache = [];
        if (array_key_exists($productName, $cache)) {
            return $cache[$productName];
        }
        $type = 'سایر';
        $productName = trim($productName);
        if ($productName !== '') {
            try {
                $st = $pdo->prepare("SELECT category FROM product WHERE name_product = ? LIMIT 1");
                $st->execute([$productName]);
                $value = $st->fetchColumn();
                if (is_string($value) && trim($value) !== '') {
                    $type = trim($value);
                } else {
                    $type = panel_reports_plain_name($productName);
                }
            } catch (Throwable $e) {
                $type = panel_reports_plain_name($productName);
            }
        }
        $cache[$productName] = $type;
        return $type;
    }
}
