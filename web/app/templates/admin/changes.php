<?php
/*
 * Versionshinweise (SPEC §7.10): Changelog aus CHANGELOG, darunter die
 * Software-Stückliste (SBOM) aufklappbar. Feste Texte.
 */
$sbomTable = function (array $components): string {
    ob_start(); ?>
    <div class="table-responsive">
      <table class="table table-sm small align-top mb-0">
        <thead>
          <tr><th>Komponente</th><th>Version</th><th>Lizenz</th><th>Zweck</th></tr>
        </thead>
        <tbody>
          <?php foreach ($components as $component): ?>
            <tr>
              <td class="fw-semibold">
                <a href="<?= e($component['source']) ?>" rel="noopener noreferrer"><?= e($component['name']) ?></a>
              </td>
              <td class="tabular-nums"><?= e($component['version']) ?></td>
              <td><?= e($component['license']) ?></td>
              <td><?= e($component['purpose']) ?></td>
            </tr>
            <tr class="sbom-files">
              <td colspan="4">
                <?php foreach ($component['files'] as $file): ?>
                  <div class="text-body-secondary">
                    <code><?= e($file['path']) ?></code>
                    <span class="sbom-hash">SHA-256 <?= e($file['sha256'] !== '' ? $file['sha256'] : 'Datei fehlt') ?></span>
                  </div>
                <?php endforeach ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="h3 mb-1">Versionshinweise</h1>
<p class="text-body-secondary mb-4">
  Aktuelle Version <?= e(app_version()) ?> vom <?= e(format_date(CHANGELOG[0]['date'])) ?>
</p>

<div class="vstack gap-4 changelog">
  <?php foreach (CHANGELOG as $i => $release): ?>
    <section>
      <h2 class="h5 mb-2">
        Version <?= e($release['version']) ?>
        <span class="fw-normal text-body-secondary">· <?= e(format_date($release['date'])) ?></span>
        <?php if ($i === 0): ?><span class="badge text-bg-success ms-1 align-middle">aktuell</span><?php endif ?>
      </h2>
      <?php foreach (CHANGELOG_SECTIONS as $key => $label): ?>
        <?php if (!empty($release[$key])): ?>
          <h3 class="h6 fw-semibold mb-1 mt-2"><?= e($label) ?></h3>
          <ul class="mb-0">
            <?php foreach ($release[$key] as $item): ?>
              <li><?= e($item) ?></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      <?php endforeach ?>
    </section>
  <?php endforeach ?>
</div>

<details class="sbom border rounded mt-5">
  <summary class="px-3 py-2 fw-semibold">Software-Stückliste (SBOM)</summary>
  <div class="px-3 pb-3">
    <p class="small text-body-secondary">
      Fremdkomponenten der App. Versionen und Prüfsummen werden direkt aus den Dateien auf dem Server gelesen.
      <a href="<?= e(url('admin/changes.php?download=sbom')) ?>">Als CycloneDX-Datei (JSON) herunterladen</a>
    </p>

    <h2 class="h6">Auf dem Server</h2>
    <?= $sbomTable($components) ?>

    <h2 class="h6 mt-4">Laufzeitumgebung</h2>
    <dl class="row small mb-0">
      <?php foreach ($runtime as $name => $version): ?>
        <dt class="col-sm-3 fw-normal text-body-secondary"><?= e($name) ?></dt>
        <dd class="col-sm-9 mb-1 tabular-nums"><?= e($version) ?></dd>
      <?php endforeach ?>
    </dl>

    <h2 class="h6 mt-4">Nur für die Entwicklung <span class="fw-normal text-body-secondary">(nicht auf dem Server)</span></h2>
    <?= $sbomTable(SBOM_DEV_TOOLS) ?>
  </div>
</details>
