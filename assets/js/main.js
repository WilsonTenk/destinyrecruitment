/* Destiny Recruitment — shared behaviour:
   language switch (English default, Arabic on demand), mobile menu,
   WhatsApp links, service filters, call-back form. */
(function () {
  'use strict';

  var WA_NUMBER = '966544827315';
  var STORAGE_KEY = 'destiny-lang';
  var dict = window.DESTINY_AR || {};
  var html = document.documentElement;

  function storedLang() {
    try { return localStorage.getItem(STORAGE_KEY) || 'en'; } catch (e) { return 'en'; }
  }
  function saveLang(lang) {
    try { localStorage.setItem(STORAGE_KEY, lang); } catch (e) { /* private mode: ignore */ }
  }

  /* ---------- Translation ---------- */
  function applyLang(lang) {
    var ar = lang === 'ar';
    html.lang = ar ? 'ar' : 'en';
    html.dir = ar ? 'rtl' : 'ltr';

    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      if (el.dataset.en === undefined) el.dataset.en = el.innerHTML;
      var key = el.dataset.i18n;
      el.innerHTML = ar && dict[key] !== undefined ? dict[key] : el.dataset.en;
    });

    document.querySelectorAll('[data-i18n-attr]').forEach(function (el) {
      el.dataset.i18nAttr.split(';').forEach(function (pair) {
        var parts = pair.split(':');
        var attr = parts[0].trim();
        var key = parts[1].trim();
        var store = 'en' + attr.replace(/[^a-z]/gi, '');
        if (el.dataset[store] === undefined) el.dataset[store] = el.getAttribute(attr) || '';
        el.setAttribute(attr, ar && dict[key] !== undefined ? dict[key] : el.dataset[store]);
      });
    });

    var titleKey = document.body.dataset.titleKey;
    if (titleKey) {
      if (!document.body.dataset.enTitle) document.body.dataset.enTitle = document.title;
      document.title = ar && dict[titleKey] ? dict[titleKey] : document.body.dataset.enTitle;
    }

    document.querySelectorAll('.lang-btn').forEach(function (btn) {
      btn.textContent = ar ? 'English' : 'عربي';
      btn.setAttribute('aria-label', ar ? 'Switch to English' : 'التبديل إلى العربية');
      btn.setAttribute('lang', ar ? 'en' : 'ar');
    });

    updateWhatsAppLinks(lang);
  }

  /* ---------- WhatsApp ---------- */
  function waUrl(text) {
    return 'https://wa.me/' + WA_NUMBER + (text ? '?text=' + encodeURIComponent(text) : '');
  }
  function updateWhatsAppLinks(lang) {
    var ar = lang === 'ar';
    document.querySelectorAll('[data-wa]').forEach(function (a) {
      var en = a.dataset.wa;
      var arMsg = a.dataset.waAr;
      a.href = waUrl(ar && arMsg ? arMsg : en);
      a.target = '_blank';
      a.rel = 'noopener';
    });
  }

  /* ---------- Mobile menu ---------- */
  var menu = document.getElementById('mobile-menu');
  var openBtn = document.querySelector('.menu-btn');
  var closeBtn = menu && menu.querySelector('.close-btn');
  function setMenu(open) {
    if (!menu) return;
    menu.classList.toggle('open', open);
    openBtn.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) closeBtn.focus(); else openBtn.focus();
  }
  if (menu && openBtn) {
    openBtn.addEventListener('click', function () { setMenu(true); });
    closeBtn.addEventListener('click', function () { setMenu(false); });
    menu.addEventListener('click', function (e) { if (e.target === menu) setMenu(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('open')) setMenu(false);
    });
  }

  /* ---------- Service filters ---------- */
  var filters = document.querySelectorAll('.filter');
  filters.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var f = btn.dataset.filter;
      filters.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
      document.querySelectorAll('.svc').forEach(function (card) {
        var tags = (card.dataset.tags || '').split(' ');
        card.hidden = !(f === 'all' || tags.indexOf(f) !== -1);
      });
    });
  });

  /* ---------- Call-back form → WhatsApp ---------- */
  var form = document.getElementById('callback-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;
      var ar = html.lang === 'ar';
      var name = form.elements.name.value.trim();
      var phone = form.elements.phone.value.trim();
      var service = form.elements.service.options[form.elements.service.selectedIndex].text;
      var msg = form.elements.message.value.trim();
      var lines = ar
        ? ['مرحبًا، أرجو الاتصال بي.', 'الاسم: ' + name, 'الجوال: ' + phone, 'الخدمة: ' + service]
        : ['Hello, please call me back.', 'Name: ' + name, 'Mobile: ' + phone, 'Service: ' + service];
      if (msg) lines.push((ar ? 'رسالة: ' : 'Message: ') + msg);
      window.open(waUrl(lines.join('\n')), '_blank', 'noopener');
    });
  }

  /* ---------- Language button ---------- */
  document.querySelectorAll('.lang-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = html.lang === 'ar' ? 'en' : 'ar';
      saveLang(next);
      applyLang(next);
    });
  });

  var year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  applyLang(storedLang());
})();
