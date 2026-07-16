(() => {
  'use strict';

  const timeline = document.querySelector('[data-story-timeline]');
  if (!timeline) return;

  const chapters = [...timeline.querySelectorAll('[data-story-chapter]')];
  const nav = [...timeline.querySelectorAll('[data-story-nav]')];
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const mobilePerformanceMode = window.matchMedia('(max-width: 900px), (pointer: coarse)').matches;
  let active = -1;
  let ticking = false;

  const activate = (index) => {
    if (index === active || index < 0) return;
    active = index;
    chapters.forEach((chapter, chapterIndex) => chapter.classList.toggle('is-active', chapterIndex === index));
    nav.forEach((item, itemIndex) => {
      item.classList.toggle('active', itemIndex === index);
      const link = item.querySelector('a');
      if (itemIndex === index) link?.setAttribute('aria-current', 'step');
      else link?.removeAttribute('aria-current');
    });
    timeline.style.setProperty('--story-progress', `${chapters.length > 1 ? (index / (chapters.length - 1)) * 100 : 100}%`);
  };

  const update = () => {
    ticking = false;
    const line = window.innerHeight * 0.5;
    let nearest = 0;
    let distance = Infinity;

    chapters.forEach((chapter, index) => {
      const rect = chapter.getBoundingClientRect();
      const center = rect.top + rect.height * 0.5;
      const current = Math.abs(center - line);
      if (current < distance) {
        distance = current;
        nearest = index;
      }

      const figure = chapter.querySelector('[data-story-parallax]');
      if (!figure || reduce) return;
      const travel = Math.max(-1, Math.min(1, (line - center) / (window.innerHeight + rect.height)));
      figure.style.setProperty('--story-image-shift', `${travel * (window.innerWidth <= 700 ? 28 : 54)}px`);
    });

    activate(nearest);
  };

  const request = () => {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(update);
  };

  nav.forEach((item) => item.querySelector('a')?.addEventListener('click', (event) => {
    const target = document.querySelector(event.currentTarget.getAttribute('href'));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
  }));

  if (mobilePerformanceMode && 'IntersectionObserver' in window) {
    chapters.forEach(chapter => chapter.querySelector('[data-story-parallax]')?.style.removeProperty('--story-image-shift'));
    const chapterObserver = new IntersectionObserver(entries => {
      const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (visible) activate(chapters.indexOf(visible.target));
    }, { rootMargin: '-30% 0px -38% 0px', threshold: [0.01, 0.25, 0.5] });
    chapters.forEach(chapter => chapterObserver.observe(chapter));
    activate(0);
  } else {
    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request);
    chapters[0]?.classList.add('is-active');
    update();
  }
})();
