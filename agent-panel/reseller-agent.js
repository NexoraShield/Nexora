/* Nexora reseller panel app. Token in localStorage. */
(function () {
  'use strict';
  var TK = 'nexora_rs_token';
  var $ = function (id) { return document.getElementById(id); };
  function token(v) { try { if (v === undefined) return localStorage.getItem(TK) || ''; if (v) localStorage.setItem(TK, v); else localStorage.removeItem(TK); } catch (e) { return ''; } }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>'"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]; }); }
  function faNum(n) { try { return new Intl.NumberFormat('fa-IR').format(n); } catch (e) { return String(n); } }
  function money(n) { return faNum(n) + ' تومان'; }
  function fdate(iso) { try { var d = new Date(iso); if (isNaN(d)) return '—'; return d.toLocaleDateString('fa-IR') + ' ' + d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }); } catch (e) { return '—'; } }
  function fday(iso) { try { var d = new Date(iso + 'T12:00:00Z'); if (isNaN(d)) return ''; return new Intl.NumberFormat('fa-IR').format(d.getUTCDate()); } catch (e) { return ''; } }
  async function post(endpoint, body) {
    body = body || {};
    body.token = token();
    if (endpoint.charAt(0) !== '/' && endpoint.indexOf('://') < 0) endpoint = '/' + endpoint.replace(/^\//, '');
    var r = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    var j = {};
    try { j = await r.json(); } catch (e) {}
    return j;
  }
  var ME = null, PRICES = null, CREDIT = 0;

  /* ---------- theme ---------- */
  var THEMES = [
    { id: 'teal', name: 'فیروزه روز', sub: 'فیروزه‌ای · روشن', css: 'linear-gradient(135deg,#0e9488,#5fd4c8)' },
    { id: 'blue', name: 'دریا آبی', sub: 'آبی · پیش‌فرض', css: 'linear-gradient(135deg,#1d7ff2,#6db3ff)' },
    { id: 'purple', name: 'بنفش رویا', sub: 'بنفش · مدرن', css: 'linear-gradient(135deg,#7c3aed,#b79cff)' },
    { id: 'green', name: 'زمرد سبز', sub: 'سبز · آرام', css: 'linear-gradient(135deg,#16a34a,#7ddfa4)' },
    { id: 'orange', name: 'غروب گرم', sub: 'کهربایی · گرم', css: 'linear-gradient(135deg,#ea7c28,#f5bd7d)' }
  ];
  function applyTheme(id, dark) {
    document.body.dataset.theme = id || 'teal';
    if (dark) document.body.dataset.dark = '1'; else document.body.removeAttribute('data-dark');
    try {
      var t = JSON.parse(localStorage.getItem('nexora_rs_theme') || '{}');
      t.id = id; t.dark = !!dark;
      localStorage.setItem('nexora_rs_theme', JSON.stringify(t));
    } catch (e) {}
  }
  (function initTheme() {
    try {
      var t = JSON.parse(localStorage.getItem('nexora_rs_theme') || '{}');
      if (t.id) applyTheme(t.id, t.dark);
    } catch (e) {}
  })();

  /* ---------- auth ---------- */
  async function doLogin() {
    var u = ($('rl-user').value || '').trim(), p = $('rl-pass').value || '', err = $('rl-err');
    err.textContent = '';
    if (!u || !p) { err.textContent = 'نام کاربری و رمز را وارد کن.'; return; }
    $('rl-go').disabled = true;
    try {
      var r = await post('api/reseller-auth.php', { action: 'login', username: u, password: p });
      if (r && r.ok) { token(r.token); ME = r.reseller; enter(); }
      else err.textContent = (r && r.message) || 'ورود ناموفق بود.';
    } catch (e) { err.textContent = 'ارتباط برقرار نشد.'; }
    $('rl-go').disabled = false;
  }
  $('rl-go').addEventListener('click', doLogin);
  $('rl-pass').addEventListener('keydown', function (e) { if (e.key === 'Enter') doLogin(); });
  $('rs-logout').addEventListener('click', async function () {
    try { await post('api/reseller-auth.php', { action: 'logout' }); } catch (e) {}
    token(''); location.reload();
  });

  function enter() {
    $('rs-login').hidden = true;
    $('rs-app').hidden = false;
    if (ME) {
      $('rs-name').textContent = ME.username || 'نماینده';
      try{ var tb=document.querySelector('.rs-brand small'); if(tb){ var tierTxt=(ME.tier==='vip'?' ویژه — ۳۰٪':' معمولی — ۲۵٪'); tb.textContent='پنل نمایندگی' + tierTxt; if(ME.tier==='vip'){ tb.style.color='#f5c86e'; tb.style.fontWeight='800'; } } }catch(e){}
      applyTheme(ME.theme, ME.dark);
      buildThemes();
      fillInfo();
    }
    show('dash');
    loadDash(14);
  }

  /* ---------- nav ---------- */
  function show(name) {
    document.querySelectorAll('.rs-nav[data-view]').forEach(function (b) { b.classList.toggle('active', b.dataset.view === name); });
    ['dash', 'users', 'ops', 'api', 'settings', 'reports', 'shop'].forEach(function (n) { var el=$('v-' + n); if(el) el.hidden = n !== name; });
    if (name === 'users') loadUsers();
    if (name === 'ops') loadOps();
    if (name === 'api') fillApi();
    if (name === 'reports') loadReports(curRDays);
    if (name === 'shop') loadShop();
  }
  document.querySelectorAll('.rs-nav[data-view]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.view); }); });

  /* ---------- dashboard ---------- */
  var curDays = 14;
  document.querySelectorAll('.chip[data-days]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.chip[data-days]').forEach(function (x) { x.classList.toggle('active', x === b); });
      curDays = parseInt(b.dataset.days, 10) || 14;
      loadDash(curDays);
    });
  });
  $('d-refresh').addEventListener('click', function () { loadDash(curDays); });
  async function loadDash(days) {
    try {
      var r = await post('api/reseller-api.php', { action: 'dashboard', days: days });
      if (!r || !r.ok) throw 0;
      CREDIT = r.credit;
      $('d-credit').textContent = money(r.credit);
      $('d-users').textContent = faNum(r.users) + ' کاربر';
      $('d-vol').textContent = faNum(r.volume_gb) + ' گیگابایت';
      var tot = 0;
      (r.chart || []).forEach(function (c) { tot += c.count; });
      $('d-chart-cap').textContent = 'مجموع ' + faNum(tot) + ' عملیات در ' + faNum(days) + ' روز';
      var box = $('d-chart');
      box.innerHTML = '';
      (r.chart || []).forEach(function (c, i) {
        var pct = r.max > 0 ? Math.round((c.count / r.max) * 100) : 0;
        var d = document.createElement('div');
        d.className = 'bar' + (c.count === 0 ? ' zero' : '');
        d.innerHTML = '<i style="height:' + Math.max(4, pct) + '%" title="' + faNum(c.count) + '"></i><span>' + (((r.chart.length > 20 && i % 2) || (r.chart.length <= 20 && i % 2 === 0 && r.chart.length > 10)) ? '' : fday(c.day)) + '</span>';
        box.appendChild(d);
      });
    } catch (e) {}
  }

  /* ---------- users ---------- */
  var uPage = 1, uPer = 10;
  $('u-new').addEventListener('click', function () { openModal(); });
  $('u-close').addEventListener('click', function () { $('u-modal').hidden = true; });
  $('u-modal').addEventListener('click', function (e) { if (e.target === $('u-modal')) $('u-modal').hidden = true; });
  $('u-refresh').addEventListener('click', function () { loadUsers(); });
  ['u-q', 'u-status', 'u-sort'].forEach(function (id) {
    $(id).addEventListener('change', function () { uPage = 1; loadUsers(); });
  });
  $('u-q').addEventListener('input', function () { clearTimeout(window.__uq); window.__uq = setTimeout(function () { uPage = 1; loadUsers(); }, 400); });

  function stPill(st) {
    if (st === 'active') return '<span class="pill p-green">فعال</span>';
    if (st === 'disabled') return '<span class="pill p-gray">غیرفعال</span>';
    return '<span class="pill p-orange">منقضی</span>';
  }
  function expText(u) {
    if (!u.expires_at) return '';
    var ms = new Date(u.expires_at) - Date.now();
    if (ms <= 0) return 'منقضی شده';
    var d = Math.ceil(ms / 86400000);
    return 'پایان در ' + faNum(d) + ' روز';
  }
  async function loadUsers() {
    var tb = $('u-list');
    tb.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted)">در حال بارگذاری…</td></tr>';
    try {
      var r = await post('api/reseller-api.php', { action: 'users', q: $('u-q').value.trim(), status: $('u-status').value, sort: $('u-sort').value, page: uPage, per: uPer });
      if (!r || !r.ok) throw 0;
      CREDIT = r.credit;
      tb.innerHTML = '';
      if (!r.users.length) tb.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted)">کاربری نیست. با «ساخت کاربر» شروع کن.</td></tr>';
      r.users.forEach(function (u) {
        var pct = (u.volume_gb && u.volume_gb > 0) ? Math.min(100, Math.round((u.used_gb / u.volume_gb) * 100)) : 0;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><span class="dot ' + (u.status === 'active' ? 'on' : 'off') + '"></span><b class="mono">' + esc(u.username) + '</b><br><small style="color:var(--muted)">📍 ' + esc(u.location || '') + ' · 📅 ' + esc(fdate(u.created_at)) + '</small></td>' +
          '<td>' + stPill(u.status) + '<br><small style="color:var(--muted)">' + esc(expText(u)) + '</small></td>' +
          '<td><small style="color:var(--muted)">٪' + faNum(pct) + ' مصرف</small><div class="ubar' + (pct >= 90 ? ' hot' : '') + '"><i style="width:' + pct + '%"></i></div><small style="color:var(--muted)">' + esc(faNum(u.used_gb) + ' از ' + (u.volume_gb ? faNum(u.volume_gb) + ' گیگ' : (u.volume || ''))) + '</small></td>' +
          '<td style="white-space:nowrap"><button class="rowbtn" data-act="copy" data-id="' + esc(u.id) + '">🔗</button> <button class="rowbtn" data-act="menu" data-id="' + esc(u.id) + '">⋯</button></td>';
        tb.appendChild(tr);
      });
      tb.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function (ev) { rowAction(b.dataset.act, b.dataset.id, ev); });
      });
      var pages = Math.max(1, Math.ceil(r.total / r.per));
      $('u-pager').innerHTML = '';
      (function (pg) {
        var info = document.createElement('span');
        info.textContent = 'نمایش ' + faNum(r.total === 0 ? 0 : ((pg - 1) * r.per + 1)) + ' تا ' + faNum(Math.min(r.total, pg * r.per)) + ' از ' + faNum(r.total);
        var prev = document.createElement('button'); prev.className = 'rowbtn'; prev.textContent = '→ قبلی'; prev.disabled = pg <= 1;
        prev.addEventListener('click', function () { if (uPage > 1) { uPage--; loadUsers(); } });
        var next = document.createElement('button'); next.className = 'rowbtn'; next.textContent = 'بعدی ←'; next.disabled = pg >= pages;
        next.addEventListener('click', function () { if (uPage < pages) { uPage++; loadUsers(); } });
        var sel = document.createElement('select'); sel.className = 'rowbtn';
        [5, 10, 20].forEach(function (n) { var o = document.createElement('option'); o.value = n; o.textContent = 'هر صفحه ' + faNum(n) + ' مورد'; if (n === uPer) o.selected = true; sel.appendChild(o); });
        sel.addEventListener('change', function () { uPer = parseInt(sel.value, 10); uPage = 1; loadUsers(); });
        $('u-pager').appendChild(prev); $('u-pager').appendChild(info); $('u-pager').appendChild(next); $('u-pager').appendChild(sel);
      })(r.page);
    } catch (e) {}
  }

  var menuEl = null;
  function closeMenu() { if (menuEl) { menuEl.remove(); menuEl = null; } }
  document.addEventListener('click', function () { closeMenu(); });
  async function rowAction(act, id, ev) {
    ev.stopPropagation();
    if (act === 'copy') {
      try {
        var r = await post('api/reseller-api.php', { action: 'users', q: '', status: '', sort: 'new', page: 1, per: 100 });
        var u = null;
        (r.users || []).forEach(function (x) { if (x.id === id) u = x; });
        if (u) {
          var txt = 'نام کاربری: ' + u.username + '\nلوکیشن: ' + u.location + '\nحجم: ' + u.volume + '\nمدت: ' + u.period + '\nانقضا: ' + fdate(u.expires_at);
          try { await navigator.clipboard.writeText(txt); } catch (e) {
            var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (e2) {}
            ta.remove();
          }
        }
      } catch (e) {}
      return;
    }
    closeMenu();
    var b = ev.currentTarget;
    var rect = b.getBoundingClientRect();
    menuEl = document.createElement('div');
    menuEl.className = 'menu-pop';
    menuEl.innerHTML = '<button data-m="copy">🔗 کپی مشخصات</button><button data-m="renew">🔄 تمدید</button><button data-m="toggle">⏸ فعال / غیرفعال</button><button data-m="del" class="del">🗑 حذف کاربر</button>';
    menuEl.style.top = Math.min(window.innerHeight - 200, rect.bottom + 6) + 'px';
    menuEl.style.left = Math.max(8, rect.left - 160) + 'px';
    document.body.appendChild(menuEl);
    menuEl.querySelectorAll('button').forEach(function (mb) {
      mb.addEventListener('click', async function (e2) {
        e2.stopPropagation();
        var m = mb.dataset.m;
        closeMenu();
        if (m === 'copy') { rowAction('copy', id, ev); return; }
        if (m === 'del' && !confirm('این کاربر حذف شود؟')) return;
        if (m === 'renew' && !confirm('تمدید شود؟ هزینه مجدد کسر می‌شود.')) return;
        try {
          var rr = await post('api/reseller-api.php', { action: m === 'del' ? 'delete' : m, id: id });
          if (rr && rr.ok) loadUsers();
          else alert((rr && rr.message) || 'ناموفق بود.');
        } catch (e) { alert('ناموفق بود.'); }
      });
    });
  }

  /* ---------- create modal ---------- */
  var PKGS = [], LOCS = [], curLoc = '', vols = [], durs = [], vi = 0, di = 0;
  var PLAN_RATES = {}; // per-plan rate_gb, rate_day
  var LIMITS = { rate_gb: 0, rate_day: 0, min_days: 1, max_days: 90, min_gb: 1, max_gb: 500 };
  async function ensurePrices() {
    var r = await post('api/reseller-api.php', { action: 'prices' });
    if (!r || !r.ok) throw 0;
    CREDIT = r.credit;
    if (r.limits) LIMITS = r.limits;
    if (r.plan_rates) PLAN_RATES = r.plan_rates;
    PKGS = [];
    LOCS = Object.keys(r.prices || {});
    // NOTE: no filter here — shop shows all plans (incl. unlimited);
    // the manual create-user modal filters via MANUAL_LOCS below.
    LOCS.forEach(function (k) {
      var pl = r.prices[k];
      Object.keys(pl.durations || {}).forEach(function (dk) {
        var d = pl.durations[dk];
        (d.packages || []).forEach(function (p) {
          PKGS.push({ loc: k, title: pl.title, volume: p.volume, days: p.days, label: d.label, price: p.price, site: p.site || Math.round(p.price / 0.75), gb: parseGb(p.volume) });
        });
      });
    });
    if (!LOCS.length) throw 0;
    return true;
  }
  // manual creation: unlimited must NOT appear here (shop only)
  var MANUAL_LOCS = ['gaming', 'economy', 'wireguard'];
  function manualLocs() { return LOCS.filter(function (k) { return MANUAL_LOCS.indexOf(k) >= 0; }); }
  async function openModal() {
    $('u-modal').hidden = false;
    $('nu-err').textContent = '';
    try {
      await ensurePrices();
      var ml = manualLocs();
      curLoc = ml.length ? ml[0] : '';
      paintLocSelect();
      paintCustom();
    } catch (e) { $('nu-err').textContent = 'دریافت لیست قیمت ناموفق بود.'; }
  }
  function locTitle(k) {
    var t = '';
    PKGS.forEach(function (p) { if (p.loc === k && !t) t = p.title; });
    return t || k;
  }
  function paintLocSelect() {
    var sel = $('cu-loc');
    sel.innerHTML = '';
    var ml = manualLocs();
    ml.forEach(function (k) {
      var o = document.createElement('option');
      o.value = k; o.textContent = locTitle(k);
      sel.appendChild(o);
    });
    if (curLoc && ml.indexOf(curLoc) >= 0) sel.value = curLoc;
    else if (ml.length) { curLoc = ml[0]; sel.value = curLoc; }
  }
  function getRateGb(loc) { return (PLAN_RATES[loc] && PLAN_RATES[loc].rate_gb) ? PLAN_RATES[loc].rate_gb : (LIMITS.rate_gb || 0); }
  function getRateDay(loc) { return (PLAN_RATES[loc] && PLAN_RATES[loc].rate_day) ? PLAN_RATES[loc].rate_day : (LIMITS.rate_day || 0); }
  function calcPrice(gb, days, loc) { return Math.round(gb * getRateGb(loc) + days * getRateDay(loc)); }
  function paintCustom() {
    if (!curLoc) { $('nu-price').textContent = '—'; return; }
    var gb = parseFloat($('cu-gb').value) || 0;
    var days = parseInt($('cu-days').value, 10) || 0;
    var rateGb = getRateGb(curLoc);
    var rateDay = getRateDay(curLoc);
    var minGb = LIMITS.min_gb || 0.5;
    var maxGb = LIMITS.max_gb || 500;
    var minDays = LIMITS.min_days || 1;
    var maxDays = LIMITS.max_days || 90;
    if (gb < minGb) gb = minGb;
    if (gb > maxGb) gb = maxGb;
    if (days < minDays) days = minDays;
    if (days > maxDays) days = maxDays;
    $('cu-gb').value = gb;
    $('cu-days').value = days;
    var price = calcPrice(gb, days, curLoc);
    $('cu-hint').textContent = 'نرخ این لوکیشن: گیگ×' + faNum(rateGb) + ' + روز×' + faNum(rateDay) + ' · محدوده: ' + faNum(minGb) + '–' + faNum(maxGb) + ' گیگ، ' + faNum(minDays) + '–' + faNum(maxDays) + ' روز';
    $('nu-price').textContent = money(price);
    $('nu-submit').disabled = price <= 0;
  }
  $('cu-loc').addEventListener('change', function () { curLoc = $('cu-loc').value; paintCustom(); });
  $('cu-gb').addEventListener('input', function () { paintCustom(); });
  $('cu-days').addEventListener('input', function () { paintCustom(); });
  /* ---------- random username ---------- */
  function rndName() {
    var abc = 'abcdefghjkmnpqrstuvwxyz23456789', o = 'user_';
    for (var i = 0; i < 6; i++) o += abc[Math.floor(Math.random() * abc.length)];
    return o;
  }
  $('nu-random').addEventListener('click', function () { $('nu-name').value = rndName(); });
  var ACCENTS = {
    gaming: { c: '#b566ff', rgb: '181,102,255', c2: '#7d40ff', e: '🎮' },
    economy: { c: '#43c8ff', rgb: '67,200,255', c2: '#147fff', e: '👛' },
    unlimited: { c: '#28dda7', rgb: '40,221,167', c2: '#0a8f66', e: '♾️' },
    wireguard: { c: '#ff5d5d', rgb: '255,93,93', c2: '#8f1018', e: '🛡️' }
  };
  function accentFor(k, title) {
    if (ACCENTS[k]) return ACCENTS[k];
    var t = title || '';
    if (t.indexOf('گیمینگ') >= 0) return ACCENTS.gaming;
    if (t.indexOf('اقتصاد') >= 0) return ACCENTS.economy;
    if (t.indexOf('نامحدود') >= 0) return ACCENTS.unlimited;
    if (t.indexOf('وایرگارد') >= 0) return ACCENTS.wireguard;
    return { c: '#0e9488', rgb: '14,148,136', c2: '#0a6e65', e: '📦' };
  }
  async function loadShop() {
    var box = $('shop-list');
    box.innerHTML = '<p style="color:var(--muted)">در حال بارگذاری محصولات…</p>';
    try {
      await ensurePrices();
      box.innerHTML = '';
      if (!PKGS.length) { box.innerHTML = '<p style="color:var(--muted)">محصولی تعریف نشده.</p>'; return; }
      try{ var sh=document.querySelector('#v-shop .rs-head p'); if(sh){ var offH = (PKGS[0] && PKGS[0].site) ? Math.round((1- PKGS[0].price/PKGS[0].site)*100): (ME && ME.tier==='vip'?30:25); sh.textContent='محصولات سایت با ٪'+faNum(offH)+' تخفیف نماینده'+(ME && ME.tier==='vip'?' ویژه':'')+'؛ سفارش بده، لینک اتصال و QR تحویل بگیر'; } }catch(e){}
      LOCS.forEach(function (k) {
        var mine = PKGS.filter(function (p) { return p.loc === k; });
        if (!mine.length) return;
        var ac = accentFor(k, locTitle(k));
        // best value = lowest price per GB in this location
        var bestIdx = -1, bestRate = Infinity;
        mine.forEach(function (p, i) {
          var gb = parseGb(p.volume);
          if (gb > 0 && p.price / gb < bestRate) { bestRate = p.price / gb; bestIdx = i; }
        });
        var off0 = (PKGS.length && PKGS[0] && PKGS[0].site) ? Math.round((1 - PKGS[0].price / PKGS[0].site)*100) : (ME && ME.tier==='vip'?30:25);
        var h = '<div class="card shop-loc"><h2>📍 ' + esc(locTitle(k)) + '</h2><p class="sub">٪' + faNum(off0) + ' زیر قیمت سایت' + (ME && ME.tier==='vip' ? ' · ویژه':'') + '</p>';
        var durs = [];
        mine.forEach(function (p) { if (!durs.some(function (x) { return x.days === p.days; })) durs.push({ days: p.days, label: p.label }); });
        durs.sort(function (a, b) { return a.days - b.days; });
        h += '<div class="shop-cols">';
        durs.forEach(function (d) {
          var inMonth = mine.filter(function (p) { return p.days === d.days; });
          h += '<div class="shop-col"><div class="shop-month">🗓 ' + esc(d.label) + ' <span class="cnt">(' + faNum(inMonth.length) + ')</span></div><div class="shop-grid">';
          mine.forEach(function (p, i) {
            if (p.days !== d.days) return;
            var siteP = p.site || Math.round(p.price / (ME && ME.tier==='vip' ? 0.70 : 0.75));
            var offP = siteP > p.price ? Math.round((1 - p.price / siteP) * 100) : 0;
            h += '<div class="shop-card" style="--lc:' + ac.c + ';--lcrgb:' + ac.rgb + ';--lc2:' + ac.c2 + '">' +
              (i === bestIdx ? '<span class="best-ribbon">✨ بهترین ارزش</span>' : '') +
              '<div class="shop-top"><span class="shop-emoji">' + ac.e + '</span>' + (offP > 0 ? '<span class="off">٪' + faNum(offP) + '-</span>' : '') + '</div><b>' + esc(p.volume) + '</b><div class="vol">' + esc(p.label) + '</div>' +
              '<div class="pr"><s>' + money(siteP) + '</s><b>' + money(p.price) + '</b></div>' +
              '<button class="rs-btn sm" data-shop="' + esc(k) + '|' + i + '" type="button">🛒 سفارش</button></div>';
          });
          h += '</div></div>';
        });
        box.innerHTML += h + '</div></div>';
      });
      box.querySelectorAll('[data-shop]').forEach(function (b) {
        b.addEventListener('click', function () {
          var parts = b.dataset.shop.split('|');
          prefillOrder(parts[0], parseInt(parts[1], 10));
        });
      });
    } catch (e) { box.innerHTML = '<p style="color:var(--muted)">دریافت محصولات ناموفق بود.</p>'; }
  }
  function prefillOrder(loc, idx) {
    var mine = PKGS.filter(function (p) { return p.loc === loc; });
    var p = mine[idx];
    if (!p) return;
    curLoc = loc;
    paintLocSelect();
    var gb = parseGb(p.volume);
    var days = p.days;
    $('cu-gb').value = gb;
    $('cu-days').value = days;
    paintCustom();
    $('u-modal').hidden = false;
    $('nu-err').textContent = '';
    setTimeout(function () { try { $('nu-name').focus(); } catch (e) {} }, 60);
  }
  var sRef = $('s-refresh');
  if (sRef) sRef.addEventListener('click', loadShop);
  /* ---------- credential result with QR ---------- */
  var lastCreds = '';
  function showResult(d) {
    var title = locTitle(curLoc);
    var m = d.match || {};
    var exp = d.expires_at ? fdate(d.expires_at) : '';
    lastCreds = 'NEXORA | ' + title + '\nنام کاربری: ' + d.username + '\nحجم: ' + (m.volume || '') + ' | ' + (m.label || '') + '\nانقضا: ' + exp;
    $('r-lines').innerHTML =
      '<div class="row"><span>👤 نام کاربری</span><b>' + esc(d.username) + '</b></div>' +
      '<div class="row"><span>📍 لوکیشن</span><b>' + esc(title) + '</b></div>' +
      '<div class="row"><span>📦 بسته</span><b>' + esc((m.volume || '') + ' · ' + (m.label || '')) + '</b></div>' +
      '<div class="row"><span>📅 انقضا</span><b>' + esc(exp) + '</b></div>' +
      '<div class="row"><span>💰 قیمت</span><b>' + esc(money(d.price || 0)) + '</b></div>';
    var qr = $('r-qr');
    qr.alt = 'QR اتصال';
    qr.onerror = function () { qr.removeAttribute('src'); qr.parentElement.innerHTML = '<small>QR در دسترس نیست؛ مشخصات را کپی کن</small>'; };
    try { qr.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(lastCreds); } catch (e) {}
    $('r-err').textContent = '';
    $('r-modal').hidden = false;
  }
  $('r-close').addEventListener('click', function () { $('r-modal').hidden = true; });
  $('r-users').addEventListener('click', function () { $('r-modal').hidden = true; show('users'); });
  $('r-copy').addEventListener('click', async function () {
    try { await navigator.clipboard.writeText(lastCreds); } catch (e) {
      var ta = document.createElement('textarea'); ta.value = lastCreds; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch (e2) {}
      ta.remove();
    }
  });
  function parseGb(v) {
    var m = String(v || '').match(/([\d.,]+)\s*(گیگ|gb|gig)/i);
    if (m) return parseFloat(m[1].replace(',', '.'));
    m = String(v || '').match(/([\d.,]+)\s*(مگ|mb)/i);
    if (m) return parseFloat(m[1].replace(',', '.')) / 1024;
    m = String(v || '').match(/([\d.,]+)\s*(ترا|tb)/i);
    if (m) return parseFloat(m[1].replace(',', '.')) * 1024;
    return 0;
  }
  /* ---------- random username ---------- */
  function rndName() {
    var abc = 'abcdefghjkmnpqrstuvwxyz23456789', o = 'user_';
    for (var i = 0; i < 6; i++) o += abc[Math.floor(Math.random() * abc.length)];
    return o;
  }
  $('nu-random').addEventListener('click', function () { $('nu-name').value = rndName(); });
  $('nu-submit').addEventListener('click', async function () {
    var err = $('nu-err');
    err.textContent = '';
    var name = ($('nu-name').value || '').trim();
    if (!/^[A-Za-z0-9_.]{3,32}$/.test(name)) { err.textContent = 'نام کاربری انگلیسی (۳ تا ۳۲ حرف).'; return; }
    if (!curLoc) { err.textContent = 'لوکیشن را انتخاب کن.'; return; }
    var gb = parseFloat($('cu-gb').value) || 0;
    var days = parseInt($('cu-days').value, 10) || 0;
    var minGb = LIMITS.min_gb || 0.5;
    var maxGb = LIMITS.max_gb || 500;
    var minDays = LIMITS.min_days || 1;
    var maxDays = LIMITS.max_days || 90;
    if (gb < minGb || gb > maxGb) { err.textContent = 'حجم باید بین ' + faNum(minGb) + ' تا ' + faNum(maxGb) + ' گیگ باشد.'; return; }
    if (days < minDays || days > maxDays) { err.textContent = 'مدت باید بین ' + faNum(minDays) + ' تا ' + faNum(maxDays) + ' روز باشد.'; return; }
    var price = calcPrice(gb, days, curLoc);
    if (price <= 0) { err.textContent = 'نرخ این لوکیشن تنظیم نشده؛ با مدیریت تماس بگیر.'; return; }
    if (CREDIT < price) { err.textContent = 'اعتبار کافی نیست؛ موجودی: ' + money(CREDIT); return; }
    var title = locTitle(curLoc);
    var volumeStr = (gb % 1 === 0 ? gb : gb.toFixed(1).replace('.0','')) + ' گیگ';
    var periodStr = days + ' روزه';
    $('nu-submit').disabled = true;
    try {
      var payload = { action: 'create', username: name, location: title, custom: 1, gb: gb, days: days, volume: volumeStr, period: periodStr, note: ($('nu-note').value || '').trim() };
      var r = await post('api/reseller-api.php', payload);
      if (r && r.ok) {
        CREDIT = r.credit;
        var createdName = ($('nu-name').value || '').trim();
        var createdExp = (r.user && r.user.expires_at) || '';
        var createdPrice = (r.user && r.user.price) || price;
        $('u-modal').hidden = true;
        $('nu-name').value = ''; $('cu-gb').value = ''; $('cu-days').value = ''; $('nu-note').value = '';
        uPage = 1; loadUsers(); loadDash(curDays);
        showResult({ username: createdName, price: createdPrice, expires_at: createdExp, match: { volume: volumeStr, label: periodStr } });
      } else err.textContent = (r && r.message) || 'ساخت ناموفق بود.';
    } catch (e) { err.textContent = 'ساخت ناموفق بود.'; }
    $('nu-submit').disabled = false;
  });

  /* ---------- ops ---------- */
  $('o-refresh').addEventListener('click', function () { loadOps(); });
  ['o-q', 'o-type'].forEach(function (id) { $(id).addEventListener('change', loadOps); });
  $('o-q').addEventListener('input', function () { clearTimeout(window.__oq); window.__oq = setTimeout(loadOps, 400); });
  var ACTS = { create: ['ایجاد کاربر', 'p-green'], renew: ['تمدید', 'p-green'], delete: ['حذف', 'p-red'], disable: ['غیرفعال‌سازی', 'p-orange'], enable: ['فعال‌سازی', 'p-green'], credit: ['شارژ اعتبار', 'p-green'] };
  async function loadOps() {
    try {
      var r = await post('api/reseller-api.php', { action: 'ops', q: $('o-q').value.trim(), type: $('o-type').value });
      if (!r || !r.ok) throw 0;
      CREDIT = r.credit;
      $('o-count').textContent = faNum(r.count);
      $('o-spent').textContent = money(r.spent);
      $('o-credit').textContent = money(r.credit);
      var tb = $('o-list');
      tb.innerHTML = '';
      if (!r.ops.length) tb.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted)">عملیاتی نیست.</td></tr>';
      r.ops.forEach(function (o) {
        var a = ACTS[o.action] || [o.action, 'p-gray'];
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><span class="pill ' + a[1] + '">' + esc(a[0]) + '</span></td>' +
          '<td><b class="mono">' + esc(o.user || '—') + '</b>' + (o.detail ? '<br><small style="color:var(--muted)">' + esc(o.detail) + '</small>' : '') + '</td>' +
          '<td>' + (o.amount ? money(o.amount) : '—') + '</td>' +
          '<td><small style="color:var(--muted)">قبل: ' + money(o.before) + '<br>بعد: ' + money(o.after) + '</small></td>' +
          '<td style="white-space:nowrap">' + esc(fdate(o.at)) + '</td>';
        tb.appendChild(tr);
      });
    } catch (e) {}
  }

  /* ---------- api docs (bot: PasarGuard) ---------- */
  function fillApi() {
    var tk = token();
    var at = $('api-token'); if(at) at.value = tk;
    var base = location.origin + '/';
    // fill bot URLs
    var uv = $('api-url-vira'); if(uv) uv.textContent = base + 'api/agent.php';
    var uv2 = $('api-url-vira2'); if(uv2) uv2.textContent = base + 'api/bot-agent.php';
    var d1=$('api-doc1'); if(d1) d1.textContent = "POST " + base + "api/agent.php   (Bot - Bearer)\nAuthorization: Bearer "+ (tk.slice(0,12)+'…') + "\nContent-Type: application/json\n\n" + JSON.stringify({ actions: 'user_create', username: 'user123', data_limit_gb: 30, expire_days: 30 }, null, 2) + "\n\n# همچنین تمپلت/دستی یکپارچه:\n" + JSON.stringify({ action: 'buy', panel: 'gaming', product_code: '۳۰ گیگ', custom_username: 'user123' }, null, 2) + "\n" + JSON.stringify({ action: 'buy_custom', panel: 'gaming', volume: 30, days: 30, custom_username: 'user123' }, null, 2);
    var d2=$('api-doc2'); if(d2) d2.textContent = "POST " + base + "api/agent.php\nAuthorization: Bearer <TOKEN>\n" + JSON.stringify({ action: 'services', q: '' }, null, 2) + "\n\nPOST " + base + "api/reseller-api.php (انسانی)\n" + JSON.stringify({ token: tk.slice(0,8)+'…', action: 'users' }, null, 2);
  }
  var ac=$('api-copy'); if(ac) ac.addEventListener('click', async function () { try { await navigator.clipboard.writeText($('api-token').value); } catch (e) {} });
  var acv=$('api-copy-vira'); if(acv) acv.addEventListener('click', async function(){ try{ await navigator.clipboard.writeText($('api-url-vira').textContent); }catch(e){} });
  var acv2=$('api-copy-vira2'); if(acv2) acv2.addEventListener('click', async function(){ try{ await navigator.clipboard.writeText($('api-url-vira2').textContent); }catch(e){} });

  /* ---------- settings ---------- */
  function buildThemes() {
    var g = $('th-grid');
    g.innerHTML = '';
    THEMES.forEach(function (t) {
      var d = document.createElement('button');
      d.type = 'button';
      d.className = 'sw' + ((ME && ME.theme === t.id) ? ' sel' : '');
      d.innerHTML = '<i style="background:' + t.css + '"></i><b>' + esc(t.name) + '</b><small>' + esc(t.sub) + '</small>';
      d.addEventListener('click', async function () {
        applyTheme(t.id, document.body.dataset.dark === '1');
        buildThemes();
        try { await post('api/reseller-api.php', { action: 'settings', theme: t.id, dark: document.body.dataset.dark === '1' }); } catch (e) {}
      });
      g.appendChild(d);
    });
    $('s-dark').checked = document.body.dataset.dark === '1';
  }
  $('s-dark').addEventListener('change', async function () {
    applyTheme(document.body.dataset.theme || 'teal', $('s-dark').checked);
    try { await post('api/reseller-api.php', { action: 'settings', theme: document.body.dataset.theme || 'teal', dark: $('s-dark').checked }); } catch (e) {}
  });
  $('s-save-pass').addEventListener('click', async function () {
    var m = $('s-msg');
    m.textContent = '';
    try {
      var r = await post('api/reseller-auth.php', { action: 'password', current: $('s-cur').value, new: $('s-new').value });
      if (r && r.ok) { $('s-cur').value = ''; $('s-new').value = ''; m.textContent = 'رمز عوض شد.'; m.style.color = 'var(--green)'; }
      else { m.textContent = (r && r.message) || 'ناموفق بود.'; m.style.color = 'var(--red)'; }
    } catch (e) { m.textContent = 'ناموفق بود.'; m.style.color = 'var(--red)'; }
  });
  function fillInfo() {
    if (!ME) return;
    var tierL = (ME.tier==='vip'?'ویژه (۳۰٪)':'معمولی (۲۵٪)');
    $('s-info').innerHTML = '<div class="kv"><span>نام کاربری</span><b class="mono">' + esc(ME.username) + '</b></div>' +
      '<div class="kv"><span>سطح</span><b style="color:' + (ME.tier==='vip' ? '#b8862f' : 'var(--acc)') + '">' + esc(tierL) + '</b></div>' +
      '<div class="kv"><span>ایمیل</span><b>' + esc(ME.email || '—') + '</b></div>' +
      '<div class="kv"><span>اعتبار</span><b>' + esc(money(ME.credit)) + '</b></div>' +
      '<div class="kv"><span>عضویت</span><b>' + esc(fdate(ME.created_at)) + '</b></div>';
  }

  /* ---------- reports ---------- */
  var curRDays = 30;
  var PAL = ['#2563eb', '#f59e0b', '#16a34a', '#a855f7', '#ef4444', '#06b6d4'];
  document.querySelectorAll('.chip[data-rdays]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.chip[data-rdays]').forEach(function (x) { x.classList.toggle('active', x === b); });
      curRDays = parseInt(b.dataset.rdays, 10) || 30;
      loadReports(curRDays);
    });
  });
  var rRef = $('r-refresh');
  if (rRef) rRef.addEventListener('click', function () { loadReports(curRDays); });
  function acc() { try { return getComputedStyle(document.body).getPropertyValue('--acc').trim() || '#0e9488'; } catch (e) { return '#0e9488'; } }
  function svgLine(el, series) {
    var W = 620, H = 230, P = 34;
    var vals = series.map(function (c) { return c.revenue; });
    var mx = Math.max.apply(null, vals.concat([1]));
    // 7-day moving average
    var ma = vals.map(function (_, i) {
      var s = 0, n = 0;
      for (var k = Math.max(0, i - 6); k <= i; k++) { s += vals[k]; n++; }
      return s / n;
    });
    function X(i) { return P + (i / Math.max(1, vals.length - 1)) * (W - P - 10); }
    function Y(v) { return H - P - (v / mx) * (H - P - 16); }
    var pts = vals.map(function (v, i) { return X(i).toFixed(1) + ',' + Y(v).toFixed(1); }).join(' ');
    var ptsM = ma.map(function (v, i) { return X(i).toFixed(1) + ',' + Y(v).toFixed(1); }).join(' ');
    var step = Math.ceil(vals.length / 8);
    var labels = '';
    vals.forEach(function (_, i) {
      if (i % step === 0 || i === vals.length - 1) labels += '<text x="' + X(i).toFixed(0) + '" y="' + (H - 8) + '" font-size="10" fill="var(--muted)" text-anchor="middle">' + esc(fday(series[i].day)) + '</text>';
    });
    el.innerHTML = '<svg class="chart" viewBox="0 0 ' + W + ' ' + H + '"><polygon points="' + P + ',' + (H - P) + ' ' + pts + ' ' + (W - 10) + ',' + (H - P) + '" fill="' + acc() + '22"/><polyline points="' + pts + '" fill="none" stroke="' + acc() + '" stroke-width="2.5" stroke-linejoin="round"/>' +
      '<polyline points="' + ptsM + '" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-dasharray="5 4"/>' + labels + '</svg>';
  }
  function svgDonut(el, parts, centerTop, centerSub) {
    var R = 54, C = 2 * Math.PI * R, tot = 0;
    parts.forEach(function (p) { tot += p.value; });
    if (!tot) { el.innerHTML = '<p style="color:var(--muted);font-size:12px">داده‌ای نیست.</p>'; return; }
    var off = 25, segs = '', leg = '';
    parts.forEach(function (p, i) {
      var frac = p.value / tot;
      segs += '<circle cx="70" cy="70" r="' + R + '" fill="none" stroke="' + PAL[i % PAL.length] + '" stroke-width="16" stroke-dasharray="' + (frac * C).toFixed(1) + ' ' + C.toFixed(1) + '" stroke-dashoffset="' + (-off).toFixed(1) + '" stroke-linecap="butt" transform="rotate(-90 70 70)"/>';
      off += frac * C;
      leg += '<span><i style="background:' + PAL[i % PAL.length] + '"></i>' + esc(p.label) + ' · ' + esc(p.sub || faNum(p.value)) + '</span>';
    });
    el.innerHTML = '<div class="donut-wrap"><svg width="150" height="150" viewBox="0 0 140 140">' + segs +
      '<text x="70" y="66" text-anchor="middle" font-size="19" font-weight="900" fill="var(--ink)">' + esc(centerTop) + '</text>' +
      '<text x="70" y="86" text-anchor="middle" font-size="11" fill="var(--muted)">' + esc(centerSub) + '</text></svg><div class="donut-legend">' + leg + '</div></div>';
  }
  function bars(el, items, colorFn) {
    var mx = 1;
    items.forEach(function (x) { mx = Math.max(mx, x.value); });
    var h = '';
    items.forEach(function (x) {
      h += '<div class="hbar-row"><span>' + esc(x.label) + '</span><div class="hbar"><i style="width:' + Math.round((x.value / mx) * 100) + '%;background:' + (colorFn ? colorFn(x) : acc()) + '"></i></div><b>' + esc(x.num) + '</b></div>';
    });
    el.innerHTML = h || '<p style="color:var(--muted);font-size:12px">داده‌ای نیست.</p>';
  }
  function growthBadge(g) {
    var up = g >= 0;
    return '<div class="gr ' + (up ? 'up' : 'down') + '">' + (up ? '▲' : '▼') + ' ' + faNum(Math.abs(g)) + '٪ نسبت به دوره قبل</div>';
  }
  async function loadReports(days) {
    try {
      var r = await post('api/reseller-api.php', { action: 'analytics', days: days });
      if (!r || !r.ok) throw 0;
      CREDIT = r.credit;
      var k = r.kpi;
      var kpis = [
        ['💰 درآمد کل', money(k.revenue), growthBadge(k.g_revenue)],
        ['🧾 تعداد سفارش‌ها', faNum(k.orders), growthBadge(k.g_orders)],
        ['👥 کاربران فعال', faNum(k.active), ''],
        ['✨ کاربر جدید (' + faNum(days) + ' روز)', faNum(k.new), ''],
        ['🎯 میانگین خرید', money(k.avg), ''],
        ['💳 درآمد بازه', money(k.range_revenue), '']
      ];
      $('r-kpis').innerHTML = kpis.map(function (x) { return '<div class="kpi"><small>' + esc(x[0]) + '</small><b>' + esc(x[1]) + '</b>' + x[2] + '</div>'; }).join('');
      svgLine($('r-trend'), r.series || []);
      // products table
      var tot = 0;
      (r.products || []).forEach(function (p) { tot += p.revenue; });
      $('r-prod-cap').textContent = faNum((r.products || []).length) + ' محصول پرفروش';
      var ph = '<table class="tbl"><thead><tr><th>#</th><th>محصول</th><th>تعداد</th><th>درآمد</th><th>سهم</th></tr></thead><tbody>';
      (r.products || []).forEach(function (p, i) {
        var sh = tot ? Math.round((p.revenue / tot) * 100) : 0;
        ph += '<tr><td>' + faNum(i + 1) + '</td><td><b>' + esc(p.name) + '</b></td><td>' + faNum(p.count) + '</td><td>' + money(p.revenue) + '</td><td style="min-width:110px"><div class="ubar"><i style="width:' + sh + '%"></i></div><small>٪' + faNum(sh) + '</small></td></tr>';
      });
      $('r-products').innerHTML = ph + '</tbody></table>';
      // donuts
      svgDonut($('r-donut-loc'), (r.locs || []).map(function (l) { return { label: l.name, value: l.revenue, sub: money(l.revenue) }; }), money(k.revenue), 'درآمد کل');
      svgDonut($('r-donut-status'), [
        { label: 'فعال', value: r.status.active, sub: faNum(r.status.active) },
        { label: 'غیرفعال', value: r.status.disabled, sub: faNum(r.status.disabled) },
        { label: 'منقضی', value: r.status.expired, sub: faNum(r.status.expired) }
      ], faNum(k.active + r.status.disabled + r.status.expired), 'کاربر');
      // peak box
      var top = (r.products || [])[0];
      $('r-peak').innerHTML = '<div class="peak-box">' +
        '<div class="kv"><span>🏆 پرفروش‌ترین محصول</span><b>' + esc(top ? top.name : '—') + '</b></div>' +
        '<div class="kv"><span>📅 پرفروش‌ترین روز</span><b>' + esc(r.peak.day.day ? fday(r.peak.day.day) + ' (' + faNum(r.peak.day.orders) + ' سفارش)' : '—') + '</b></div>' +
        '<div class="kv"><span>🕐 ساعت اوج خرید</span><b>' + esc(faNum(r.peak.hour) + ' (' + faNum(r.peak.hour_orders) + ' سفارش)') + '</b></div></div>';
      // weekday (Sat..Fri order)
      var WN = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
      var order = [6, 0, 1, 2, 3, 4, 5];
      var wmx = 1;
      order.forEach(function (w) { wmx = Math.max(wmx, r.week[w]); });
      var wh = '<div class="wkday">';
      order.forEach(function (w) {
        var pct = Math.round((r.week[w] / wmx) * 100);
        wh += '<div class="bar' + (r.week[w] === 0 ? ' zero' : '') + '"><i style="height:' + Math.max(4, pct) + '%"></i><span>' + WN[w] + '</span></div>';
      });
      $('r-week').innerHTML = wh + '</div>';
      // hours
      var hmx = 1;
      r.hours.forEach(function (n) { hmx = Math.max(hmx, n); });
      var hh = '<div class="wkday">';
      r.hours.forEach(function (n, i) {
        var pct = Math.round((n / hmx) * 100);
        hh += '<div class="bar' + (n === 0 ? ' zero' : '') + '"><i style="height:' + Math.max(3, pct) + '%;background:#ec4899"></i><span>' + (i % 2 === 0 ? faNum(i) : '') + '</span></div>';
      });
      $('r-hours').innerHTML = hh + '</div>';
      // growth
      var g = r.growth || [];
      var gmx = 1, tmx = 1;
      g.forEach(function (x) { gmx = Math.max(gmx, x.new); tmx = Math.max(tmx, x.total); });
      var step = Math.ceil(g.length / 10);
      var gh = '<svg class="chart" viewBox="0 0 620 220">';
      g.forEach(function (x, i) {
        var bx = 34 + (i / Math.max(1, g.length - 1)) * 576;
        var bh = (x.new / gmx) * 120;
        gh += '<rect x="' + (bx - 5).toFixed(1) + '" y="' + (190 - bh).toFixed(1) + '" width="10" height="' + bh.toFixed(1) + '" rx="3" fill="#93c5fd"/>';
      });
      var lp = g.map(function (x, i) {
        var bx = 34 + (i / Math.max(1, g.length - 1)) * 576;
        return bx.toFixed(1) + ',' + (190 - (x.total / tmx) * 150).toFixed(1);
      }).join(' ');
      gh += '<polyline points="' + lp + '" fill="none" stroke="' + acc() + '" stroke-width="2.5"/>';
      g.forEach(function (x, i) {
        if (i % step === 0 || i === g.length - 1) {
          var bx = 34 + (i / Math.max(1, g.length - 1)) * 576;
          gh += '<text x="' + bx.toFixed(0) + '" y="212" font-size="10" fill="var(--muted)" text-anchor="middle">' + esc(fday(x.day)) + '</text>';
        }
      });
      $('r-growth').innerHTML = gh + '</svg>';
    } catch (e) {}
  }

  /* ---------- PasarGuard-style language switcher (fa/en/zh/ru) ---------- */
  var PG_LOCALES = {
    fa: { pg_title: 'پنل نمایندگی مخصوص ربات | نکسورا شیلد', login_h: 'پنل نمایندگی مخصوص ربات', login_p: 'ورود مخصوص اتصال ربات‌های فروش — عین پاسارگارد، با بک‌گراند شیشه‌ای نکسورا', login_user: 'نام کاربری', login_pass: 'رمز عبور', login_btn: 'ورود به پنل ربات', brand_sub: 'پنل ربات — 🤖 PasarGuard', nav_dash: 'داشبورد', nav_shop: 'محصولات آماده', nav_users: 'مدیریت کاربران', nav_ops: 'لاگ عملیات', nav_api: 'اتصال ربات', nav_reports: 'گزارش‌ها', nav_settings: 'تنظیمات', logout: 'خروج', dash_h: 'داشبورد ربات', dash_p: 'خلاصه وضعیت — مشترک با پنل دستی (همان موجودی)', refresh: '🔄 به‌روزرسانی', st_credit: 'موجودی کیف پول', st_credit_cap: 'از هر دو پنل (تمپلت/دستی/ربات) مشترک کسر می‌شود', st_users: 'کاربران', st_users_cap: 'بدون سقف کاربر', st_vol: 'حجم ساخته‌شده', st_vol_cap: 'مجموع سرویس‌ها', chart_h: '📈 نمودار فعالیت', chip7: '۷ روز', chip14: '۱۴ روز', chip30: '۳۰ روز', chip60: '۶۰ روز', shop_h: '📦 محصولات آماده', shop_p: 'بسته‌های آماده با تخفیف نماینده — سفارش بده، لینک اتصال و QR تحویل بگیر', users_h: 'مدیریت کاربران', users_p: 'همه کاربران ساخته‌شده از هر دو پنل (تمپلت/دستی) + ربات — یک جدول مشترک', u_new: '＋ ساخت کاربر', search_ph: 'جستجو…', f_all: 'همه وضعیت‌ها', f_active: 'فعال', f_disabled: 'غیرفعال', f_expired: 'منقضی', s_new: 'جدیدترین', s_old: 'قدیمی‌ترین', th_user: 'نام کاربری / متا', th_status: 'وضعیت / انقضا', th_usage: 'مصرف داده', ops_h: 'لاگ عملیات', ops_p: 'همه کسر موجودی‌ها از تمپلت/دستی/ربات اینجا ثبت می‌شود.', o_count: 'تعداد عملیات', o_spent: 'مجموع پرداخت', o_credit: 'موجودی پس از آخرین عملیات', ot_all: 'همه عملیات‌ها', ot_create: 'ایجاد', ot_renew: 'تمدید', ot_delete: 'حذف', ot_disable: 'غیرفعال', ot_enable: 'فعال', ot_credit: 'شارژ', oth_op: 'عملیات', oth_user: 'کاربر', oth_amount: 'مبلغ', oth_bal: 'موجودی قبل و بعد', oth_time: 'زمان', api_h: '🔌 اتصال ربات — PasarGuard', api_p: 'این پنل دقیقاً عین پاسارگارد است؛ ربات را به آدرس‌های زیر وصل کن.', api_c1h: '۱) آدرس مخصوص ربات', api_c1p: 'آدرس API زیر را در تنظیمات ربات فروش خود وارد کن', copy_btn: 'کپی', api_c2h: '۲) API یکپارچه (برای ربات سفارشی خودت)', api_c2p: 'همه ساخت‌ها از یک موجودی کسر می‌شوند؛ فرقی نمی‌کند از تمپلت یا دستی یا ربات آمده باشد.', copy_token: 'کپی توکن', api_c3h: 'تمپلت vs دستی — هر دو از موجودی کم می‌کند (قاطی نمی‌کند)', api_c4h: 'لیست کاربران + لاگ', rep_h: 'گزارش‌ها و آمار', rep_p: 'روند فروش و درآمد — مشترک با پنل دستی', trend_h: '📈 روند فروش و درآمد', trend_p: 'خط پررنگ درآمد، خط‌چین میانگین متحرک ۷ روزه', prod_h: '🏆 پرفروش‌ترین محصول‌ها', donut1_h: '🍩 فروش به تفکیک سرویس', donut2_h: '👥 وضعیت کاربران', peak_h: '⭐ پرفروش‌ترین‌ها', week_h: '📅 فروش بر اساس روز هفته', hours_h: '🕐 ساعات اوج خرید', growth_h: '🌱 رشد کاربران', growth_p: 'تجمعی + کاربر جدید روزانه', set_h: 'تنظیمات', set_p: 'رنگ‌بندی و اطلاعات پنل ربات.', theme_h: '🎨 رنگ‌بندی پنل', theme_p: 'شیشه‌ای با بک‌گراند سایت — لوگو نکسورا', dark_mode: 'حالت تیره', pass_h: '🔒 تغییر رمز پنل', pass_p: 'رمز ورود به همین پنل ربات (جدا از پنل دستی ولی همان اکانت، همان موجودی).', pass_cur_ph: 'رمز فعلی', pass_new_ph: 'رمز جدید (حداقل ۶ حرف)', pass_btn: 'تغییر رمز', info_h: 'ℹ️ اطلاعات پنل', m_h: '👤 ایجاد کاربر جدید', m_p: 'ساخت دستی با حجم و مدت دلخواه — قیمت بر اساس نرخ هر لوکیشن از موجودی کسر می‌شود', m_user: '👤 شناسه کاربر', m_random: '🎲 رندوم', m_loc: '📍 لوکیشن', m_vol: '📦 حجم', m_days: '📅 مدت', unit_gb: 'گیگ', unit_day: 'روز', m_note: '📝 یادداشت', m_note_ph: 'مثلاً: مشتری ربات - @user', m_submit: '✓ ساخت کاربر', m_final: 'قیمت نهایی', r_h: '✅ اکانت ساخته شد', r_p: 'مبلغ با فرمول پنلت از موجودی کسر شد — از هر دو پنل مشترک', r_users: '👥 مدیریت کاربران', r_copy: '📋 کپی مشخصات' },
    en: { pg_title: 'Bot Reseller Panel | Nexora Shield', login_h: 'Bot Reseller Panel', login_p: 'Login to connect your sales bots — just like PasarGuard, with Nexora glass background', login_user: 'Username', login_pass: 'Password', login_btn: 'Enter Bot Panel', brand_sub: 'Bot Panel — 🤖 PasarGuard', nav_dash: 'Dashboard', nav_shop: 'Ready Products', nav_users: 'Users', nav_ops: 'Operations Log', nav_api: 'Bot Connection', nav_reports: 'Reports', nav_settings: 'Settings', logout: 'Log out', dash_h: 'Bot Dashboard', dash_p: 'Status overview — shared balance', refresh: '🔄 Refresh', st_credit: 'Wallet Balance', st_credit_cap: 'Shared deduction across panels and bot', st_users: 'Users', st_users_cap: 'No user limit', st_vol: 'Created Volume', st_vol_cap: 'Total services', chart_h: '📈 Activity Chart', chip7: '7 days', chip14: '14 days', chip30: '30 days', chip60: '60 days', shop_h: '📦 Ready Products', shop_p: 'Ready packages with reseller discount — order and get connection link & QR', users_h: 'User Management', users_p: 'All users created from panels + bot — one shared table', u_new: '＋ New User', search_ph: 'Search…', f_all: 'All statuses', f_active: 'Active', f_disabled: 'Disabled', f_expired: 'Expired', s_new: 'Newest', s_old: 'Oldest', th_user: 'Username / Meta', th_status: 'Status / Expiry', th_usage: 'Data Usage', ops_h: 'Operations Log', ops_p: 'All balance deductions are logged here.', o_count: 'Operations', o_spent: 'Total Spent', o_credit: 'Balance After Last Op', ot_all: 'All operations', ot_create: 'Create', ot_renew: 'Renew', ot_delete: 'Delete', ot_disable: 'Disable', ot_enable: 'Enable', ot_credit: 'Top-up', oth_op: 'Operation', oth_user: 'User', oth_amount: 'Amount', oth_bal: 'Balance Before / After', oth_time: 'Time', api_h: '🔌 Bot Connection — PasarGuard', api_p: 'This panel works exactly like PasarGuard; connect your bot to the addresses below.', api_c1h: '1) Dedicated Bot Address', api_c1p: 'Enter the API address below in your sales bot settings', copy_btn: 'Copy', api_c2h: '2) Unified API (for your custom bot)', api_c2p: 'All creations deduct from one balance, no matter the source.', copy_token: 'Copy Token', api_c3h: 'Template vs manual — both deduct from balance', api_c4h: 'User List + Log', rep_h: 'Reports & Stats', rep_p: 'Sales and revenue trend — shared balance', trend_h: '📈 Sales & Revenue Trend', trend_p: 'Bold line: revenue, dashed: 7-day moving average', prod_h: '🏆 Top Products', donut1_h: '🍩 Sales by Service', donut2_h: '👥 Users Status', peak_h: '⭐ Highlights', week_h: '📅 Sales by Weekday', hours_h: '🕐 Peak Hours', growth_h: '🌱 User Growth', growth_p: 'Cumulative + daily new users', set_h: 'Settings', set_p: 'Panel colors and info.', theme_h: '🎨 Panel Theme', theme_p: 'Glassy with site background — Nexora logo', dark_mode: 'Dark Mode', pass_h: '🔒 Change Panel Password', pass_p: 'Password for this bot panel (same account and balance).', pass_cur_ph: 'Current password', pass_new_ph: 'New password (min 6 chars)', pass_btn: 'Change Password', info_h: 'ℹ️ Panel Info', m_h: '👤 New User', m_p: 'Manual creation with custom volume & duration — priced by each location’s rates', m_user: '👤 Username', m_random: '🎲 Random', m_loc: '📍 Location', m_vol: '📦 Volume', m_days: '📅 Duration', unit_gb: 'GB', unit_day: 'days', m_note: '📝 Note', m_note_ph: 'e.g. bot customer - @user', m_submit: '✓ Create User', m_final: 'Final Price', r_h: '✅ Account Created', r_p: 'Charged from the shared balance', r_users: '👥 Users', r_copy: '📋 Copy Details' },
    zh: { pg_title: '机器人经销商面板 | Nexora Shield', login_h: '机器人经销商面板', login_p: '登录以连接您的销售机器人 — 如 PasarGuard，采用 Nexora 玻璃背景', login_user: '用户名', login_pass: '密码', login_btn: '进入机器人面板', brand_sub: '机器人面板 — 🤖 PasarGuard', nav_dash: '仪表板', nav_shop: '现成产品', nav_users: '用户管理', nav_ops: '操作日志', nav_api: '机器人连接', nav_reports: '报告', nav_settings: '设置', logout: '退出', dash_h: '机器人仪表板', dash_p: '状态概览 — 共享余额', refresh: '🔄 刷新', st_credit: '钱包余额', st_credit_cap: '面板与机器人共享扣费', st_users: '用户', st_users_cap: '用户数量不限', st_vol: '已创建流量', st_vol_cap: '服务总数', chart_h: '📈 活动图表', chip7: '7天', chip14: '14天', chip30: '30天', chip60: '60天', shop_h: '📦 现成产品', shop_p: '经销商折扣现成套餐 — 下单获取连接与二维码', users_h: '用户管理', users_p: '面板与机器人创建的所有用户 — 同一张表', u_new: '＋ 新建用户', search_ph: '搜索…', f_all: '所有状态', f_active: '正常', f_disabled: '已禁用', f_expired: '已过期', s_new: '最新', s_old: '最早', th_user: '用户名/备注', th_status: '状态/过期', th_usage: '流量使用', ops_h: '操作日志', ops_p: '所有余额扣费都记录在这里。', o_count: '操作数', o_spent: '总支出', o_credit: '上次操作后余额', ot_all: '全部操作', ot_create: '创建', ot_renew: '续费', ot_delete: '删除', ot_disable: '禁用', ot_enable: '启用', ot_credit: '充值', oth_op: '操作', oth_user: '用户', oth_amount: '金额', oth_bal: '操作前后余额', oth_time: '时间', api_h: '🔌 机器人连接 — PasarGuard', api_p: '本面板与 PasarGuard 完全一致；将机器人连接到以下地址。', api_c1h: '1）机器人专用地址', api_c1p: '在销售机器人设置中填入以下 API 地址', copy_btn: '复制', api_c2h: '2）统一 API（用于自定义机器人）', api_c2p: '所有创建均从同一余额扣费，来源不限。', copy_token: '复制令牌', api_c3h: '模板与手动 — 均从余额扣费', api_c4h: '用户列表 + 日志', rep_h: '报告与统计', rep_p: '销售与收入趋势 — 共享余额', trend_h: '📈 销售与收入趋势', trend_p: '粗线：收入，虚线：7日均值', prod_h: '🏆 畅销产品', donut1_h: '🍩 按服务划分', donut2_h: '👥 用户状态', peak_h: '⭐ 精选', week_h: '📅 按星期统计', hours_h: '🕐 高峰时段', growth_h: '🌱 用户增长', growth_p: '累计 + 每日新增', set_h: '设置', set_p: '面板颜色与信息。', theme_h: '🎨 面板主题', theme_p: '站点背景玻璃风 — Nexora 标志', dark_mode: '深色模式', pass_h: '🔒 修改面板密码', pass_p: '此机器人面板的密码（同一账号与余额）。', pass_cur_ph: '当前密码', pass_new_ph: '新密码（至少6位）', pass_btn: '修改密码', info_h: 'ℹ️ 面板信息', m_h: '👤 新建用户', m_p: '自定义流量与时长手动创建 — 按各位置费率计价', m_user: '👤 用户名', m_random: '🎲 随机', m_loc: '📍 位置', m_vol: '📦 流量', m_days: '📅 时长', unit_gb: 'GB', unit_day: '天', m_note: '📝 备注', m_note_ph: '例如：机器人客户 - @user', m_submit: '✓ 创建用户', m_final: '最终价格', r_h: '✅ 账号已创建', r_p: '已从共享余额扣费', r_users: '👥 用户管理', r_copy: '📋 复制详情' },
    ru: { pg_title: 'Панель бота реселлера | Nexora Shield', login_h: 'Панель бота реселлера', login_p: 'Войдите, чтобы подключить ботов продаж — как PasarGuard, со стеклянным фоном Nexora', login_user: 'Имя пользователя', login_pass: 'Пароль', login_btn: 'Войти в панель бота', brand_sub: 'Панель бота — 🤖 PasarGuard', nav_dash: 'Дашборд', nav_shop: 'Готовые продукты', nav_users: 'Пользователи', nav_ops: 'Журнал операций', nav_api: 'Подключение бота', nav_reports: 'Отчёты', nav_settings: 'Настройки', logout: 'Выйти', dash_h: 'Дашборд бота', dash_p: 'Общая сводка — общий баланс', refresh: '🔄 Обновить', st_credit: 'Баланс кошелька', st_credit_cap: 'Общее списание для панелей и бота', st_users: 'Пользователи', st_users_cap: 'Без лимита пользователей', st_vol: 'Созданный объём', st_vol_cap: 'Всего услуг', chart_h: '📈 График активности', chip7: '7 дней', chip14: '14 дней', chip30: '30 дней', chip60: '60 дней', shop_h: '📦 Готовые продукты', shop_p: 'Готовые пакеты со скидкой реселлера — закажите и получите ссылку и QR', users_h: 'Управление пользователями', users_p: 'Все пользователи из панелей и бота — одна общая таблица', u_new: '＋ Новый пользователь', search_ph: 'Поиск…', f_all: 'Все статусы', f_active: 'Активен', f_disabled: 'Отключён', f_expired: 'Истёк', s_new: 'Сначала новые', s_old: 'Сначала старые', th_user: 'Имя / Мета', th_status: 'Статус / Истечение', th_usage: 'Использование', ops_h: 'Журнал операций', ops_p: 'Все списания баланса записываются здесь.', o_count: 'Операций', o_spent: 'Потрачено', o_credit: 'Баланс после последней', ot_all: 'Все операции', ot_create: 'Создание', ot_renew: 'Продление', ot_delete: 'Удаление', ot_disable: 'Отключение', ot_enable: 'Включение', ot_credit: 'Пополнение', oth_op: 'Операция', oth_user: 'Пользователь', oth_amount: 'Сумма', oth_bal: 'Баланс до / после', oth_time: 'Время', api_h: '🔌 Подключение бота — PasarGuard', api_p: 'Панель работает точно как PasarGuard; подключите бота по адресам ниже.', api_c1h: '1) Адрес для бота', api_c1p: 'Введите API-адрес ниже в настройках бота продаж', copy_btn: 'Копировать', api_c2h: '2) Единый API (для своего бота)', api_c2p: 'Все создания списываются с одного баланса.', copy_token: 'Копировать токен', api_c3h: 'Шаблон и вручную — списание с баланса', api_c4h: 'Список пользователей + лог', rep_h: 'Отчёты и статистика', rep_p: 'Динамика продаж и дохода — общий баланс', trend_h: '📈 Продажи и доход', trend_p: 'Жирная — доход, пунктир — среднее за 7 дней', prod_h: '🏆 Топ продуктов', donut1_h: '🍩 Продажи по услугам', donut2_h: '👥 Статусы пользователей', peak_h: '⭐ Лучшее', week_h: '📅 Продажи по дням недели', hours_h: '🕐 Часы пик', growth_h: '🌱 Рост пользователей', growth_p: 'Всего + новые за день', set_h: 'Настройки', set_p: 'Цвета и информация панели.', theme_h: '🎨 Тема панели', theme_p: 'Стекло с фоном сайта — логотип Nexora', dark_mode: 'Тёмная тема', pass_h: '🔒 Смена пароля панели', pass_p: 'Пароль этой панели бота (тот же аккаунт и баланс).', pass_cur_ph: 'Текущий пароль', pass_new_ph: 'Новый пароль (мин. 6 символов)', pass_btn: 'Сменить пароль', info_h: 'ℹ️ Информация', m_h: '👤 Новый пользователь', m_p: 'Ручное создание с объёмом и сроком — цена по тарифам локации', m_user: '👤 Имя пользователя', m_random: '🎲 Случайно', m_loc: '📍 Локация', m_vol: '📦 Объём', m_days: '📅 Срок', unit_gb: 'ГБ', unit_day: 'дн.', m_note: '📝 Заметка', m_note_ph: 'напр.: клиент бота - @user', m_submit: '✓ Создать', m_final: 'Итоговая цена', r_h: '✅ Аккаунт создан', r_p: 'Списано с общего баланса', r_users: '👥 Пользователи', r_copy: '📋 Копировать данные' }
  };
  window.locales = PG_LOCALES;
  function pgNormLang(l) { l = String(l || 'fa').toLowerCase().split(/[-_]/)[0]; return (l === 'en' || l === 'fa' || l === 'zh' || l === 'ru') ? l : 'fa'; }
  function pgGetLang() { try { return pgNormLang(localStorage.getItem('nexora_language') || 'fa'); } catch (e) { return 'fa'; } }
  function pgSetText(el, v) {
    if (v == null) return;
    if (el.tagName === 'LABEL') {
      for (var i = 0; i < el.childNodes.length; i++) {
        var n = el.childNodes[i];
        if (n.nodeType === 3 && (/[^\s]/.test(n.nodeValue || ''))) { n.nodeValue = v; return; }
      }
    }
    el.textContent = v;
  }
  function pgApplyLang(lang) {
    lang = pgNormLang(lang);
    var D = PG_LOCALES[lang] || {}, F = PG_LOCALES.fa || {};
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      var k = el.getAttribute('data-i18n');
      pgSetText(el, (k in D) ? D[k] : F[k]);
    });
    document.querySelectorAll('[data-i18n-ph]').forEach(function (el) {
      var k = el.getAttribute('data-i18n-ph');
      var v = (k in D) ? D[k] : F[k];
      if (v != null) el.placeholder = v;
    });
    try { document.title = D.pg_title || F.pg_title; } catch (e) {}
    var dir = (lang === 'fa') ? 'rtl' : 'ltr';
    try { document.documentElement.lang = lang; document.documentElement.dir = dir; document.body.dir = dir; } catch (e) {}
    try { localStorage.setItem('nexora_language', lang); } catch (e) {}
    document.querySelectorAll('#pg-lang-menu [data-pg-lang]').forEach(function (b) { b.classList.toggle('sel', b.getAttribute('data-pg-lang') === lang); });
    try { window.dispatchEvent(new CustomEvent('nexora-lang-change', { detail: { lang: lang } })); } catch (e) {}
  }
  (function pgLangWire() {
    var btn = $('pg-lang-btn'), menu = $('pg-lang-menu');
    if (btn && menu) {
      btn.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; });
      menu.querySelectorAll('[data-pg-lang]').forEach(function (b) {
        b.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = true; pgApplyLang(b.getAttribute('data-pg-lang')); });
      });
      document.addEventListener('click', function () { if (menu && !menu.hidden) menu.hidden = true; });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu) menu.hidden = true; });
    }
  })();
  try { pgApplyLang(pgGetLang()); } catch (e) {}

  /* ---------- boot ---------- */
  (async function () {
    if (!token()) return;
    try {
      var r = await post('api/reseller-auth.php', { action: 'me' });
      if (r && r.ok) { ME = r.reseller; enter(); }
      else token('');
    } catch (e) {}
  })();
})();
