(function () {
  'use strict';

  var tawkLauncherObserver = null;
  var tawkLauncherFixQueued = false;

  function watchMobileTawkLauncher(container) {
    if (tawkLauncherObserver || !window.MutationObserver) return;

    tawkLauncherObserver = new MutationObserver(function () {
      if (tawkLauncherFixQueued) return;
      tawkLauncherFixQueued = true;

      (window.requestAnimationFrame || window.setTimeout)(function () {
        tawkLauncherFixQueued = false;
        fixMobileTawkLauncher();
      });
    });
    tawkLauncherObserver.observe(container, {
      attributes: true,
      attributeFilter: ['style'],
      childList: true,
      subtree: true
    });
  }

  function fixMobileTawkLauncher() {
    if (!window.matchMedia || !window.matchMedia('(max-width: 767px)').matches) return;

    var bubble = document.getElementById('chat-bubble');
    if (!bubble || !bubble.parentElement) return;

    watchMobileTawkLauncher(bubble.parentElement);

    Array.prototype.forEach.call(bubble.parentElement.querySelectorAll('iframe'), function (frame) {
      var computed = window.getComputedStyle(frame);
      var rect = frame.getBoundingClientRect();
      if (computed.display === 'none' || rect.width <= 0 || rect.height <= 0 || rect.width >= 120 || rect.height >= 120) return;

      var isTooSmall = rect.width < 44 || rect.height < 44;
      var isTooCloseToEdge = rect.left < 8 || window.innerHeight - rect.bottom < 8;
      var isOnRight = rect.left > window.innerWidth / 2;
      if (!isTooSmall && !isTooCloseToEdge && !isOnRight) return;

      if (isTooSmall) {
        frame.style.setProperty('width', '60px', 'important');
        frame.style.setProperty('min-width', '60px', 'important');
        frame.style.setProperty('max-width', '60px', 'important');
        frame.style.setProperty('height', '60px', 'important');
        frame.style.setProperty('min-height', '60px', 'important');
        frame.style.setProperty('max-height', '60px', 'important');
      }

      frame.style.setProperty('left', 'calc(16px + env(safe-area-inset-left))', 'important');
      frame.style.setProperty('right', 'auto', 'important');
      frame.style.setProperty('top', 'auto', 'important');
      frame.style.setProperty('bottom', 'calc(16px + env(safe-area-inset-bottom))', 'important');
    });
  }

  function scheduleMobileTawkFix() {
    [0, 250, 1000, 2500, 5000].forEach(function (delay) {
      window.setTimeout(fixMobileTawkLauncher, delay);
    });
  }

  function loadMarketingServices() {
    if (window.gkMarketingLoaded) return;
    window.gkMarketingLoaded = true;

    if (typeof window.gkLoadMetaPixel === 'function') window.gkLoadMetaPixel();

    window.Tawk_API = window.Tawk_API || {};
    window.Tawk_API.customStyle = window.Tawk_API.customStyle || {};
    window.Tawk_API.customStyle.visibility = window.Tawk_API.customStyle.visibility || {};
    window.Tawk_API.customStyle.visibility.mobile = {
      position: 'bl',
      xOffset: 16,
      yOffset: 16
    };

    var previousTawkOnLoad = window.Tawk_API.onLoad;
    window.Tawk_API.onLoad = function () {
      if (typeof previousTawkOnLoad === 'function') previousTawkOnLoad();
      if (document.documentElement.classList.contains('side-filter-open') && typeof window.Tawk_API.hideWidget === 'function') {
        window.Tawk_API.hideWidget();
      } else {
        if (typeof window.Tawk_API.showWidget === 'function') window.Tawk_API.showWidget();
        scheduleMobileTawkFix();
      }
    };

    window.Tawk_LoadStart = new Date();
    var tawk = document.createElement('script');
    tawk.async = true;
    tawk.src = 'https://embed.tawk.to/66f6b827e5982d6c7bb5a510/1i8ps10ve';
    tawk.charset = 'UTF-8';
    tawk.setAttribute('crossorigin', '*');
    document.head.appendChild(tawk);
    scheduleMobileTawkFix();

    var badge = document.createElement('script');
    badge.async = true;
    badge.src = 'https://cpx.smind.hr/Log/LogData?data=' + encodeURIComponent(JSON.stringify({
      Key: 'Cro_2155', Size: '80', Type: 'badge', Version: 2, BadgeClassName: 'smdWrapperTag'
    }));
    document.head.appendChild(badge);

    var floating = document.createElement('script');
    floating.async = true;
    floating.id = '_cpxTag';
    floating.src = 'https://cpx.smind.hr/Log/LogData?data=' + encodeURIComponent(JSON.stringify({
      Key: 'Cro_2155', Type: 'floatingbar', Version: 2, BadgeClassName: 'smdWrapperTag', BadgeZoom: 1
    }));
    document.head.appendChild(floating);

    if (window.gkSmindOrder) {
      var order = document.createElement('script');
      order.async = true;
      order.src = 'https://cpx.smind.hr/Log/LogData?data=' + encodeURIComponent(JSON.stringify(window.gkSmindOrder));
      document.head.appendChild(order);
    }
  }

  function applyConsent() {
    var analytics = CookieConsent.acceptedCategory('analytics');
    var marketing = CookieConsent.acceptedCategory('marketing');
    if (analytics && typeof window.gkLoadAnalytics === 'function') window.gkLoadAnalytics();
    if (marketing) loadMarketingServices();
  }

  function boot() {
    if (!window.CookieConsent || window.gkCookieConsentStarted) return;
    window.gkCookieConsentStarted = true;
    CookieConsent.run({
      cookie: {name: 'cc_cookie', expiresAfterDays: 182, sameSite: 'Lax'},
      disablePageInteraction: true,
      guiOptions: {
        consentModal: {layout: 'box', position: 'middle center', equalWeightButtons: true, flipButtons: false},
        preferencesModal: {layout: 'box', position: 'middle center'}
      },
      categories: {
        necessary: {enabled: true, readOnly: true},
        analytics: {enabled: false, autoClear: {cookies: [{name: /^_ga/}, {name: '_gid'}, {name: '_gat'}]}},
        marketing: {enabled: false, autoClear: {cookies: [{name: /^_gcl/}, {name: '_fbp'}]}}
      },
      onFirstConsent: applyConsent,
      onConsent: applyConsent,
      onChange: function () {
        applyConsent();
        if ((window.gkAnalyticsLoaded && !CookieConsent.acceptedCategory('analytics')) || (window.gkMarketingLoaded && !CookieConsent.acceptedCategory('marketing'))) {
          window.location.reload();
        }
      },
      language: {
        default: document.documentElement.lang === 'en' ? 'en' : 'hr',
        translations: {
          hr: {
            consentModal: {
              title: 'Vaša privatnost, vaš izbor',
              description: 'Nužni kolačići omogućuju rad trgovine. Uz vaše dopuštenje koristimo analitiku za poboljšanje stranice te vanjske usluge za podršku i prikaz oznake povjerenja. <a href="pravila-privatnosti">Pravila privatnosti</a>.',
              acceptAllBtn: 'Prihvati sve', acceptNecessaryBtn: 'Samo nužni', showPreferencesBtn: 'Postavke'
            },
            preferencesModal: {
              title: 'Postavke kolačića', acceptAllBtn: 'Prihvati sve', acceptNecessaryBtn: 'Samo nužni', savePreferencesBtn: 'Spremi odabir', closeIconLabel: 'Zatvori',
              sections: [
                {title: 'O vašem izboru', description: 'Izbor možete promijeniti u bilo kojem trenutku poveznicom „Postavke kolačića” u podnožju.'},
                {title: 'Nužni kolačići', description: 'Omogućuju košaricu, prijavu, jezik, valutu, sigurnost i pamćenje vašeg izbora. Ne mogu se isključiti.', linkedCategory: 'necessary'},
                {title: 'Analitika', description: 'Pomaže nam razumjeti korištenje trgovine i poboljšati brzinu i sadržaj. Uključuje Google Analytics.', linkedCategory: 'analytics'},
                {title: 'Podrška i vanjske usluge', description: 'Omogućuje Tawk chat i Certified Shop/Smind oznaku povjerenja. Ove se usluge učitavaju tek nakon pristanka.', linkedCategory: 'marketing'}
              ]
            }
          },
          en: {
            consentModal: {title: 'Your privacy, your choice', description: 'Necessary cookies keep the shop working. With your permission we use analytics and third-party support services. <a href="pravila-privatnosti">Privacy policy</a>.', acceptAllBtn: 'Accept all', acceptNecessaryBtn: 'Necessary only', showPreferencesBtn: 'Settings'},
            preferencesModal: {title: 'Cookie settings', acceptAllBtn: 'Accept all', acceptNecessaryBtn: 'Necessary only', savePreferencesBtn: 'Save selection', closeIconLabel: 'Close', sections: [{title: 'Necessary cookies', description: 'Required for the cart, login, security and saving your choice.', linkedCategory: 'necessary'}, {title: 'Analytics', description: 'Helps us improve the shop.', linkedCategory: 'analytics'}, {title: 'Support and third-party services', description: 'Loads Tawk chat and the Certified Shop badge.', linkedCategory: 'marketing'}]}
          }
        }
      }
    });
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-cookie-consent-trigger]');
    if (!trigger) return;
    event.preventDefault();
    if (window.CookieConsent) CookieConsent.showPreferences();
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
}());
