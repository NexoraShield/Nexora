<?php
// The helpers of the shop pages (agent shop, agent panel shop, top up).

function nexora_agentshop_clean_style($v): string
{
    $v = trim((string) $v);
    return in_array($v, function_exists('nexora_color_slugs') ? nexora_color_slugs() : [], true) ? $v : '';
}
function nexora_agentshop_clean_emoji($v): string
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^\d{1,32}$/', $v)) return $v;
    if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $v, $m)) return $m[1];
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v) && function_exists('nexora_emoji_lookup')) {
        $row = nexora_emoji_lookup(trim($v));
        if ($row && !empty($row['custom_emoji_id'])) return (string) $row['custom_emoji_id'];
    }
    return '';
}
function nexora_agentshop_style_select($selected, string $name = 'btn_style'): string
{
    $h = '<select name="' . htmlspecialchars($name) . '" class="select" style="min-width:90px">';
    foreach (nexora_color_palette() as $slug => $info) {
        $label = $info['label'] ?? $slug;
        if ($slug === '') $label = 'پیش‌فرض';
        $sel = ((string) $slug === (string) $selected) ? ' selected' : '';
        $h .= '<option value="' . htmlspecialchars($slug) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    return $h . '</select>';
}
function nexora_agentshop_preview($name, $style, $emoji, $colors): string
{
    $bg = $colors[$style] ?? 'var(--surface-3)';
    $fg = ($style !== '' && isset($colors[$style])) ? '#fff' : 'inherit';
    $icon = function_exists('nexora_emoji_preview_html') ? nexora_emoji_preview_html($emoji) : '';
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border-radius:8px;padding:4px 12px;font-size:.8rem">' . $icon . htmlspecialchars((string) $name) . '</span>';
}
