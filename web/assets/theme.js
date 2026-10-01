// Farbschema: automatisch nach Systemeinstellung oder per Umschalter
// Hell/Dunkel (Bootstrap data-bs-theme). Wird im <head> geladen, damit die
// Seite nicht erst hell aufblitzt. Die Wahl liegt nur im Browser
// (localStorage), ohne Wahl gilt „Automatisch“.

(() => {
  const KEY = 'theme';
  const LABELS = { auto: 'Automatisch', light: 'Hell', dark: 'Dunkel' };
  const media = window.matchMedia('(prefers-color-scheme: dark)');

  const choice = () => {
    let stored = null;
    try {
      stored = localStorage.getItem(KEY);
    } catch {
      // localStorage gesperrt (z. B. privater Modus): automatisch
    }
    return stored === 'light' || stored === 'dark' ? stored : 'auto';
  };

  const apply = () => {
    const current = choice();
    const theme = current === 'auto' ? (media.matches ? 'dark' : 'light') : current;
    document.documentElement.setAttribute('data-bs-theme', theme);
  };

  const updateToggles = () => {
    const current = choice();
    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
      toggle.hidden = false;
      const button = toggle.querySelector('[data-theme-current]');
      button.querySelectorAll('[data-theme-icon]').forEach((icon) => {
        // SVG-Elemente haben keine .hidden-Eigenschaft, daher das Attribut
        icon.toggleAttribute('hidden', icon.dataset.themeIcon !== current);
      });
      button.setAttribute('aria-label', `Farbschema: ${LABELS[current]}`);
      button.title = `Farbschema: ${LABELS[current]}`;
      toggle.querySelectorAll('[data-theme-value]').forEach((item) => {
        const active = item.dataset.themeValue === current;
        item.classList.toggle('active', active);
        item.setAttribute('aria-pressed', String(active));
      });
    });
  };

  apply();
  media.addEventListener('change', apply);
  document.addEventListener('DOMContentLoaded', updateToggles);

  document.addEventListener('click', (event) => {
    const item = event.target.closest('[data-theme-value]');
    if (!item) {
      return;
    }
    const value = item.dataset.themeValue;
    try {
      if (value === 'auto') {
        localStorage.removeItem(KEY);
      } else {
        localStorage.setItem(KEY, value);
      }
    } catch {
      // Wahl gilt dann nur bis zum Neuladen
      document.documentElement.setAttribute('data-bs-theme', value === 'auto' ? (media.matches ? 'dark' : 'light') : value);
      return;
    }
    apply();
    updateToggles();
  });
})();
