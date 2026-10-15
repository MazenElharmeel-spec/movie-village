(() => {
  const updateControls = (track) => {
    const controls = document.querySelectorAll(`[data-target="${track.id}"]`);
    const max = track.scrollWidth - track.clientWidth;
    controls.forEach((button) => {
      if (button.dataset.scroll === 'left') button.disabled = track.scrollLeft <= 2;
      if (button.dataset.scroll === 'right') button.disabled = track.scrollLeft >= max - 2;
    });
  };

  document.querySelectorAll('.movie-row[id]').forEach((track) => {
    const refresh = () => updateControls(track);
    track.addEventListener('scroll', refresh, { passive: true });
    window.addEventListener('resize', refresh);
    refresh();
  });

  document.querySelectorAll('[data-scroll][data-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const track = document.getElementById(button.dataset.target);
      if (!track) return;
      const direction = button.dataset.scroll === 'left' ? -1 : 1;
      track.scrollBy({ left: direction * Math.max(track.clientWidth * 0.82, 260), behavior: 'smooth' });
    });
  });
})();
