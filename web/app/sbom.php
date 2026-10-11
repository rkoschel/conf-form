<?php
declare(strict_types=1);

/**
 * Software-Stückliste (SBOM, SPEC §7.10): eingebundene Fremdkomponenten.
 * Versionen und SHA-256-Prüfsummen der ausgelieferten Dateien werden zur
 * Laufzeit aus den Dateien gelesen, damit die Liste nach einem Update nicht
 * veraltet. Entwicklungswerkzeuge liegen nicht auf dem Server; ihre Daten
 * stehen hier fest und müssen zu tools/install.sh passen (Test prüft das).
 */

/** Werkzeuge nur für die Entwicklung (tools/install.sh), nicht deployt */
const SBOM_DEV_TOOLS = [
    [
        'name' => 'PHPUnit',
        'version' => '11.5.56',
        'license' => 'BSD-3-Clause',
        'purpose' => 'Tests',
        'source' => 'https://phar.phpunit.de/phpunit-11.5.56.phar',
        'files' => [['path' => 'tools/phpunit.phar', 'sha256' => '915fa161f496dc04a45cd6032855879bca0bab644048cd0516982dffe678e9f1']],
    ],
    [
        'name' => 'PHPStan',
        'version' => '2.2.16',
        'license' => 'MIT',
        'purpose' => 'Statische Code-Analyse',
        'source' => 'https://github.com/phpstan/phpstan/releases/download/2.2.16/phpstan.phar',
        'files' => [['path' => 'tools/phpstan.phar', 'sha256' => '1a2fb5460c142502d3cd06272529c18b19fda004ada974bd2581cb9fd6c0a53b']],
    ],
];

/**
 * Komponenten, die mit der App ausgeliefert werden.
 *
 * @return list<array{name: string, version: string, license: string, purpose: string, source: string,
 *     files: list<array{path: string, sha256: string}>}>
 */
function sbom_components(): array
{
    $web = dirname(APP_DIR);
    $file = fn (string $path): array => [
        'path' => $path,
        'sha256' => is_file($web . '/' . $path) ? (string) hash_file('sha256', $web . '/' . $path) : '',
    ];
    $bootstrapCss = 'assets/vendor/bootstrap/bootstrap.min.css';
    $phpMailer = 'app/lib/PHPMailer/PHPMailer.php';

    return [
        [
            'name' => 'Bootstrap',
            'version' => sbom_version_from_file($web . '/' . $bootstrapCss, '/Bootstrap\s+v(\d+\.\d+\.\d+)/'),
            'license' => 'MIT',
            'purpose' => 'Gestaltung und Bedienelemente (CSS, JavaScript), selbst gehostet',
            'source' => 'https://getbootstrap.com/',
            'files' => [$file($bootstrapCss), $file('assets/vendor/bootstrap/bootstrap.bundle.min.js')],
        ],
        [
            'name' => 'PHPMailer',
            'version' => sbom_version_from_file($web . '/' . $phpMailer, "/const VERSION = '([^']+)'/"),
            'license' => 'LGPL-2.1-only',
            'purpose' => 'Mailversand über SMTP',
            'source' => 'https://github.com/PHPMailer/PHPMailer',
            'files' => [$file($phpMailer), $file('app/lib/PHPMailer/SMTP.php'), $file('app/lib/PHPMailer/Exception.php')],
        ],
    ];
}

/** Erste Fundstelle von $pattern (Gruppe 1) in den ersten 64 KB der Datei, sonst „unbekannt“ */
function sbom_version_from_file(string $path, string $pattern): string
{
    $head = is_file($path) ? (string) file_get_contents($path, false, null, 0, 65536) : '';
    return preg_match($pattern, $head, $match) ? $match[1] : 'unbekannt';
}

/**
 * Laufzeitumgebung des Servers (Versionen live ermittelt).
 *
 * @return array<string, string> Bezeichnung → Version
 */
function sbom_runtime(): array
{
    return [
        'PHP' => PHP_VERSION,
        'SQLite' => (string) db()->query('SELECT sqlite_version()')->fetchColumn(),
        'OpenSSL' => extension_loaded('openssl') ? OPENSSL_VERSION_TEXT : 'nicht geladen',
    ];
}

/**
 * SBOM im Format CycloneDX 1.5 (JSON), zum Herunterladen.
 *
 * @return array<string, mixed>
 */
function sbom_cyclonedx(): array
{
    $component = fn (array $c, string $scope): array => [
        'type' => 'library',
        'name' => $c['name'],
        'version' => $c['version'],
        'scope' => $scope,
        'description' => $c['purpose'],
        'licenses' => [['license' => ['id' => $c['license']]]],
        'externalReferences' => [['type' => 'website', 'url' => $c['source']]],
        // Prüfsumme der Hauptdatei; weitere Dateien als Eigenschaften
        'hashes' => $c['files'][0]['sha256'] !== '' ? [['alg' => 'SHA-256', 'content' => $c['files'][0]['sha256']]] : [],
        'properties' => array_map(
            fn (array $f) => ['name' => 'file:' . $f['path'], 'value' => 'sha256:' . $f['sha256']],
            $c['files']
        ),
    ];
    $components = array_map(fn (array $c) => $component($c, 'required'), sbom_components());
    foreach (SBOM_DEV_TOOLS as $tool) {
        $components[] = $component($tool, 'excluded');
    }
    return [
        'bomFormat' => 'CycloneDX',
        'specVersion' => '1.5',
        'version' => 1,
        'metadata' => [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'component' => ['type' => 'application', 'name' => 'conf-form', 'version' => app_version()],
            'properties' => array_map(
                fn (string $name, string $version) => ['name' => 'runtime:' . $name, 'value' => $version],
                array_keys(sbom_runtime()),
                sbom_runtime()
            ),
        ],
        'components' => $components,
    ];
}
