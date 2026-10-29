<?php
/**
 * Premium Emoji Picker Panel - Nexora Bot
 * Allows admin to select premium emojis for buttons
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../nexora_style.php';
require_auth();

nexora_ensure_emoji_table();
$action = $_GET['action'] ?? '';
$target = $_GET['target'] ?? '';

// Handle emoji selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emoji_id'])) {
    csrf_check_post();
    $emoji_id = trim($_POST['emoji_id']);
    $target = $_POST['target'] ?? '';
    $target_type = $_POST['target_type'] ?? 'button';
    
    // Store emoji mapping in database
        $stmt = $pdo->prepare("INSERT INTO nexora_emoji_map (target_type, target_name, emoji_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE emoji_id = VALUES(emoji_id)");
    $stmt->execute([$target_type, $target, $emoji_id]);
    
    header('Location: emoji_picker.php?target=' . urlencode($target) . '&target_type=' . $target_type . '&saved=1');
    exit;
}

// Get available premium emojis
$emojis = [];
$stmt = $pdo->query("SELECT * FROM bot_custom_emoji ORDER BY emoji_name");
while ($row = $stmt->fetch()) {
    $emojis[] = $row;
}

// Get current mappings
$emoji_map = [];
$stmt = $pdo->query("SELECT * FROM nexora_emoji_map");
while ($row = $stmt->fetch()) {
    $emoji_map[$row['target_type'] . ':' . $row['target_name']] = $row['emoji_id'];
}

$page_title = 'مدیریت ایموجی پریمیوم';
$activeNav = 'emojis';

include __DIR__ . '/inc/layout_head.php';
?>

<div class="page-header">
    <h1>🎭 مدیریت ایموجی پریمیوم</h1>
    <p class="page-lede">انتخاب ایموجی premium برای دکمه‌های ربات</p>
</div>

<div class="card">
    <div class="card-header">
        <h3>📋 لیست ایموجی‌های پریمیوم</h3>
    </div>
    <div class="card-body">
        <div class="emoji-grid">
            <?php foreach ($emojis as $emoji): ?>
                <div class="emoji-item" onclick="selectEmoji('<?= htmlspecialchars($emoji['emoji_id']) ?>', '<?= $emoji['emoji_name'] ?>')">
                    <div class="emoji-char"><?= htmlspecialchars($emoji['custom_emoji_id'] ?? $emoji['emoji_name']) ?></div>
                    <div class="emoji-name"><?= htmlspecialchars($emoji['emoji_name'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>📋 انتخاب هدف</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="emoji_picker.php">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label>نام دکمه/متن:</label>
                <input type="text" name="target" class="input" value="<?= htmlspecialchars($target) ?>" placeholder="مثل: back_btn, panel_btn, buy_btn">
            </div>
            <div class="form-group">
                <label>نوع هدف:</label>
                <select name="target_type" class="input">
                    <option value="button" <?= $target_type === 'button' ? 'selected' : '' ?>>دکمه</option>
                    <option value="panel">panel</option>
                    <option value="category">category</option>
                    <option value="product">product</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">ثبت</button>
        </form>
    </div>
</div>

<?php if (!empty($emoji_map)): ?>
<div class="card mt-4">
    <div class="card-header">
        <h3>📋 ایموجی‌های فعال</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>نوع</th>
                    <th>نام</th>
                    <th>ایموجی</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emoji_map as $key => $emoji_id): ?>
                <?php list($type, $target) = explode(':', $key, 2); ?>
                <tr>
                    <td><?= htmlspecialchars($type) ?></td>
                    <td><?= htmlspecialchars($target) ?></td>
                    <td>
                        <?php
                        $emoji_row = $pdo->prepare("SELECT * FROM bot_custom_emoji WHERE custom_emoji_id = ? OR emoji_name = ?");
                        $emoji_row->execute([$emoji_id, $emoji_id]);
                        $er = $emoji_row->fetch();
                        echo $er['custom_emoji_id'] ?? $emoji['emoji_name'] ?? $emoji_id;
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
<script>
function selectEmoji(emojiId, emojiName) {
    document.getElementById('selected_emoji').value = emojiId;
    document.getElementById('selected_emoji_display').innerHTML = '<span style="font-size:24px">' + emojiName + '</span>';
    document.getElementById('emoji_selection').style.display = 'block';
}
</script>

<div id="emoji_selection" style="display:none; margin-top:20px; padding:10px; background:#e7f3ff; border:1px solid #007bff; border-radius:5px;">
    <strong>انتخاب شد:</strong> <span id="selected_emoji_display"></span>
    <input type="hidden" id="selected_emoji" name="emoji_id">
    <button type="button" onclick="document.getElementById('emoji_selection').style.display='none'">انصراف</button>
    <button type="button" onclick="document.getElementById('emoji_selection_form').submit()" class="btn btn-primary">تایید</button>
    <form id="emoji_selection_form" method="POST" style="display:inline">
        <input type="hidden" name="emoji_id" value="">
        <input type="hidden" name="target" value="<?= htmlspecialchars($target) ?>">
        <input type="hidden" name="target_type" value="<?= htmlspecialchars($target_type) ?>">
    </form>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>