<?php
/*
 * Feste Leiste oben auf den öffentlichen Seiten: nur ein Button rechts, zurück
 * zur Hauptseite des Veranstalters (Config home_url). Bleibt beim Scrollen
 * sichtbar; flach über die ganze Breite, mit feiner Linie unten (siehe
 * .public-bar in app.css).
 * referrerpolicy: Die URL (z. B. mit dem Team-Schlüssel) wird nicht an die
 * Hauptseite weitergegeben, auch nicht bei gleicher Domain.
 */
?>
<header class="public-bar">
  <div class="public-bar-inner">
    <a href="<?= e(home_url()) ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
       referrerpolicy="no-referrer">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/></svg>
      Zurück zur Hauptseite
    </a>
  </div>
</header>
