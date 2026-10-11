<?php
declare(strict_types=1);

final class AdminChangesTest extends HttpTestCase
{
    public function testShowsChangelogAndCollapsedSbom(): void
    {
        $response = $this->get('/admin/changes.php');
        $body = $response['body'];

        $this->assertSame(200, $response['status']);
        $this->assertSame('no-store', $response['headers']['cache-control'] ?? null);
        $this->assertMatchesRegularExpression('#nav-link active"\s+aria-current="page"\s+href="/admin/changes.php">Versionshinweise#', $body);
        $this->assertStringContainsString('Aktuelle Version ' . app_version() . ' vom ' . format_date(CHANGELOG[0]['date']), $body);
        foreach (CHANGELOG as $release) {
            $this->assertStringContainsString('Version ' . $release['version'], $body);
        }
        $this->assertStringContainsString('<h3 class="h6 fw-semibold mb-1 mt-2">Behoben</h3>', $body);

        // SBOM: zugeklappt (<details> ohne open), Versionen und Prüfsummen aus den Dateien
        $this->assertMatchesRegularExpression('#<details class="sbom border rounded mt-5">\s*<summary[^>]*>Software-Stückliste \(SBOM\)</summary>#', $body);
        $this->assertMatchesRegularExpression('#>Bootstrap</a>\s*</td>\s*<td class="tabular-nums">5\.3\.8</td>#', $body);
        $this->assertMatchesRegularExpression('#>PHPMailer</a>\s*</td>\s*<td class="tabular-nums">\d+\.\d+\.\d+</td>#', $body);
        $this->assertStringContainsString(hash_file('sha256', ROOT_DIR . '/web/assets/vendor/bootstrap/bootstrap.min.css'), $body);
        $this->assertStringContainsString('<dt class="col-sm-3 fw-normal text-body-secondary">PHP</dt>', $body);
        $this->assertStringContainsString('>PHPUnit</a>', $body);
        $this->assertStringNotContainsString('unbekannt', $body);
    }

    public function testSbomDownloadIsCycloneDx(): void
    {
        $response = $this->get('/admin/changes.php?download=sbom');

        $this->assertSame(200, $response['status']);
        $this->assertStringStartsWith('application/vnd.cyclonedx+json', $response['headers']['content-type'] ?? '');
        $this->assertStringContainsString('attachment; filename="conf-form-' . app_version() . '-sbom.cdx.json"', $response['headers']['content-disposition'] ?? '');
        $bom = json_decode($response['body'], true);
        $this->assertSame('CycloneDX', $bom['bomFormat']);
        $this->assertSame('1.5', $bom['specVersion']);
        $this->assertSame(app_version(), $bom['metadata']['component']['version']);
        $byName = array_column($bom['components'], null, 'name');
        $this->assertSame('required', $byName['Bootstrap']['scope']);
        $this->assertSame('excluded', $byName['PHPStan']['scope'], 'Entwicklungswerkzeug');
        $this->assertSame('SHA-256', $byName['PHPMailer']['hashes'][0]['alg']);
    }
}
