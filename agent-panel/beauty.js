/* Nexora beauty.js — scroll-reveal only. Safe: adds classes, never hides without observer. */
(function () {
  function init() {
    try {
      var sels = ['.plan-card', '.content-card', '.contact-card', '.os-row', '.dl-row', '.faq-item', '.trust-item', '.pf-card', '.tk-card', '.stat', '.latest-panel', '.hero-mini-item', '.feature-panel', '.checkout-summary', '.checkout-form', '.news-card', '.tutorial-card', '.perk', '.about-pill', '.social-card', '.kpi'];
      var els = [];
      sels.forEach(function (s) {
        document.querySelectorAll(s).forEach(function (el) {
          if (!el.dataset.rv) { el.dataset.rv = '1'; el.classList.add('rv'); els.push(el); }
        });
      });
      if (!els.length) return;
      if (!('IntersectionObserver' in window)) {
        els.forEach(function (el) { el.classList.add('in'); });
        return;
      }
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        });
      }, { threshold: 0.08 });
      els.forEach(function (el) { io.observe(el); });
      setTimeout(function () { els.forEach(function (el) { el.classList.add('in'); }); }, 2500);
    } catch (e) {}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
