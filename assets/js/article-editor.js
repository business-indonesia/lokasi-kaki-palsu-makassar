(function () {
  'use strict';
  function sanitizeForEditor(html) {
    var box = document.createElement('div');
    box.innerHTML = html || '';
    box.querySelectorAll('script,style,iframe,object,embed,form,button,input,textarea,select').forEach(function (node) { node.remove(); });
    box.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes).forEach(function (attr) {
        var name = attr.name.toLowerCase();
        if (name.indexOf('on') === 0 || name === 'style' || name === 'srcdoc') node.removeAttribute(attr.name);
      });
      if (node.tagName.toLowerCase() === 'a') {
        var href = node.getAttribute('href') || '';
        if (!/^(https?:\/\/|\/|#)/i.test(href)) node.removeAttribute('href');
      }
      if (node.tagName.toLowerCase() === 'img') {
        var src = node.getAttribute('src') || '';
        if (!/^\/uploads\/articles\/[A-Za-z0-9._-]+$/i.test(src)) node.remove();
      }
    });
    return box.innerHTML;
  }
  function textToHtml(value) {
    var text = (value || '').trim();
    if (!text) return '';
    if (/<(?:p|h2|h3|h4|ul|ol|blockquote|pre|strong|em|a|img)\b/i.test(text)) return text;
    return text.split(/\n\s*\n/).filter(Boolean).map(function (part) {
      return '<p>' + part.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</p>';
    }).join('');
  }
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-article-editor]');
    if (!form) return;
    var source = form.querySelector('[data-editor-source]');
    var visual = form.querySelector('[data-editor-visual]');
    var mode = form.querySelector('[data-editor-mode]');
    var section = form.querySelector('.article-editor-section');
    var count = form.querySelector('[data-editor-count]');
    var state = form.querySelector('[data-editor-state]');
    var focusButton = form.querySelector('[data-editor-focus]');
    var tabs = Array.prototype.slice.call(form.querySelectorAll('[data-editor-tab]'));
    if (!source || !visual || !mode) return;
    function syncVisualFromSource() { visual.innerHTML = sanitizeForEditor(textToHtml(source.value)); updateCount(); }
    function syncSourceFromVisual() { source.value = sanitizeForEditor(visual.innerHTML).trim(); updateCount(); }
    function updateCount() {
      var text = (mode.value === 'visual' ? visual.innerText : source.value).replace(/\s+/g,' ').trim();
      var words = text ? text.split(' ').length : 0;
      if (count) count.textContent = words + ' kata · ' + text.length + ' karakter';
    }
    function setMode(next) {
      if (next === 'visual') { syncVisualFromSource(); source.hidden = true; visual.hidden = false; if(state) state.textContent='Editor Visual aktif'; }
      else { syncSourceFromVisual(); source.hidden = false; visual.hidden = true; if(state) state.textContent='HTML Source aktif'; }
      mode.value = next;
      tabs.forEach(function (tab) { var active = tab.getAttribute('data-editor-tab') === next; tab.classList.toggle('is-active', active); tab.setAttribute('aria-selected', active ? 'true' : 'false'); });
      updateCount();
    }
    tabs.forEach(function (tab) { tab.addEventListener('click', function () { setMode(tab.getAttribute('data-editor-tab') || 'visual'); }); });
    form.querySelectorAll('[data-editor-command]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (mode.value !== 'visual') setMode('visual');
        visual.focus();
        var command = button.getAttribute('data-editor-command');
        if (command === 'formatBlock') document.execCommand('formatBlock', false, '<' + (button.getAttribute('data-format') || 'p') + '>');
        else if (command === 'blockquote') document.execCommand('formatBlock', false, '<blockquote>');
        else if (command === 'createLink') { var url = window.prompt('URL tautan (https://... atau /path):', 'https://'); if (url) document.execCommand('createLink', false, url); }
        else if (command) document.execCommand(command, false, null);
        updateCount();
      });
    });
    if (focusButton && section) focusButton.addEventListener('click', function () {
      var active = section.classList.toggle('is-focus');
      focusButton.setAttribute('aria-pressed', active ? 'true' : 'false');
      focusButton.textContent = active ? 'Tutup Fokus' : 'Fokus';
      if (active) visual.focus();
    });
    ['input','keyup','paste'].forEach(function (name) { visual.addEventListener(name, updateCount); source.addEventListener(name, updateCount); });
    form.addEventListener('submit', function () { if (mode.value === 'visual') syncSourceFromVisual(); });
    visual.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') { event.preventDefault(); document.execCommand('bold'); updateCount(); }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'i') { event.preventDefault(); document.execCommand('italic'); updateCount(); }
    });
    setMode(mode.value === 'html' ? 'html' : 'visual');
  });
}());
