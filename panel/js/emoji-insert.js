(function () {
    function maxAllowed(field) {
        var m = field.getAttribute('data-ms-max');
        if (m === null || m === '') return 1;
        var n = parseInt(m, 10);
        return isNaN(n) ? 1 : n;
    }
    function countPlaceholders(text) {
        var m = (text || '').match(/\{emoji:[^}]+\}/g);
        return m ? m.length : 0;
    }
    document.addEventListener('click', function (e) {
        var chip = e.target && e.target.closest ? e.target.closest('[data-ms-emoji]') : null;
        if (!chip) return;
        var code = chip.getAttribute('data-ms-emoji') || '';
        if (code === '') return;
        var field = document.querySelector('.ms-emoji-field:focus') || document.querySelector('.ms-emoji-field');
        if (!field) return;
        if (countPlaceholders(field.value) >= maxAllowed(field)) return;
        var s = (field.selectionStart == null) ? field.value.length : field.selectionStart;
        var en = (field.selectionEnd == null) ? field.value.length : field.selectionEnd;
        field.value = field.value.slice(0, s) + code + field.value.slice(en);
        var pos = s + code.length;
        try { field.setSelectionRange(pos, pos); } catch (_) {}
        field.focus();
    });
})();
