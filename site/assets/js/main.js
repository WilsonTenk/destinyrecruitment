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
    var titleAr = titleKey ? dict[titleKey] : document.body.dataset.titleAr;
    if (!document.body.dataset.enTitle) document.body.dataset.enTitle = document.title;
    document.title = ar && titleAr ? titleAr : document.body.dataset.enTitle;

    /* Arabic image descriptions on admin-written articles */
    document.querySelectorAll('[data-alt-ar]').forEach(function (img) {
      if (img.dataset.altEn === undefined) img.dataset.altEn = img.getAttribute('alt') || '';
      img.setAttribute('alt', ar && img.dataset.altAr ? img.dataset.altAr : img.dataset.altEn);
    });

    document.querySelectorAll('.lang-btn').forEach(function (btn) {
      btn.textContent = ar ? 'English' : 'عربي';
      btn.setAttribute('aria-label', ar ? 'Switch to English' : 'التبديل إلى العربية');
      btn.setAttribute('lang', ar ? 'en' : 'ar');
    });

    updateWhatsAppLinks(lang);
    applyLive();
  }

  /* ---------- Live details from the admin (cPanel only) ----------
     Availability labels and contact details come from api/site.php when the
     site runs on the PHP server. Anywhere else the page keeps its built-in text. */
  var live = null;
  function formatPhone(p) {
    var d = String(p || '').replace(/[^\d]/g, '');
    if (d.indexOf('966') === 0 && d.length === 12) return '+966 ' + d.slice(3, 5) + ' ' + d.slice(5, 8) + ' ' + d.slice(8);
    return p ? (String(p).charAt(0) === '+' ? p : '+' + d) : '';
  }
  function applyLive() {
    if (!live) return;
    var ar = html.lang === 'ar';
    var av = live.availability || {};
    document.querySelectorAll('[data-avail]').forEach(function (el) {
      var v = av[el.dataset.avail];
      if (!v) return;
      var key = 'status.' + v;
      var en = { ok: 'Available now', ahead: 'Book ahead', off: 'Fully booked' }[v];
      el.classList.remove('ok', 'ahead', 'off');
      el.classList.add(v);
      el.dataset.i18n = key;
      el.dataset.en = en;
      el.innerHTML = ar && dict[key] ? dict[key] : en;
    });
    var c = live.contact || {};
    document.querySelectorAll('[data-setting]').forEach(function (el) {
      var k = el.dataset.setting;
      if (k === 'phone1' || k === 'phone2') {
        if (!c[k]) { if (el.tagName === 'A' || el.tagName === 'SPAN') el.hidden = true; return; }
        el.hidden = false;
        el.textContent = formatPhone(c[k]);
        if (el.tagName === 'A') el.href = 'tel:' + c[k];
      } else if (k === 'whatsapp' && c.whatsapp) {
        el.textContent = formatPhone(c.whatsapp);
      } else if ((k === 'hours' || k === 'address') && (c[k + '_en'] || c[k + '_ar'])) {
        el.textContent = ar ? (c[k + '_ar'] || c[k + '_en']) : (c[k + '_en'] || c[k + '_ar']);
      } else if (k === 'maps' && c.maps_url) {
        el.href = c.maps_url;
        el.target = '_blank';
        el.rel = 'noopener';
      }
    });
  }
  function loadLive() {
    if (!/^https?:$/.test(location.protocol) || /github\.io$/.test(location.hostname)) return;
    if (!window.fetch) return;
    fetch('api/site.php', { credentials: 'same-origin', cache: 'no-cache' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.availability) return;
        live = data;
        if (live.contact && /^\d{8,15}$/.test(live.contact.whatsapp || '')) {
          WA_NUMBER = live.contact.whatsapp;
          updateWhatsAppLinks(html.lang);
        }
        applyLive();
      })
      .catch(function () { /* no server-side admin here: keep the built-in text */ });
  }

  /* Keep a copy of a form in the admin inbox (cPanel only). Never blocks WhatsApp. */
  function canBeacon() {
    return /^https?:$/.test(location.protocol) && !/github\.io$/.test(location.hostname) && !!navigator.sendBeacon;
  }
  function pageName() { return (location.pathname.split('/').pop() || 'index').replace(/\.html$/i, '').toLowerCase(); }
  function saveRequest(payload) {
    try {
      if (!canBeacon()) return;
      payload.lang = html.lang;
      payload.page = pageName();
      navigator.sendBeacon('api/request.php', new Blob([JSON.stringify(payload)], { type: 'text/plain' }));
    } catch (e) { /* ignore */ }
  }
  /* Count clicks on WhatsApp buttons for the admin WhatsApp report (anonymous, cPanel only). */
  function trackWhatsApp(e) {
    try {
      var a = e.target && e.target.closest ? e.target.closest('a[href*="wa.me/"]') : null;
      if (!a || !canBeacon()) return;
      var source = a.classList.contains('fab') ? 'fab'
        : a.closest('#mobile-menu') ? 'menu'
        : a.closest('header') ? 'header'
        : a.closest('footer') ? 'footer'
        : a.classList.contains('contact-card') ? 'contact-card' : 'page';
      navigator.sendBeacon('api/track.php', new Blob([JSON.stringify({ source: source, page: pageName(), lang: html.lang })], { type: 'text/plain' }));
    } catch (err) { /* never block the click */ }
  }
  document.addEventListener('click', trackWhatsApp, true);
  document.addEventListener('auxclick', function (e) { if (e.button === 1) trackWhatsApp(e); }, true);
  /* English names of the checked choices, so the inbox reads the same whatever the visitor's language. */
  function englishLabels(formEl, name) {
    return Array.prototype.slice.call(formEl.querySelectorAll('input[name="' + name + '"]:checked'))
      .map(function (input) {
        var span = input.nextElementSibling;
        return (span.dataset.en || span.textContent).replace(/<[^>]+>/g, '').trim();
      });
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

  /* Text of the checked choices in a form group, in the current language. */
  function checkedLabels(formEl, name) {
    return Array.prototype.slice.call(formEl.querySelectorAll('input[name="' + name + '"]:checked'))
      .map(function (input) { return input.nextElementSibling.textContent.trim(); });
  }
  function joinList(items) { return items.join(html.lang === 'ar' ? '، ' : ', '); }

  /* ---------- Call-back form → WhatsApp ---------- */
  var form = document.getElementById('callback-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;
      var ar = html.lang === 'ar';
      var name = form.elements.name.value.trim();
      var phone = form.elements.phone.value.trim();
      var services = checkedLabels(form, 'service');
      var msg = form.elements.message.value.trim();
      var lines = ar
        ? ['مرحبًا، أرجو الاتصال بي.', 'الاسم: ' + name, 'الجوال: ' + phone]
        : ['Hello, please call me back.', 'Name: ' + name, 'Mobile: ' + phone];
      if (services.length) lines.push((ar ? 'الخدمات: ' : 'Services: ') + joinList(services));
      if (msg) lines.push((ar ? 'رسالة: ' : 'Message: ') + msg);
      window.open(waUrl(lines.join('\n')), '_blank', 'noopener');
      saveRequest({ kind: 'callback', name: name, phone: phone, services: englishLabels(form, 'service'), notes: msg,
                    website: form.elements.website ? form.elements.website.value : '' });
    });
  }

  /* ---------- Request builder (Services page) ---------- */
  var reqForm = document.getElementById('request-form');
  if (reqForm) {
    var addBtns = document.querySelectorAll('[data-add-service]');
    var bar = document.getElementById('request-bar');
    var countEl = document.getElementById('request-count');
    var errorEl = document.getElementById('request-error');
    var builderVisible = false;

    function serviceBox(key) { return reqForm.querySelector('input[name="services"][value="' + key + '"]'); }
    function selectedCount() { return reqForm.querySelectorAll('input[name="services"]:checked').length; }

    function refresh() {
      addBtns.forEach(function (btn) {
        var on = serviceBox(btn.dataset.addService).checked;
        btn.setAttribute('aria-pressed', String(on));
        var card = btn.closest('.svc');
        if (card) card.classList.toggle('is-added', on);
      });
      var n = selectedCount();
      countEl.textContent = html.lang === 'ar' ? n.toLocaleString('ar-EG') : n;
      var show = n > 0 && !builderVisible;
      bar.hidden = !show;
      document.body.classList.toggle('has-request-bar', show);
      if (n > 0) errorEl.textContent = '';
    }

    addBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var box = serviceBox(btn.dataset.addService);
        box.checked = !box.checked;
        refresh();
      });
    });
    reqForm.addEventListener('change', refresh);
    /* Run after the language switch has applied, so the count uses the right digits. */
    document.querySelectorAll('.lang-btn').forEach(function (b) { b.addEventListener('click', function () { setTimeout(refresh, 0); }); });

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        builderVisible = entries[0].isIntersecting;
        refresh();
      }, { threshold: 0.15 }).observe(reqForm);
    }

    reqForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var ar = html.lang === 'ar';
      var services = checkedLabels(reqForm, 'services');
      if (!services.length) {
        errorEl.textContent = ar ? 'اختر خدمة واحدة على الأقل.' : 'Please pick at least one service.';
        reqForm.querySelector('input[name="services"]').focus();
        return;
      }
      var home = checkedLabels(reqForm, 'home').concat(checkedLabels(reqForm, 'size'));
      var extras = checkedLabels(reqForm, 'extras');
      var care = checkedLabels(reqForm, 'care');
      var schedule = checkedLabels(reqForm, 'schedule');
      var area = reqForm.elements.area.value.trim();
      var start = reqForm.elements.start.value;
      var notes = reqForm.elements.notes.value.trim();
      var name = reqForm.elements.name ? reqForm.elements.name.value.trim() : '';
      var phone = reqForm.elements.phone ? reqForm.elements.phone.value.trim() : '';
      var L = ar
        ? { hi: 'مرحبًا، أود الحصول على عرض سعر من إدينام ديستني.', name: 'الاسم: ', phone: 'الجوال: ', services: 'الخدمات: ', home: 'المنزل: ', extras: 'إضافات: ',
            care: 'الرعاية: ', schedule: 'الجدول: ', area: 'الحي: ', start: 'تاريخ البدء: ', notes: 'ملاحظات: ' }
        : { hi: "Hello, I'd like a quote from Edinam Destiny.", name: 'Name: ', phone: 'Mobile: ', services: 'Services: ', home: 'Home: ', extras: 'Also: ',
            care: 'Care for: ', schedule: 'Schedule: ', area: 'Area: ', start: 'Start date: ', notes: 'Notes: ' };
      var lines = [L.hi];
      if (name) lines.push(L.name + name);
      if (phone) lines.push(L.phone + phone);
      lines.push(L.services + joinList(services));
      if (home.length) lines.push(L.home + joinList(home));
      if (extras.length) lines.push(L.extras + joinList(extras));
      if (care.length) lines.push(L.care + joinList(care));
      if (schedule.length) lines.push(L.schedule + joinList(schedule));
      if (area) lines.push(L.area + area);
      if (start) lines.push(L.start + start);
      if (notes) lines.push(L.notes + notes);
      window.open(waUrl(lines.join('\n')), '_blank', 'noopener');
      saveRequest({
        kind: 'build', name: name, phone: phone, services: englishLabels(reqForm, 'services'),
        home: englishLabels(reqForm, 'home').concat(englishLabels(reqForm, 'size')), extras: englishLabels(reqForm, 'extras'),
        care: englishLabels(reqForm, 'care'), schedule: englishLabels(reqForm, 'schedule'),
        area: area, start: start, notes: notes, website: reqForm.elements.website ? reqForm.elements.website.value : ''
      });
    });

    refresh();
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
  loadLive();
})();
