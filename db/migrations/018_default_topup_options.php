<?php

// The default top up options: ten steps for the agent panel shop (one to ten
// million) and ten steps for the agent shop (one to ten terabyte). Every
// option can be renamed, re-priced, re-coloured and given a premium emoji in
// the web panel.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('panel_topup_product')) {
        return;
    }
    try {
        $pdo->exec("ALTER TABLE panel_topup_product ADD COLUMN unit VARCHAR(32) NULL");
    } catch (Throwable $e) {
        // the column exists already
    }

    $defaults = [
        'agentpanelshop' => [
            'unit' => 'Toman',
            'items' => [
                ['name' => 'شارژ ۱ میلیون', 'price' => 500000, 'credit' => 1000000, 'description' => 'یک میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۲ میلیون', 'price' => 1000000, 'credit' => 2000000, 'description' => 'دو میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۳ میلیون', 'price' => 1500000, 'credit' => 3000000, 'description' => 'سه میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۴ میلیون', 'price' => 2000000, 'credit' => 4000000, 'description' => 'چهار میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۵ میلیون', 'price' => 2500000, 'credit' => 5000000, 'description' => 'پنج میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۶ میلیون', 'price' => 3000000, 'credit' => 6000000, 'description' => 'شش میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۷ میلیون', 'price' => 3500000, 'credit' => 7000000, 'description' => 'هفت میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۸ میلیون', 'price' => 4000000, 'credit' => 8000000, 'description' => 'هشت میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۹ میلیون', 'price' => 4500000, 'credit' => 9000000, 'description' => 'نه میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
                ['name' => 'شارژ ۱۰ میلیون', 'price' => 5000000, 'credit' => 10000000, 'description' => 'ده میلیون تومان اعتبار به پنل شما اضافه می‌شود.'],
            ],
        ],
        'agentshop' => [
            'unit' => 'GB',
            'items' => [
                ['name' => 'شارژ ۱ ترابایت', 'price' => 7000000, 'credit' => 1024, 'description' => 'یک ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۲ ترابایت', 'price' => 14000000, 'credit' => 2048, 'description' => 'دو ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۳ ترابایت', 'price' => 21000000, 'credit' => 3072, 'description' => 'سه ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۴ ترابایت', 'price' => 28000000, 'credit' => 4096, 'description' => 'چهار ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۵ ترابایت', 'price' => 35000000, 'credit' => 5120, 'description' => 'پنج ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۶ ترابایت', 'price' => 42000000, 'credit' => 6144, 'description' => 'شش ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۷ ترابایت', 'price' => 49000000, 'credit' => 7168, 'description' => 'هفت ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۸ ترابایت', 'price' => 56000000, 'credit' => 8192, 'description' => 'هشت ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۹ ترابایت', 'price' => 63000000, 'credit' => 9216, 'description' => 'نه ترابایت به پنل انتخابی شما اضافه می‌شود.'],
                ['name' => 'شارژ ۱۰ ترابایت', 'price' => 70000000, 'credit' => 10240, 'description' => 'ده ترابایت به پنل انتخابی شما اضافه می‌شود.'],
            ],
        ],
    ];

    $insert = $pdo->prepare(
        "INSERT INTO panel_topup_product (shop,name,price,credit,description,unit,btn_style,status_product,display_order) VALUES (?,?,?,?,?,?,?,'active',?)"
    );
    foreach ($defaults as $shop => $config) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM panel_topup_product WHERE shop = '" . addslashes($shop) . "'")->fetchColumn();
        if ($count > 0) {
            continue;
        }
        $order = 0;
        foreach ($config['items'] as $item) {
            $order++;
            $insert->execute([
                $shop,
                $item['name'],
                $item['price'],
                $item['credit'],
                $item['description'],
                $config['unit'],
                'primary',
                $order,
            ]);
        }
        echo "The default top up options were created for {$shop}.\n";
    }
};
