window.openEditModal = function (p) {
    document.getElementById('edit_id').value = p.id || '';
    document.getElementById('edit_name').value = p.name_product || '';
    document.getElementById('edit_price').value = p.price_product || '';
    document.getElementById('edit_volume').value = p.Volume_constraint || '';
    document.getElementById('edit_time').value = p.Service_time || '';
    document.getElementById('edit_cat').value = p.category || '';
    document.getElementById('edit_agent').value = p.agent || '';
    document.getElementById('edit_note').value = p.note || '';
    document.getElementById('edit_position').value = p.position || '';

    var sel = document.getElementById('edit_panel');
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            sel.options[i].selected = sel.options[i].value === (p.Location || '');
        }
    }

    // per product inbound checkboxes
    var inbWrap = document.getElementById('edit_inbounds');
    if (inbWrap) {
        var selectedInbounds = [];
        try {
            var parsedInbounds = JSON.parse(p.inbounds || '[]');
            if (Array.isArray(parsedInbounds)) {
                selectedInbounds = parsedInbounds.map(function (v) { return String(v); });
            }
        } catch (e) { }
        var inbBoxes = inbWrap.querySelectorAll('input[name="inbounds_product[]"]');
        for (var j = 0; j < inbBoxes.length; j++) {
            inbBoxes[j].checked = selectedInbounds.indexOf(inbBoxes[j].value) !== -1;
        }
    }

    openModal('editModal');
};
