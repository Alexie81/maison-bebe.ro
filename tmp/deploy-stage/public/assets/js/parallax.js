(() => {
  'use strict';
  const hero = document.querySelector('[data-parallax-hero]');
  if (!hero || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const mobile = matchMedia('(max-width: 560px)');
  let queued = false;
  const render = () => {
    queued = false;
    if (!mobile.matches) {
      hero.style.removeProperty('--hero-parallax');
      return;
    }
    const rect = hero.getBoundingClientRect();
    const progress = Math.max(0, Math.min(hero.offsetHeight, -rect.top));
    hero.style.setProperty('--hero-parallax', `${Math.min(32, progress * 0.12).toFixed(1)}px`);
  };
  const request = () => {
    if (!queued) {
      queued = true;
      requestAnimationFrame(render);
    }
  };
  addEventListener('scroll', request, {passive: true});
  addEventListener('resize', request, {passive: true});
  render();
})();
