/* Nexora mobile layer — bottom app nav + menu language row. */
window.__nxMobileMenuHandled = true;
(function () {
  'use strict';
  function isMobile() { try { return window.matchMedia('(max-width:900px)').matches; } catch (e) { return false; } }
  function lang() { try { return localStorage.getItem('nexora_language') || 'fa'; } catch (e) { return 'fa'; } }
  var T = {
    fa: { home: 'خانه', services: 'سرویس‌ها', ticket: 'تیکت', account: 'حساب' },
    en: { home: 'Home', services: 'Services', ticket: 'Tickets', account: 'Account' },
    ar: { home: 'الرئيسية', services: 'الخدمات', ticket: 'التذاكر', account: 'حسابي' },
    tr: { home: 'Ana sayfa', services: 'Hizmetler', ticket: 'Destek', account: 'Hesabım' }
  };
  T.dari = T.fa;
  function t(k) { var L = T[lang()] || T.fa; return L[k] || T.fa[k] || k; }
  var ICO = {
    home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>',
    services: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><rect x="3" y="7" width="18" height="13" rx="3"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
    ticket: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8z"/><path d="M13 6v2M13 11v2M13 16v2"/></svg>',
    account: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.5-3.5 4.5-5 7.5-5s6 1.5 7.5 5"/></svg>'
  };
  function path() { try { return (location.pathname.split('/').pop() || 'index.html').toLowerCase(); } catch (e) { return 'index.html'; } }
  function buildNav() {
    if (document.querySelector('.m-nav')) return;
    var p = path();
    var nav = document.createElement('nav');
    nav.className = 'm-nav';
    nav.setAttribute('aria-label', 'mobile');
    function item(href, icon, label, active) {
      var a = document.createElement('a');
      a.href = href;
      if (active) a.classList.add('active');
      a.innerHTML = icon + '<span>' + label + '</span>';
      return a;
    }
    nav.appendChild(item('index.html', ICO.home, t('home'), p === 'index.html' || p === ''));
    nav.appendChild(item('services.html', ICO.services, t('services'), p === 'services.html'));
    nav.appendChild(item('ticket.html', ICO.ticket, t('ticket'), p === 'ticket.html'));
    var me = document.createElement('button');
    me.type = 'button';
    me.className = 'mi' + (p === 'profile.html' ? ' active' : '');
    me.innerHTML = ICO.account + '<span>' + t('account') + '</span>';
    me.addEventListener('click', function () {
      var tk = '';
      try { tk = (window.NexoraAccount && NexoraAccount.getToken()) || ''; } catch (e) {}
      if (tk) location.href = 'profile.html';
      else if (window.NexoraAuth) NexoraAuth.open('login');
      else location.href = 'profile.html';
    });
    nav.appendChild(me);
    document.body.appendChild(nav);
    document.querySelectorAll('.page-languages button,.language').forEach(function (b) {
      b.addEventListener('click', function () { setTimeout(relabel, 150); });
    });
  }
  function relabel() {
    var nav = document.querySelector('.m-nav');
    if (!nav) return;
    var spans = nav.querySelectorAll('span');
    var keys = ['home', 'services', 'ticket', 'account'];
    spans.forEach(function (s, i) { if (keys[i]) s.textContent = t(keys[i]); });
    syncLangRow();
  }
  function buildLangRow() {
    /* flags live in the header now; keep menu clean */
    try {
      document.querySelectorAll('.navigation .m-lang').forEach(function (n) { n.remove(); });
    } catch (e) {}
    return;
    var nav = document.querySelector('.navigation');
    if (!nav || nav.querySelector('.m-lang')) return;
    var src = document.querySelectorAll('.nav-actions .language');
    if (!src.length) return;
    var row = document.createElement('div');
    row.className = 'm-lang';
    src.forEach(function (b) {
      var c = document.createElement('button');
      c.type = 'button';
      c.innerHTML = b.innerHTML;
      c.title = b.title || '';
      c.dataset.language = b.dataset.language;
      c.addEventListener('click', function () { b.click(); setTimeout(syncLangRow, 150); });
      row.appendChild(c);
    });
    nav.appendChild(row);
    syncLangRow();
  }
  function syncLangRow() {
    var l = lang();
    document.querySelectorAll('.m-lang button').forEach(function (b) {
      b.classList.toggle('on', b.dataset.language === l);
    });
  }
  function init() {
    buildNav();
    buildLangRow();
    relabel();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
/* nx-menu-extras: account + support shortcuts inside hamburger menu (additive, null-safe) */
(function () {
  try {
    var nav = document.querySelector('.nav .navigation');
    if (!nav || nav.querySelector('.m-acc')) return;
    var logged = false;
    try { logged = !!(window.NexoraAccount && NexoraAccount.getToken()); } catch (e) {}
    var box = document.createElement('div');
    box.className = 'm-acc';
    var acc = document.createElement('a');
    acc.className = 'm-acc-btn';
    if (logged) { acc.href = 'profile.html'; acc.textContent = '👤 حساب کاربری'; }
    else { acc.href = '#'; acc.setAttribute('data-auth', 'login'); acc.textContent = '👤 ورود / عضویت'; }
    var sup = document.createElement('a');
    sup.className = 'm-acc-btn';
    sup.href = 'contact.html';
    sup.textContent = '📞 پشتیبانی';
    box.appendChild(acc);
    box.appendChild(sup);
    nav.appendChild(box);
    box.addEventListener('click', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[data-auth]') : null;
      if (a) {
        e.preventDefault();
        try { document.querySelector('.nav').classList.remove('open'); } catch (_) {}
        try { if (window.NexoraAuth) NexoraAuth.open('login'); else location.href = 'index.html'; } catch (_) {}
      }
    });
  } catch (e) {}
})();
/* nx-esc: close hamburger with Escape + auto-close on resize to desktop */
(function () {
  try {
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var nav = document.querySelector('.nav.open');
        if (nav) nav.classList.remove('open');
      }
    });
    window.addEventListener('resize', function () {
      try {
        if (window.matchMedia && matchMedia('(min-width:901px)').matches) {
          var nav = document.querySelector('.nav.open');
          if (nav) nav.classList.remove('open');
        }
      } catch (_) {}
    });
  } catch (e) {}
})();
/* nx-drawer: right 5cm drawer + overlay click + unified nav for all templates */
(function () {
  try {
    function closeAll() {
      document.querySelectorAll('.nav.open, .page-nav.open, .plan-nav.open, .page-header.open').forEach(function(n){ n.classList.remove('open'); });
      try{ document.querySelectorAll('.menu-button[aria-expanded=\"true\"], .page-menu[aria-expanded=\"true\"]').forEach(function(b){ b.setAttribute('aria-expanded','false'); }); }catch(_){}
      try{ document.body.style.overflow=''; }catch(_){}
    }
    document.addEventListener('click', function(e){
      var nav = document.querySelector('.nav.open, .page-nav.open, .plan-nav.open, .page-header.open');
      if (!nav) return;
      // click on backdrop (the container itself, not the drawer)
      if (e.target === nav) closeAll();
      // click on overlay pseudo is not detectable, so also handle click outside drawer
      var insideDrawer = e.target.closest('.navigation, nav');
      if (nav.contains(e.target) && !insideDrawer) closeAll();
      var link = e.target.closest('.nav.open .navigation a, .page-nav.open nav a, .plan-nav.open nav a');
      if (link) setTimeout(closeAll, 180);
    });
    // also close on overlay via nav::before click is captured as nav click
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeAll();
    });
    window.addEventListener('resize', function(){
      try { if (window.matchMedia && matchMedia('(min-width:901px)').matches) closeAll(); } catch(_){}
    });
    // unify hamburger for page/plan
    document.querySelectorAll('.menu-button, .page-menu').forEach(function(btn){
      // avoid double binding
      if (btn._nxBound) return; btn._nxBound = true;
      btn.addEventListener('click', function(ev){
        ev.preventDefault(); ev.stopPropagation();
        var p = btn.closest('.nav, .page-nav, .plan-nav, .page-header, .plan-header');
        var target = p || document.querySelector('.nav') || document.querySelector('.page-nav') || document.querySelector('.plan-nav');
        if (!target) return;
        var willOpen = !target.classList.contains('open');
        target.classList.toggle('open');
        try{ btn.setAttribute('aria-expanded', String(willOpen)); }catch(_){}
        // sync body lock immediately for fallback browsers
        try{ document.body.style.overflow = willOpen ? 'hidden' : ''; }catch(_){}
      });
    });
  } catch(e){}
})();
/* nx-lock: freeze background scroll while hamburger open */
(function () {
  try {
    if (!('MutationObserver' in window)) return;
    var lock = function () {
      try {
        var open = document.querySelector('.nav.open, .page-nav.open, .plan-nav.open, .page-header.open');
        document.body.style.overflow = open ? 'hidden' : '';
      } catch (e) {}
    };
    document.querySelectorAll('.nav, .page-nav, .plan-nav, .page-header').forEach(function(n){
      try{ new MutationObserver(lock).observe(n, { attributes: true, attributeFilter: ['class'] }); }catch(_){}
    });
  } catch (e) {}
})();
