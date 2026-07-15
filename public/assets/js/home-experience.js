(() => {
  'use strict';

  const main = document.querySelector('main');
  if (!main) return;

  const hero = document.querySelector('[data-home-hero]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const homeTargets = hero ? [
    ...document.querySelectorAll('.home-collections .section-heading, .home-collections .collection-chip'),
    ...document.querySelectorAll('.home-story-heading, .home-story-chapter, .home-story-finale'),
    ...document.querySelectorAll('main > .section-space .section-heading, main > .section-space .product-card'),
    ...document.querySelectorAll('.home-gift-story > *, .atelier-preview .section-heading, .atelier-preview .article-card, .newsletter > *')
  ] : [];
  const genericTargets = hero ? [] : [
    ...main.querySelectorAll(':scope > .page-hero, :scope > .catalog-hero, :scope > .gift-hero, :scope > .atelier-hero, :scope > .product-layout, :scope > .catalog-layout, :scope > .section-space')
  ];
  const revealTargets = [...new Set([...homeTargets, ...genericTargets])];

  revealTargets.forEach((element, index) => {
    element.dataset.reveal = element.matches('.home-gift-story > :first-child') ? 'left' :
      element.matches('.home-gift-story > :last-child') ? 'right' : 'up';
    element.style.setProperty('--reveal-delay', String(Math.min(index % 5, 4) * 55) + 'ms');
  });

  if (!reducedMotion && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('storefront-motion');
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    revealTargets.forEach(element => revealObserver.observe(element));

    if (hero) {
      let frame = 0;
      const updateHero = () => {
        frame = 0;
        const rect = hero.getBoundingClientRect();
        if (rect.bottom <= 0 || rect.top >= window.innerHeight) return;
        const progress = Math.max(0, Math.min(hero.offsetHeight, -rect.top));
        const mobileFactor = window.innerWidth <= 900 ? 0.045 : 0.065;
        hero.style.setProperty('--hero-shift', Math.min(38, progress * mobileFactor).toFixed(2) + 'px');
      };
      const requestHeroFrame = () => {
        if (frame) return;
        frame = window.requestAnimationFrame(updateHero);
      };
      window.addEventListener('scroll', requestHeroFrame, { passive: true });
      window.addEventListener('resize', requestHeroFrame, { passive: true });
      updateHero();
    }
  } else {
    revealTargets.forEach(element => element.classList.add('is-visible'));
  }

  hero?.querySelector('.hero-scroll-cue')?.addEventListener('click', event => {
    const target = document.querySelector(event.currentTarget.getAttribute('href'));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
  });
})();
