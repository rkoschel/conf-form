<?php
// Versionshinweise und Software-Stückliste (SBOM) für Admins (SPEC §7.10)
require __DIR__ . '/../app/bootstrap.php';
admin_init();

// SBOM als CycloneDX-Datei herunterladen
if (($_GET['download'] ?? '') === 'sbom') {
    header('Content-Type: application/vnd.cyclonedx+json; charset=utf-8');
    header('Content-Disposition: attachment; filename="conf-form-' . app_version() . '-sbom.cdx.json"');
    echo json_encode(sbom_cyclonedx(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

admin_render('changes', [
    'title' => 'Versionshinweise',
    'active' => 'admin/changes.php',
    'components' => sbom_components(),
    'runtime' => sbom_runtime(),
]);
