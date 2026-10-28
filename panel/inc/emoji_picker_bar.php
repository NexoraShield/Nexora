<?php
if (empty($msEmojiRows) || !is_array($msEmojiRows)) return;
?>
<style>.ms-emoji-bar{border:1px solid rgba(128,128,128,.35);border-radius:10px;padding:10px 12px;margin-bottom:14px}.ms-emoji-bar-title{font-weight:700;font-size:.85rem;margin-bottom:8px}.ms-emoji-chips{display:flex;flex-wrap:wrap;gap:6px;max-height:150px;overflow-y:auto}.ms-emoji-chip{border:1px solid rgba(128,128,128,.4);border-radius:8px;padding:4px 10px;font-size:.78rem;cursor:pointer;background:transparent;color:inherit}.ms-emoji-chip:hover{border-color:#3390ec}</style>
<div class="ms-emoji-bar">
    <div class="ms-emoji-bar-title">ایموجی Premium — کلیک کنید بعد روی فیلد</div>
    <div class="ms-emoji-chips">
        <?php foreach ($msEmojiRows as $chip):
            if (empty($chip['placeholder'])) continue; ?>
            <button type="button" class="ms-emoji-chip" data-ms-emoji="<?= htmlspecialchars($chip['placeholder']) ?>"
                title="<?= htmlspecialchars($chip['placeholder']) ?>"><?= htmlspecialchars($chip['label'] !== '' ? $chip['label'] : $chip['placeholder']) ?></button>
        <?php endforeach; ?>
    </div>
</div>
