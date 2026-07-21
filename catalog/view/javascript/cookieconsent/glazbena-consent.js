(function () {
  'use strict';

  function loadMarketingServices() {
    if (window.gkMarketingLoaded) return;
    window.gkMarketingLoaded = true;

    if (typeof window.gkLoadMetaPixel === 'function') window.gkLoadMetaPixel();

    window.Tawk_API = window.Tawk_API || {};

    function positionTawkWidget() {
      var isMobile = window.matchMedia ? window.matchMedia('(max-width: 767px)').matches : window.innerWidth < 768;
      var frames = document.querySelectorAll('iframe[title*="chat"], iframe[title*="Chat"], iframe[src*="tawk.to"]');

      Array.prototype.forEach.call(frames, function (frame) {
        if (!frame.hasAttribute('data-gk-chat-positioned')) {
          frame.setAttribute('data-gk-chat-positioned', '1');
          frame.setAttribute('data-gk-chat-left', frame.style.getPropertyValue('left'));
          frame.setAttribute('data-gk-chat-left-priority', frame.style.getPropertyPriority('left'));
          frame.setAttribute('data-gk-chat-right', frame.style.getPropertyValue('right'));
          frame.setAttribute('data-gk-chat-right-priority', frame.style.getPropertyPriority('right'));
        }

        if (isMobile) {
          if (
            frame.style.getPropertyValue('left') !== '16px' ||
            frame.style.getPropertyPriority('left') !== 'important' ||
            frame.style.getPropertyValue('right') !== 'auto' ||
            frame.style.getPropertyPriority('right') !== 'important'
          ) {
            frame.style.setProperty('left', '16px', 'important');
            frame.style.setProperty('right', 'auto', 'important');
          }
        } else {
          var left = frame.getAttribute('data-gk-chat-left');
          var right = frame.getAttribute('data-gk-chat-right');

          left ? frame.style.setProperty('left', left, frame.getAttribute('data-gk-chat-left-priority')) : frame.style.removeProperty('left');
          right ? frame.style.setProperty('right', right, frame.getAttribute('data-gk-chat-right-priority')) : frame.style.removeProperty('right');
        }
      });

      if (document.documentElement.classList.contains('side-filter-open') && typeof window.Tawk_API.hideWidget === 'function') {
        window.Tawk_API.hideWidget();
      }
    }

    var previousTawkOnLoad = window.Tawk_API.onLoad;
    window.Tawk_API.onLoad = function () {
      if (typeof previousTawkOnLoad === 'function') previousTawkOnLoad();
      positionTawkWidget();
      window.setTimeout(positionTawkWidget, 250);
    };

    if (window.MutationObserver && document.body) {
      var tawkObserver = new MutationObserver(function (mutations) {
        var hasNewFrame = mutations.some(function (mutation) {
          if (mutation.type === 'attributes') {
            var isMobile = window.matchMedia ? window.matchMedia('(max-width: 767px)').matches : window.innerWidth < 768;
            return isMobile && mutation.target.matches && mutation.target.matches('iframe[title*="chat"], iframe[title*="Chat"], iframe[src*="tawk.to"]');
          }

          return Array.prototype.some.call(mutation.addedNodes, function (node) {
            return node.nodeType === 1 && (node.tagName === 'IFRAME' || (node.querySelector && node.querySelector('iframe')));
          });
        });

        if (hasNewFrame) window.setTimeout(positionTawkWidget, 0);
      });

      tawkObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['style'],
        childList: true,
        subtree: true
      });
      window.addEventListener('resize', positionTawkWidget);
    }

    window.Tawk_LoadStart = new Date();
    var tawk = document.createElement('script');
    tawk.async = true;
    tawk.src = 'https://embed.tawk.to/66f6b827e5982d6c7bb5a510/1i8ps10ve';
    tawk.charset = 'UTF-8';
    tawk.setAttribute('crossorigin', '*');
    document.head.appendChild(tawk);

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
