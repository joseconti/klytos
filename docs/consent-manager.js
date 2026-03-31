/**
 * =============================================================================
 * CONSENT MANAGER - API de Gestion de Consentimiento de Cookies
 * =============================================================================
 *
 * Sistema para sitios estaticos que permite a plugins declarar sus cookies
 * y scripts, y controla su carga segun el consentimiento del visitante.
 *
 * Compatible con GDPR, CCPA y ePrivacy Directive.
 *
 * USO BASICO:
 *
 *   <script src="consent-manager.js"></script>
 *   <script>
 *     ConsentManager.init({ /* opciones * / });
 *   </script>
 *
 * REGISTRO DE PLUGINS:
 *
 *   ConsentManager.register({
 *     pluginId: 'mi-plugin',
 *     name: 'Mi Plugin Analytics',
 *     category: 'analytics',
 *     description: 'Recopila estadisticas de uso anonimizadas',
 *     cookies: [
 *       { name: '_ga', duration: '2 anos', description: 'Identificador de Google Analytics' }
 *     ],
 *     scripts: ['https://www.googletagmanager.com/gtag/js?id=UA-XXXXX'],
 *     onAccept: function() { /* inicializar * / },
 *     onReject: function() { /* limpiar * / }
 *   });
 *
 * =============================================================================
 */

(function(global) {
  'use strict';

  // =========================================================================
  // CONSTANTES
  // =========================================================================

  var CONSENT_COOKIE_NAME = '__consent_prefs';
  var CONSENT_COOKIE_DAYS = 365;
  var CONSENT_VERSION = 1;

  var CATEGORIES = {
    necessary: {
      id: 'necessary',
      name: 'Obligatorias',
      description: 'Cookies esenciales para el funcionamiento del sitio. No se pueden desactivar.',
      required: true
    },
    functional: {
      id: 'functional',
      name: 'Funcionales',
      description: 'Mejoran la experiencia de uso (preferencias, idioma, sesion).',
      required: false
    },
    analytics: {
      id: 'analytics',
      name: 'Analiticas',
      description: 'Permiten medir el trafico y el comportamiento de los visitantes.',
      required: false
    },
    marketing: {
      id: 'marketing',
      name: 'Marketing',
      description: 'Se usan para mostrar publicidad relevante y medir campanas.',
      required: false
    }
  };

  // =========================================================================
  // ESTADO INTERNO
  // =========================================================================

  var _initialized = false;
  var _config = {};
  var _plugins = {};          // pluginId -> registro del plugin
  var _consent = null;        // estado actual del consentimiento
  var _listeners = [];        // callbacks de cambio de consentimiento
  var _bannerElement = null;
  var _panelElement = null;

  // =========================================================================
  // UTILIDADES DE COOKIES
  // =========================================================================

  function setCookie(name, value, days) {
    var expires = '';
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      expires = '; expires=' + date.toUTCString();
    }
    var sameSite = '; SameSite=Lax';
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/' + sameSite + secure;
  }

  function getCookie(name) {
    var nameEQ = name + '=';
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
      var c = cookies[i].trim();
      if (c.indexOf(nameEQ) === 0) {
        return decodeURIComponent(c.substring(nameEQ.length));
      }
    }
    return null;
  }

  function deleteCookie(name, paths) {
    var pathsToTry = paths || ['/', ''];
    var domains = [
      '',
      location.hostname,
      '.' + location.hostname
    ];
    // Intentar borrar en combinaciones de path y dominio
    for (var p = 0; p < pathsToTry.length; p++) {
      for (var d = 0; d < domains.length; d++) {
        var domainStr = domains[d] ? '; domain=' + domains[d] : '';
        var pathStr = pathsToTry[p] ? '; path=' + pathsToTry[p] : '';
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT' + pathStr + domainStr;
      }
    }
  }

  // =========================================================================
  // GESTION DEL CONSENTIMIENTO
  // =========================================================================

  function loadConsent() {
    var raw = getCookie(CONSENT_COOKIE_NAME);
    if (!raw) return null;
    try {
      var data = JSON.parse(raw);
      if (data.version !== CONSENT_VERSION) return null;
      return data;
    } catch (e) {
      return null;
    }
  }

  function saveConsent(categories) {
    var data = {
      version: CONSENT_VERSION,
      timestamp: new Date().toISOString(),
      categories: categories
    };
    setCookie(CONSENT_COOKIE_NAME, JSON.stringify(data), CONSENT_COOKIE_DAYS);
    _consent = data;
  }

  function hasConsent(category) {
    if (category === 'necessary') return true;
    if (!_consent || !_consent.categories) return false;
    return _consent.categories[category] === true;
  }

  function getConsentState() {
    if (!_consent) return null;
    var state = {};
    for (var cat in CATEGORIES) {
      state[cat] = hasConsent(cat);
    }
    return state;
  }

  // =========================================================================
  // CARGA DE SCRIPTS
  // =========================================================================

  function loadScript(src, callback) {
    var script = document.createElement('script');
    script.src = src;
    script.async = true;
    if (callback) {
      script.onload = function() { callback(null); };
      script.onerror = function() { callback(new Error('Error al cargar: ' + src)); };
    }
    document.head.appendChild(script);
    return script;
  }

  function activateBlockedScripts(category) {
    // Buscar scripts con type="text/plain" y data-consent-category
    var scripts = document.querySelectorAll(
      'script[type="text/plain"][data-consent-category="' + category + '"]'
    );
    for (var i = 0; i < scripts.length; i++) {
      var original = scripts[i];
      var newScript = document.createElement('script');
      // Copiar atributos excepto type y data-consent-category
      for (var j = 0; j < original.attributes.length; j++) {
        var attr = original.attributes[j];
        if (attr.name !== 'type' && attr.name !== 'data-consent-category') {
          newScript.setAttribute(attr.name, attr.value);
        }
      }
      // Copiar contenido inline si existe
      if (original.textContent) {
        newScript.textContent = original.textContent;
      }
      original.parentNode.replaceChild(newScript, original);
    }
  }

  // =========================================================================
  // LIMPIEZA DE COOKIES
  // =========================================================================

  function cleanCookiesForCategory(category) {
    for (var pluginId in _plugins) {
      var plugin = _plugins[pluginId];
      if (plugin.category === category && plugin.cookies) {
        for (var i = 0; i < plugin.cookies.length; i++) {
          var cookieInfo = plugin.cookies[i];
          var paths = cookieInfo.paths || ['/', ''];
          deleteCookie(cookieInfo.name, paths);
        }
      }
    }
  }

  // =========================================================================
  // APLICAR CONSENTIMIENTO
  // =========================================================================

  function applyConsent(newCategories, oldCategories) {
    for (var category in CATEGORIES) {
      if (category === 'necessary') continue;

      var wasAccepted = oldCategories && oldCategories[category];
      var isAccepted = newCategories[category];

      if (isAccepted && !wasAccepted) {
        // Categoria recien aceptada: cargar scripts y activar plugins
        activateBlockedScripts(category);
        activatePluginsForCategory(category);
      } else if (!isAccepted && wasAccepted) {
        // Categoria revocada: limpiar cookies
        cleanCookiesForCategory(category);
        deactivatePluginsForCategory(category);
      }
    }

    // Siempre activar los necesarios
    activateBlockedScripts('necessary');
    activatePluginsForCategory('necessary');

    // Notificar listeners
    notifyListeners(newCategories);
  }

  function activatePluginsForCategory(category) {
    for (var pluginId in _plugins) {
      var plugin = _plugins[pluginId];
      if (plugin.category !== category) continue;
      if (plugin._activated) continue;

      // Cargar scripts declarados
      if (plugin.scripts && plugin.scripts.length > 0) {
        var remaining = plugin.scripts.length;
        var errors = [];
        for (var i = 0; i < plugin.scripts.length; i++) {
          (function(scriptSrc) {
            loadScript(scriptSrc, function(err) {
              if (err) errors.push(err);
              remaining--;
              if (remaining === 0 && plugin.onAccept) {
                plugin.onAccept(errors.length > 0 ? errors : null);
              }
            });
          })(plugin.scripts[i]);
        }
      } else if (plugin.onAccept) {
        plugin.onAccept(null);
      }

      plugin._activated = true;
    }
  }

  function deactivatePluginsForCategory(category) {
    for (var pluginId in _plugins) {
      var plugin = _plugins[pluginId];
      if (plugin.category !== category) continue;
      if (!plugin._activated) continue;

      if (plugin.onReject) {
        plugin.onReject();
      }
      plugin._activated = false;
    }
  }

  function notifyListeners(categories) {
    for (var i = 0; i < _listeners.length; i++) {
      try {
        _listeners[i](categories);
      } catch (e) {
        console.error('[ConsentManager] Error en listener:', e);
      }
    }
  }

  // =========================================================================
  // REGISTRO DE PLUGINS
  // =========================================================================

  function validatePluginRegistration(opts) {
    if (!opts.pluginId) throw new Error('[ConsentManager] pluginId es obligatorio');
    if (!opts.name) throw new Error('[ConsentManager] name es obligatorio');
    if (!opts.category) throw new Error('[ConsentManager] category es obligatorio');
    if (!CATEGORIES[opts.category]) {
      throw new Error('[ConsentManager] Categoria no valida: ' + opts.category +
        '. Usa: ' + Object.keys(CATEGORIES).join(', '));
    }
    if (opts.cookies && !Array.isArray(opts.cookies)) {
      throw new Error('[ConsentManager] cookies debe ser un array');
    }
    if (opts.scripts && !Array.isArray(opts.scripts)) {
      throw new Error('[ConsentManager] scripts debe ser un array');
    }
    if (opts.cookies) {
      for (var i = 0; i < opts.cookies.length; i++) {
        var c = opts.cookies[i];
        if (!c.name) throw new Error('[ConsentManager] Cada cookie debe tener un name');
      }
    }
  }

  // =========================================================================
  // INFORMACION DE PLUGINS (Panel de transparencia)
  // =========================================================================

  function getPluginRegistry() {
    var result = {};
    for (var cat in CATEGORIES) {
      result[cat] = {
        category: CATEGORIES[cat],
        plugins: []
      };
    }
    for (var pluginId in _plugins) {
      var plugin = _plugins[pluginId];
      result[plugin.category].plugins.push({
        pluginId: plugin.pluginId,
        name: plugin.name,
        description: plugin.description || '',
        vendor: plugin.vendor || '',
        privacyUrl: plugin.privacyUrl || '',
        cookies: (plugin.cookies || []).map(function(c) {
          return {
            name: c.name,
            duration: c.duration || 'Sesion',
            description: c.description || '',
            type: c.type || 'cookie'
          };
        }),
        scripts: plugin.scripts || [],
        activated: !!plugin._activated
      });
    }
    return result;
  }

  // =========================================================================
  // BANNER Y UI
  // =========================================================================

  function injectStyles() {
    if (document.getElementById('consent-manager-styles')) return;

    var css = [
      '/* Consent Manager - Estilos base */',
      '.cm-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999998; opacity: 0; transition: opacity 0.3s; }',
      '.cm-overlay.cm-visible { opacity: 1; }',
      '',
      '/* Banner */',
      '.cm-banner { position: fixed; bottom: 0; left: 0; right: 0; z-index: 999999; background: #fff; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); padding: 24px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; color: #333; transform: translateY(100%); transition: transform 0.4s ease; }',
      '.cm-banner.cm-visible { transform: translateY(0); }',
      '.cm-banner-inner { max-width: 1200px; margin: 0 auto; }',
      '.cm-banner-text { margin-bottom: 16px; line-height: 1.5; }',
      '.cm-banner-text a { color: #0066cc; }',
      '.cm-banner-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }',
      '.cm-btn { padding: 10px 24px; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.2s, transform 0.1s; }',
      '.cm-btn:active { transform: scale(0.97); }',
      '.cm-btn-primary { background: #0066cc; color: #fff; }',
      '.cm-btn-primary:hover { background: #0052a3; }',
      '.cm-btn-secondary { background: #e8e8e8; color: #333; }',
      '.cm-btn-secondary:hover { background: #d0d0d0; }',
      '.cm-btn-link { background: none; color: #0066cc; padding: 10px 12px; text-decoration: underline; }',
      '',
      '/* Panel de configuracion */',
      '.cm-panel { position: fixed; top: 0; right: 0; width: 520px; max-width: 100%; height: 100%; z-index: 999999; background: #fff; box-shadow: -4px 0 20px rgba(0,0,0,0.2); transform: translateX(100%); transition: transform 0.3s ease; overflow-y: auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; color: #333; }',
      '.cm-panel.cm-visible { transform: translateX(0); }',
      '.cm-panel-header { padding: 24px; border-bottom: 1px solid #e5e5e5; display: flex; justify-content: space-between; align-items: center; }',
      '.cm-panel-title { font-size: 18px; font-weight: 600; margin: 0; }',
      '.cm-panel-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 0; line-height: 1; }',
      '.cm-panel-body { padding: 24px; }',
      '.cm-panel-footer { padding: 24px; border-top: 1px solid #e5e5e5; display: flex; gap: 12px; justify-content: flex-end; }',
      '',
      '/* Categorias */',
      '.cm-category { border: 1px solid #e5e5e5; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }',
      '.cm-category-header { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #fafafa; cursor: pointer; }',
      '.cm-category-header:hover { background: #f0f0f0; }',
      '.cm-category-info { flex: 1; }',
      '.cm-category-name { font-weight: 600; font-size: 15px; }',
      '.cm-category-count { font-size: 12px; color: #888; margin-left: 8px; }',
      '.cm-category-desc { font-size: 13px; color: #666; margin-top: 4px; }',
      '',
      '/* Toggle switch */',
      '.cm-toggle { position: relative; width: 48px; height: 26px; flex-shrink: 0; margin-left: 16px; }',
      '.cm-toggle input { opacity: 0; width: 0; height: 0; }',
      '.cm-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 26px; transition: background 0.3s; }',
      '.cm-toggle-slider:before { content: ""; position: absolute; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: transform 0.3s; }',
      '.cm-toggle input:checked + .cm-toggle-slider { background: #0066cc; }',
      '.cm-toggle input:checked + .cm-toggle-slider:before { transform: translateX(22px); }',
      '.cm-toggle input:disabled + .cm-toggle-slider { opacity: 0.6; cursor: not-allowed; }',
      '',
      '/* Detalles de plugins */',
      '.cm-category-details { display: none; border-top: 1px solid #e5e5e5; }',
      '.cm-category.cm-expanded .cm-category-details { display: block; }',
      '.cm-category-arrow { margin-left: 8px; transition: transform 0.2s; font-size: 12px; color: #888; }',
      '.cm-expanded .cm-category-arrow { transform: rotate(90deg); }',
      '',
      '.cm-plugin { padding: 16px; border-bottom: 1px solid #f0f0f0; }',
      '.cm-plugin:last-child { border-bottom: none; }',
      '.cm-plugin-name { font-weight: 600; font-size: 14px; }',
      '.cm-plugin-vendor { font-size: 12px; color: #888; margin-left: 8px; }',
      '.cm-plugin-desc { font-size: 13px; color: #666; margin-top: 4px; }',
      '.cm-plugin-privacy { font-size: 12px; margin-top: 4px; }',
      '.cm-plugin-privacy a { color: #0066cc; }',
      '',
      '.cm-cookie-table { width: 100%; margin-top: 12px; border-collapse: collapse; font-size: 12px; }',
      '.cm-cookie-table th { text-align: left; padding: 6px 8px; background: #f5f5f5; color: #666; font-weight: 600; border-bottom: 1px solid #e5e5e5; }',
      '.cm-cookie-table td { padding: 6px 8px; border-bottom: 1px solid #f0f0f0; color: #555; }',
      '',
      '.cm-script-list { margin-top: 8px; padding: 0; list-style: none; font-size: 12px; }',
      '.cm-script-list li { padding: 3px 0; color: #888; word-break: break-all; }',
      '.cm-script-list li:before { content: "JS: "; font-weight: 600; color: #666; }',
      '',
      '.cm-no-plugins { padding: 16px; font-size: 13px; color: #888; font-style: italic; }',
      '',
      '/* Status badge */',
      '.cm-status { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }',
      '.cm-status-active { background: #e6f4ea; color: #1e7e34; }',
      '.cm-status-inactive { background: #fce8e6; color: #c5221f; }',
      '',
      '/* Responsive */',
      '@media (max-width: 600px) {',
      '  .cm-panel { width: 100%; }',
      '  .cm-banner-actions { flex-direction: column; }',
      '  .cm-btn { width: 100%; text-align: center; }',
      '}'
    ].join('\n');

    var style = document.createElement('style');
    style.id = 'consent-manager-styles';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function buildBanner() {
    var privacyLink = _config.privacyUrl
      ? ' <a href="' + _config.privacyUrl + '">Politica de privacidad</a>.'
      : '';

    var bannerHtml = [
      '<div class="cm-banner-inner">',
      '  <div class="cm-banner-text">',
      '    ' + (_config.bannerText || 'Este sitio utiliza cookies propias y de terceros. Puedes aceptar todas, solo las obligatorias, o configurar tus preferencias.') + privacyLink,
      '  </div>',
      '  <div class="cm-banner-actions">',
      '    <button class="cm-btn cm-btn-primary" data-cm-action="accept-all">Aceptar todas</button>',
      '    <button class="cm-btn cm-btn-secondary" data-cm-action="accept-necessary">Solo obligatorias</button>',
      '    <button class="cm-btn cm-btn-link" data-cm-action="show-settings">Configurar</button>',
      '  </div>',
      '</div>'
    ].join('\n');

    _bannerElement = document.createElement('div');
    _bannerElement.className = 'cm-banner';
    _bannerElement.setAttribute('role', 'dialog');
    _bannerElement.setAttribute('aria-label', 'Configuracion de cookies');
    _bannerElement.innerHTML = bannerHtml;

    document.body.appendChild(_bannerElement);

    // Eventos
    _bannerElement.querySelector('[data-cm-action="accept-all"]').addEventListener('click', function() {
      ConsentManager.acceptAll();
    });
    _bannerElement.querySelector('[data-cm-action="accept-necessary"]').addEventListener('click', function() {
      ConsentManager.acceptNecessaryOnly();
    });
    _bannerElement.querySelector('[data-cm-action="show-settings"]').addEventListener('click', function() {
      ConsentManager.showSettings();
    });

    // Mostrar con animacion
    requestAnimationFrame(function() {
      _bannerElement.classList.add('cm-visible');
    });
  }

  function buildSettingsPanel() {
    var registry = getPluginRegistry();

    var categoriesHtml = '';
    for (var catId in CATEGORIES) {
      var cat = CATEGORIES[catId];
      var plugins = registry[catId].plugins;
      var pluginCount = plugins.length;
      var isRequired = cat.required;
      var isChecked = isRequired || hasConsent(catId);

      categoriesHtml += '<div class="cm-category" data-cm-category="' + catId + '">';
      categoriesHtml += '  <div class="cm-category-header">';
      categoriesHtml += '    <div class="cm-category-info">';
      categoriesHtml += '      <span class="cm-category-name">' + cat.name + '</span>';
      categoriesHtml += '      <span class="cm-category-count">(' + pluginCount + ' plugin' + (pluginCount !== 1 ? 's' : '') + ')</span>';
      categoriesHtml += '      <div class="cm-category-desc">' + cat.description + '</div>';
      categoriesHtml += '    </div>';
      categoriesHtml += '    <label class="cm-toggle">';
      categoriesHtml += '      <input type="checkbox" data-cm-cat="' + catId + '"' +
                        (isChecked ? ' checked' : '') + (isRequired ? ' disabled' : '') + '>';
      categoriesHtml += '      <span class="cm-toggle-slider"></span>';
      categoriesHtml += '    </label>';
      categoriesHtml += '    <span class="cm-category-arrow">&#9654;</span>';
      categoriesHtml += '  </div>';
      categoriesHtml += '  <div class="cm-category-details">';

      if (plugins.length === 0) {
        categoriesHtml += '    <div class="cm-no-plugins">No hay plugins registrados en esta categoria.</div>';
      } else {
        for (var p = 0; p < plugins.length; p++) {
          var plugin = plugins[p];
          categoriesHtml += '<div class="cm-plugin">';
          categoriesHtml += '  <div>';
          categoriesHtml += '    <span class="cm-plugin-name">' + plugin.name + '</span>';
          if (plugin.vendor) {
            categoriesHtml += '    <span class="cm-plugin-vendor">por ' + plugin.vendor + '</span>';
          }
          categoriesHtml += '    <span class="cm-status ' + (plugin.activated ? 'cm-status-active' : 'cm-status-inactive') + '">';
          categoriesHtml += plugin.activated ? 'Activo' : 'Inactivo';
          categoriesHtml += '    </span>';
          categoriesHtml += '  </div>';
          if (plugin.description) {
            categoriesHtml += '  <div class="cm-plugin-desc">' + plugin.description + '</div>';
          }
          if (plugin.privacyUrl) {
            categoriesHtml += '  <div class="cm-plugin-privacy"><a href="' + plugin.privacyUrl + '" target="_blank" rel="noopener">Politica de privacidad del proveedor</a></div>';
          }

          // Tabla de cookies
          if (plugin.cookies.length > 0) {
            categoriesHtml += '  <table class="cm-cookie-table">';
            categoriesHtml += '    <thead><tr><th>Cookie</th><th>Duracion</th><th>Descripcion</th></tr></thead>';
            categoriesHtml += '    <tbody>';
            for (var c = 0; c < plugin.cookies.length; c++) {
              var cookie = plugin.cookies[c];
              categoriesHtml += '    <tr>';
              categoriesHtml += '      <td><code>' + cookie.name + '</code></td>';
              categoriesHtml += '      <td>' + cookie.duration + '</td>';
              categoriesHtml += '      <td>' + cookie.description + '</td>';
              categoriesHtml += '    </tr>';
            }
            categoriesHtml += '    </tbody></table>';
          }

          // Lista de scripts
          if (plugin.scripts.length > 0) {
            categoriesHtml += '  <ul class="cm-script-list">';
            for (var s = 0; s < plugin.scripts.length; s++) {
              categoriesHtml += '    <li>' + plugin.scripts[s] + '</li>';
            }
            categoriesHtml += '  </ul>';
          }

          categoriesHtml += '</div>'; // .cm-plugin
        }
      }

      categoriesHtml += '  </div>'; // .cm-category-details
      categoriesHtml += '</div>'; // .cm-category
    }

    var panelHtml = [
      '<div class="cm-panel-header">',
      '  <h2 class="cm-panel-title">Configuracion de cookies</h2>',
      '  <button class="cm-panel-close" data-cm-action="close-panel" aria-label="Cerrar">&times;</button>',
      '</div>',
      '<div class="cm-panel-body">',
      '  <p style="margin-top:0;line-height:1.5;">Selecciona que categorias de cookies deseas aceptar. Puedes desplegar cada categoria para ver los plugins, sus cookies y los scripts que cargan.</p>',
      categoriesHtml,
      '</div>',
      '<div class="cm-panel-footer">',
      '  <button class="cm-btn cm-btn-secondary" data-cm-action="accept-necessary">Solo obligatorias</button>',
      '  <button class="cm-btn cm-btn-primary" data-cm-action="save-settings">Guardar preferencias</button>',
      '</div>'
    ].join('\n');

    // Overlay
    var overlay = document.createElement('div');
    overlay.className = 'cm-overlay';
    overlay.addEventListener('click', function() {
      ConsentManager.hideSettings();
    });
    document.body.appendChild(overlay);

    _panelElement = document.createElement('div');
    _panelElement.className = 'cm-panel';
    _panelElement.setAttribute('role', 'dialog');
    _panelElement.setAttribute('aria-label', 'Configuracion detallada de cookies');
    _panelElement.innerHTML = panelHtml;
    document.body.appendChild(_panelElement);

    // Eventos del panel
    _panelElement.querySelector('[data-cm-action="close-panel"]').addEventListener('click', function() {
      ConsentManager.hideSettings();
    });
    _panelElement.querySelector('[data-cm-action="accept-necessary"]').addEventListener('click', function() {
      ConsentManager.acceptNecessaryOnly();
      ConsentManager.hideSettings();
    });
    _panelElement.querySelector('[data-cm-action="save-settings"]').addEventListener('click', function() {
      var categories = {};
      var checkboxes = _panelElement.querySelectorAll('input[data-cm-cat]');
      for (var i = 0; i < checkboxes.length; i++) {
        categories[checkboxes[i].getAttribute('data-cm-cat')] = checkboxes[i].checked;
      }
      var oldCategories = _consent ? _consent.categories : null;
      saveConsent(categories);
      applyConsent(categories, oldCategories);
      ConsentManager.hideSettings();
      hideBanner();
    });

    // Toggle expandir/colapsar categorias
    var headers = _panelElement.querySelectorAll('.cm-category-header');
    for (var h = 0; h < headers.length; h++) {
      headers[h].addEventListener('click', function(e) {
        // No colapsar si se hace click en el toggle
        if (e.target.closest('.cm-toggle')) return;
        this.parentElement.classList.toggle('cm-expanded');
      });
    }

    // Prevenir propagacion del toggle
    var toggles = _panelElement.querySelectorAll('.cm-toggle');
    for (var t = 0; t < toggles.length; t++) {
      toggles[t].addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }

    // Mostrar con animacion
    requestAnimationFrame(function() {
      overlay.classList.add('cm-visible');
      _panelElement.classList.add('cm-visible');
    });
  }

  function hideBanner() {
    if (_bannerElement) {
      _bannerElement.classList.remove('cm-visible');
      setTimeout(function() {
        if (_bannerElement && _bannerElement.parentNode) {
          _bannerElement.parentNode.removeChild(_bannerElement);
          _bannerElement = null;
        }
      }, 400);
    }
  }

  function destroySettingsPanel() {
    var overlay = document.querySelector('.cm-overlay');
    if (overlay) {
      overlay.classList.remove('cm-visible');
      setTimeout(function() {
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
      }, 300);
    }
    if (_panelElement) {
      _panelElement.classList.remove('cm-visible');
      setTimeout(function() {
        if (_panelElement && _panelElement.parentNode) {
          _panelElement.parentNode.removeChild(_panelElement);
          _panelElement = null;
        }
      }, 300);
    }
  }

  // =========================================================================
  // API PUBLICA
  // =========================================================================

  var ConsentManager = {

    /**
     * Inicializa el Consent Manager.
     * Debe llamarse una vez al cargar la pagina.
     *
     * @param {Object} config
     * @param {string} [config.bannerText] - Texto personalizado del banner
     * @param {string} [config.privacyUrl] - URL de la politica de privacidad
     * @param {Object} [config.categories] - Categorias personalizadas (se fusionan con las por defecto)
     * @param {boolean} [config.autoShow=true] - Mostrar banner automaticamente si no hay consentimiento
     */
    init: function(config) {
      if (_initialized) {
        console.warn('[ConsentManager] Ya esta inicializado');
        return;
      }

      _config = config || {};
      _initialized = true;

      // Fusionar categorias personalizadas
      if (_config.categories) {
        for (var catId in _config.categories) {
          if (CATEGORIES[catId]) {
            for (var key in _config.categories[catId]) {
              CATEGORIES[catId][key] = _config.categories[catId][key];
            }
          } else {
            CATEGORIES[catId] = _config.categories[catId];
            CATEGORIES[catId].id = catId;
          }
        }
      }

      injectStyles();

      // Cargar consentimiento previo
      _consent = loadConsent();

      if (_consent) {
        // Hay consentimiento guardado: aplicar
        applyConsent(_consent.categories, null);
      } else if (_config.autoShow !== false) {
        // No hay consentimiento: mostrar banner
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', buildBanner);
        } else {
          buildBanner();
        }
      }
    },

    /**
     * Registra un plugin y sus requisitos de cookies/scripts.
     *
     * OBLIGATORIO para que un plugin pueda usar cookies o cargar scripts externos.
     *
     * @param {Object} opts
     * @param {string} opts.pluginId    - Identificador unico del plugin
     * @param {string} opts.name        - Nombre legible del plugin
     * @param {string} opts.category    - Categoria: 'necessary' | 'functional' | 'analytics' | 'marketing'
     * @param {string} [opts.description] - Descripcion de para que sirve
     * @param {string} [opts.vendor]    - Nombre del proveedor/empresa
     * @param {string} [opts.privacyUrl] - URL de la politica de privacidad del proveedor
     * @param {Array}  [opts.cookies]   - Cookies que usa el plugin
     * @param {string} opts.cookies[].name       - Nombre de la cookie
     * @param {string} [opts.cookies[].duration]  - Duracion (ej: "2 anos", "Sesion", "30 dias")
     * @param {string} [opts.cookies[].description] - Para que sirve esta cookie
     * @param {string} [opts.cookies[].type]      - Tipo: 'cookie' | 'localStorage' | 'sessionStorage'
     * @param {Array}  [opts.cookies[].paths]     - Paths donde se establece la cookie
     * @param {Array}  [opts.scripts]   - URLs de scripts externos que necesita cargar
     * @param {Function} [opts.onAccept] - Se ejecuta cuando se acepta la categoria
     * @param {Function} [opts.onReject] - Se ejecuta cuando se revoca la categoria
     */
    register: function(opts) {
      validatePluginRegistration(opts);

      if (_plugins[opts.pluginId]) {
        console.warn('[ConsentManager] Plugin ya registrado: ' + opts.pluginId + '. Se sobreescribe.');
      }

      _plugins[opts.pluginId] = {
        pluginId: opts.pluginId,
        name: opts.name,
        category: opts.category,
        description: opts.description || '',
        vendor: opts.vendor || '',
        privacyUrl: opts.privacyUrl || '',
        cookies: opts.cookies || [],
        scripts: opts.scripts || [],
        onAccept: opts.onAccept || null,
        onReject: opts.onReject || null,
        _activated: false
      };

      // Si ya hay consentimiento para esta categoria, activar inmediatamente
      if (_initialized && hasConsent(opts.category)) {
        activatePluginsForCategory(opts.category);
      }
    },

    /**
     * Desregistra un plugin.
     *
     * @param {string} pluginId
     */
    unregister: function(pluginId) {
      if (_plugins[pluginId]) {
        var plugin = _plugins[pluginId];
        if (plugin._activated && plugin.onReject) {
          plugin.onReject();
        }
        delete _plugins[pluginId];
      }
    },

    /**
     * Acepta todas las categorias de cookies.
     */
    acceptAll: function() {
      var categories = {};
      for (var cat in CATEGORIES) {
        categories[cat] = true;
      }
      var oldCategories = _consent ? _consent.categories : null;
      saveConsent(categories);
      applyConsent(categories, oldCategories);
      hideBanner();
    },

    /**
     * Acepta solo las cookies obligatorias.
     */
    acceptNecessaryOnly: function() {
      var categories = {};
      for (var cat in CATEGORIES) {
        categories[cat] = CATEGORIES[cat].required === true;
      }
      var oldCategories = _consent ? _consent.categories : null;
      saveConsent(categories);
      applyConsent(categories, oldCategories);
      hideBanner();
    },

    /**
     * Actualiza el consentimiento para categorias especificas.
     *
     * @param {Object} categories - { analytics: true, marketing: false, ... }
     */
    updateConsent: function(categories) {
      categories.necessary = true; // Siempre obligatorio
      var oldCategories = _consent ? _consent.categories : null;
      saveConsent(categories);
      applyConsent(categories, oldCategories);
    },

    /**
     * Muestra el panel de configuracion detallada.
     */
    showSettings: function() {
      if (_panelElement) return;
      buildSettingsPanel();
    },

    /**
     * Oculta el panel de configuracion.
     */
    hideSettings: function() {
      destroySettingsPanel();
    },

    /**
     * Consulta si una categoria tiene consentimiento.
     *
     * @param {string} category
     * @returns {boolean}
     */
    hasConsent: function(category) {
      return hasConsent(category);
    },

    /**
     * Devuelve el estado completo del consentimiento.
     *
     * @returns {Object|null} - { necessary: true, functional: false, ... } o null si no hay consentimiento
     */
    getConsentState: function() {
      return getConsentState();
    },

    /**
     * Devuelve el registro completo de plugins agrupado por categoria.
     * Util para el panel de transparencia o para debugging.
     *
     * @returns {Object}
     */
    getPluginRegistry: function() {
      return getPluginRegistry();
    },

    /**
     * Devuelve informacion de un plugin especifico.
     *
     * @param {string} pluginId
     * @returns {Object|null}
     */
    getPluginInfo: function(pluginId) {
      var plugin = _plugins[pluginId];
      if (!plugin) return null;
      return {
        pluginId: plugin.pluginId,
        name: plugin.name,
        category: plugin.category,
        description: plugin.description,
        vendor: plugin.vendor,
        privacyUrl: plugin.privacyUrl,
        cookies: plugin.cookies,
        scripts: plugin.scripts,
        activated: plugin._activated
      };
    },

    /**
     * Registra un listener que se ejecuta cuando cambia el consentimiento.
     *
     * @param {Function} callback - Recibe { necessary: true, analytics: false, ... }
     * @returns {Function} - Funcion para desregistrar el listener
     */
    onChange: function(callback) {
      _listeners.push(callback);
      return function() {
        var idx = _listeners.indexOf(callback);
        if (idx > -1) _listeners.splice(idx, 1);
      };
    },

    /**
     * Revoca todo el consentimiento y elimina cookies de plugins.
     */
    revokeAll: function() {
      var oldCategories = _consent ? _consent.categories : null;
      var categories = {};
      for (var cat in CATEGORIES) {
        categories[cat] = CATEGORIES[cat].required === true;
        if (!CATEGORIES[cat].required) {
          cleanCookiesForCategory(cat);
        }
      }
      saveConsent(categories);
      applyConsent(categories, oldCategories);
    },

    /**
     * Resetea completamente el Consent Manager.
     * Elimina la cookie de consentimiento y muestra el banner.
     */
    reset: function() {
      deleteCookie(CONSENT_COOKIE_NAME);
      _consent = null;
      for (var pluginId in _plugins) {
        _plugins[pluginId]._activated = false;
      }
      destroySettingsPanel();
      hideBanner();
      // Mostrar banner de nuevo
      setTimeout(buildBanner, 100);
    },

    /**
     * Constantes utiles para los desarrolladores de plugins.
     */
    CATEGORIES: CATEGORIES,
    VERSION: '1.0.0'
  };

  // Exponer globalmente
  global.ConsentManager = ConsentManager;

})(typeof window !== 'undefined' ? window : this);
