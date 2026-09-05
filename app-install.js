(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  var btn = $('installAppBtn');
  if (!btn) return;

  var deferredPrompt = null;
  var ua = navigator.userAgent || '';
  var isIOS = /iphone|ipad|ipod/i.test(ua) && !window.MSStream;
  var isAndroid = /android/i.test(ua);

  function showBtn() {
    btn.style.display = 'inline-flex';
  }
  function hideBtn() {
    btn.style.display = 'none';
  }

  function makeModal() {
    var wrap = document.createElement('div');
    wrap.id = 'installModal';
    wrap.style.position = 'fixed';
    wrap.style.inset = '0';
    wrap.style.zIndex = '3000';
    wrap.style.background = 'rgba(15,23,42,.55)';
    wrap.style.display = 'flex';
    wrap.style.alignItems = 'center';
    wrap.style.justifyContent = 'center';
    wrap.style.padding = '20px';

    var card = document.createElement('div');
    card.style.background = '#fff';
    card.style.borderRadius = '20px';
    card.style.maxWidth = '420px';
    card.style.width = '100%';
    card.style.padding = '30px 26px';
    card.style.boxShadow = '0 20px 60px rgba(0,0,0,.3)';
    card.style.position = 'relative';
    card.style.textAlign = 'left';

    function close() { wrap.remove(); }

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.innerHTML = '&times;';
    closeBtn.style.position = 'absolute';
    closeBtn.style.top = '12px';
    closeBtn.style.right = '16px';
    closeBtn.style.background = 'none';
    closeBtn.style.border = 'none';
    closeBtn.style.fontSize = '26px';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.color = '#64748b';
    closeBtn.addEventListener('click', close);

    var title = document.createElement('div');
    title.style.fontSize = '20px';
    title.style.fontWeight = '800';
    title.style.marginBottom = '14px';
    title.style.color = '#1e293b';

    var body = document.createElement('div');
    body.style.fontSize = '14px';
    body.style.color = '#64748b';
    body.style.lineHeight = '1.6';

    var steps = document.createElement('div');
    steps.style.marginTop = '14px';
    steps.style.display = 'flex';
    steps.style.flexDirection = 'column';
    steps.style.gap = '8px';
    steps.style.fontSize = '13px';
    steps.style.color = '#334155';

    card.appendChild(closeBtn);
    card.appendChild(title);
    card.appendChild(body);
    card.appendChild(steps);
    wrap.appendChild(card);
    document.body.appendChild(wrap);
    return { wrap: wrap, title: title, body: body, steps: steps };
  }

  var installNow = function () {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function (choice) {
        if (choice.outcome === 'accepted') { hideBtn(); }
        deferredPrompt = null;
      });
      return;
    }
    // Fallback / iOS: show manual steps (multiplataforma)
    var m = makeModal();
    if (isIOS) {
      m.title.textContent = 'Instalar la app en iOS';
      m.body.textContent = 'ClassExpress funciona como app en tu iPhone o iPad. Añádela a la pantalla de inicio:';
      var i1 = document.createElement('div'); i1.textContent = '1. Abre Safari (no otras apps).';
      var i2 = document.createElement('div'); i2.textContent = '2. Toca el botón Compartir (cuadrado con flecha ↑).';
      var i3 = document.createElement('div'); i3.textContent = '3. Elige “Añadir a pantalla de inicio” y confirma.';
      m.steps.appendChild(i1); m.steps.appendChild(i2); m.steps.appendChild(i3);
    } else if (isAndroid) {
      m.title.textContent = 'Instalar la app en Android';
      m.body.textContent = 'Desde Chrome en tu Android:';
      var a1 = document.createElement('div'); a1.textContent = '1. Abre este sitio en Chrome.';
      var a2 = document.createElement('div'); a2.textContent = '2. Toca el menú ⋮ (arriba a la derecha).';
      var a3 = document.createElement('div'); a3.textContent = '3. Elige “Añadir a pantalla de inicio” o “Instalar app”.';
      m.steps.appendChild(a1); m.steps.appendChild(a2); m.steps.appendChild(a3);
    } else {
      m.title.textContent = 'App multiplataforma';
      m.body.textContent = 'ClassExpress funciona en cualquier navegador (Web, iOS, Android), y puedes instalarla para tenerla siempre a mano:';
      var w1 = document.createElement('div'); w1.textContent = 'Tu navegador no ofrece instalación directa ahora mismo.'; w1.style.color = '#94a3b8';
      var w2 = document.createElement('div'); w2.textContent = 'Usa Chrome/Edge y busca “Instalar ClassExpress” en el menú.';
      m.steps.appendChild(w1); m.steps.appendChild(w2);
    }
    var note = document.createElement('div');
    note.style.marginTop = '14px';
    note.style.padding = '12px';
    note.style.background = 'rgba(102,221,189,.1)';
    note.style.borderRadius = '12px';
    note.style.fontSize = '13px';
    note.style.color = '#334155';
    note.textContent = 'También puedes usar ClassExpress directamente desde el navegador, sin instalar nada.';
    m.steps.appendChild(note);
  };

  btn.addEventListener('click', installNow);

  // The landing presents the app download as the primary CTA, so the button is
  // visible by default; it upgrades to a native install prompt when available.
  showBtn();

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
  });
  window.addEventListener('appinstalled', function () {
    hideBtn();
    deferredPrompt = null;
  });
})();
