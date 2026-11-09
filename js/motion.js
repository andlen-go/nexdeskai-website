(function () {
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-stuck', window.pageYOffset > 8);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduced || !('IntersectionObserver' in window)) { return; }

  var selector = [
    '.platform-copy', '.setup', '.stage > *', '.duo > *',
    '.integrations-card',
    '.features .kicker', '.features h2', '.cards > li',
    '.team-grid > *',
    '.rules .kicker', '.rules h2', '.rules-intro', '.instruction', '.flow', '.steps > li', '.matrix',
    '.pricing .kicker', '.pricing h2', '.pricing-intro', '.pilot-band', '.plans > *',
    '.cta h2', '.cta-cards > *',
    '.contact-copy', '.cf-card'
  ].join(',');

  var targets = Array.prototype.slice.call(document.querySelectorAll(selector));
  if (!targets.length) { return; }

  targets.forEach(function (el) {
    var parent = el.parentNode;
    el.__nxCount = parent.__nxCount || 0;
    parent.__nxCount = el.__nxCount + 1;
  });

  document.documentElement.classList.add('motion');

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) { return; }
      var el = entry.target;
      observer.unobserve(el);
      var delay = Math.min(el.__nxCount, 3) * 90;
      el.style.setProperty('--d', delay + 'ms');
      el.classList.add('is-in');
      window.setTimeout(function () {
        el.classList.remove('reveal', 'is-in');
        el.style.removeProperty('--d');
      }, 1600 + delay);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

  targets.forEach(function (el) {
    el.classList.add('reveal');
    observer.observe(el);
  });
}());
