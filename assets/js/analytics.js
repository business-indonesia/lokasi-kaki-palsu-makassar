(function () {
  'use strict';
  var script = document.currentScript;
  var id = script && script.getAttribute('data-ga4') || '';
  if (!/^G-[A-Z0-9]+$/i.test(id)) return;
  if (navigator.doNotTrack === '1') return;
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', id, {send_page_view: false, anonymize_ip: true, allow_google_signals: false, allow_ad_personalization_signals: false});
  var remote = document.createElement('script');
  remote.async = true;
  remote.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
  document.head.appendChild(remote);
  function send(event, params) {
    params = params || {};
    if (window.gtag) window.gtag('event', event, params);
    try {
      if (navigator.sendBeacon) navigator.sendBeacon('/analytics/event.php', new Blob([JSON.stringify({event:event,path:location.pathname,params:params})], {type:'application/json'}));
    } catch (e) {}
  }
  send('page_view', {page_location: location.href, page_title: document.title, page_type: document.body && document.body.classList.contains('article-page') ? 'article' : 'public'});
  var article = document.querySelector('.article-page');
  if (article) {
    var heading = article.querySelector('h1');
    send('article_view', {article_path: location.pathname, article_title: heading ? (heading.textContent || '').trim().slice(0,120) : '', article_slug: location.pathname.split('/').filter(Boolean).pop() || ''});
    article.addEventListener('click', function (event) {
      var link = event.target.closest ? event.target.closest('a') : null;
      if (!link) return;
      var href = link.getAttribute('href') || '';
      if (/^https:\/\/wa\.me\//i.test(href)) send('article_cta_click', {cta:'whatsapp', article_path:location.pathname});
      else if (/^\/artikel\//.test(href)) send('article_internal_link_click', {target_path:href, article_path:location.pathname});
    });
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('a') : null;
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (/^https:\/\/wa\.me\//i.test(href)) send('consultation_whatsapp_click', {link_text: (link.textContent || '').trim().slice(0,80), page_path:location.pathname});
    else if (/^tel:/i.test(href)) send('phone_click', {page_path:location.pathname});
    else if (/^\/panduan\//.test(href)) send('guide_open', {target_path:href, page_path:location.pathname});
    else if (/^\/lokasi-pelayanan\//.test(href)) send('region_open', {target_path:href, page_path:location.pathname});
    else if (/^\/cari\//.test(href) && location.pathname.startsWith('/cari/')) send('search_result_click', {target_path:href, page_path:location.pathname});
  });
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('form[role="search"]')) return;
    var input = form.querySelector('input[name="q"]');
    send('search_submit', {search_term: input ? (input.value || '').trim().slice(0,80) : '', page_path:location.pathname});
  });
}());
