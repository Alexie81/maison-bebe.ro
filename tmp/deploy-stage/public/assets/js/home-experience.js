(() => {
  'use strict';

  const main = document.querySelector('main');
  if (!main) return;

  const hero = document.querySelector('[data-home-hero]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const mobilePerformanceMode = window.matchMedia('(max-width: 900px), (pointer: coarse)').matches;
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

    if (hero && !mobilePerformanceMode) {
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
    } else if (hero) {
      hero.style.setProperty('--hero-shift', '0px');
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

  const collectionCarousel = document.querySelector('[data-collection-carousel]');
  if (collectionCarousel) {
    const viewport = collectionCarousel.querySelector('[data-collection-viewport]');
    const rail = collectionCarousel.querySelector('.collection-rail');
    const slides = [...collectionCarousel.querySelectorAll('.collection-chip')];
    const previous = collectionCarousel.querySelector('[data-collection-prev]');
    const next = collectionCarousel.querySelector('[data-collection-next]');
    const mobileCarousel = window.matchMedia('(max-width: 760px)');
    let index = 0;
    let autoplay = 0;
    let pointerStartX = null;
    let pointerStartY = null;
    let carouselVisible = true;

    const stopAutoplay = () => {
      if (!autoplay) return;
      window.clearInterval(autoplay);
      autoplay = 0;
    };
    const renderCarousel = (animate = true) => {
      if (!mobileCarousel.matches || slides.length < 2) {
        rail.style.removeProperty('transform');
        rail.style.removeProperty('transition');
        slides.forEach(slide => {
          slide.removeAttribute('tabindex');
          slide.removeAttribute('aria-current');
        });
        return;
      }
      index = (index + slides.length) % slides.length;
      rail.style.transition = animate && !reducedMotion ? '' : 'none';
      rail.style.transform = `translate3d(${-index * 100}%,0,0)`;
      slides.forEach((slide, slideIndex) => {
        slide.tabIndex = slideIndex === index ? 0 : -1;
        if (slideIndex === index) slide.setAttribute('aria-current', 'true');
        else slide.removeAttribute('aria-current');
      });
    };
    const startAutoplay = () => {
      stopAutoplay();
      if (!mobileCarousel.matches || reducedMotion || slides.length < 2 || document.hidden || !carouselVisible) return;
      autoplay = window.setInterval(() => {
        index += 1;
        renderCarousel();
      }, 2800);
    };
    const moveCarousel = direction => {
      index += direction;
      renderCarousel();
      startAutoplay();
    };

    previous?.addEventListener('click', () => moveCarousel(-1));
    next?.addEventListener('click', () => moveCarousel(1));
    viewport?.addEventListener('pointerdown', event => {
      pointerStartX = event.clientX;
      pointerStartY = event.clientY;
      stopAutoplay();
    }, { passive: true });
    viewport?.addEventListener('pointerup', event => {
      if (pointerStartX === null || pointerStartY === null) return startAutoplay();
      const distanceX = event.clientX - pointerStartX;
      const distanceY = event.clientY - pointerStartY;
      pointerStartX = null;
      pointerStartY = null;
      if (Math.abs(distanceX) > 36 && Math.abs(distanceX) > Math.abs(distanceY)) {
        moveCarousel(distanceX < 0 ? 1 : -1);
        return;
      }
      startAutoplay();
    }, { passive: true });
    viewport?.addEventListener('pointercancel', () => {
      pointerStartX = null;
      pointerStartY = null;
      startAutoplay();
    }, { passive: true });
    collectionCarousel.addEventListener('mouseenter', stopAutoplay);
    collectionCarousel.addEventListener('mouseleave', startAutoplay);
    collectionCarousel.addEventListener('focusin', stopAutoplay);
    collectionCarousel.addEventListener('focusout', startAutoplay);
    document.addEventListener('visibilitychange', startAutoplay);
    if ('IntersectionObserver' in window) {
      const carouselObserver = new IntersectionObserver(entries => {
        carouselVisible = Boolean(entries[0]?.isIntersecting);
        if (carouselVisible) startAutoplay();
        else stopAutoplay();
      }, { rootMargin: '160px 0px', threshold: 0.01 });
      carouselObserver.observe(collectionCarousel);
    }
    mobileCarousel.addEventListener('change', () => {
      index = 0;
      renderCarousel(false);
      startAutoplay();
    });
    renderCarousel(false);
    startAutoplay();
  }
})();
