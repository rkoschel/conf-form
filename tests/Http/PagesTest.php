<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class PagesTest extends HttpTestCase
{
    public function testInfoPageShowsInactiveTextWithoutActiveEvent(): void
    {
        $response = $this->get('/');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Derzeit ist keine Anmeldung möglich.', $response['body']);
    }

    /** @return array<string, array{string}> */
    public static function publicPages(): array
    {
        return [
            'Infoseite' => ['/'],
            'Anmeldung' => ['/register/'],
            'Absage' => ['/cancel/'],
            'Admin' => ['/admin/stats.php'],
        ];
    }

    #[DataProvider('publicPages')]
    public function testPageRendersWithSecurityHeaders(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(200, $response['status']);
        $this->assertSame('nosniff', $response['headers']['x-content-type-options'] ?? null);
        $this->assertSame('DENY', $response['headers']['x-frame-options'] ?? null);
        $this->assertSame('same-origin', $response['headers']['referrer-policy'] ?? null);
        $this->assertArrayNotHasKey('x-powered-by', $response['headers']);
    }

    /** @return array<string, array{string}> */
    public static function barPages(): array
    {
        return ['Infoseite' => ['/'], 'Anmeldung' => ['/register/'], 'Absage' => ['/cancel/'], 'Fehlerseite' => ['/team/']];
    }

    #[DataProvider('barPages')]
    public function testPublicPagesHaveStickyBarWithHomeButton(string $path): void
    {
        $body = $this->get($path)['body'];

        $this->assertMatchesRegularExpression(
            '#<header class="public-bar[^"]*">.*?<a href="https://example\.org/hauptseite/" class="btn[^"]*"[^>]*referrerpolicy="no-referrer"[^>]*>.*?Zurück zur Hauptseite.*?</a>.*?</header>#s',
            $body
        );
        $this->assertLessThan(strpos($body, '<main'), strpos($body, '<header class="public-bar">'), 'Leiste steht über dem Inhalt');
        $this->assertSame(1, substr_count($body, '<header class="public-bar">'), 'nur eine Leiste');
        $this->assertSame(1, substr_count(substr($body, strpos($body, '<header'), strpos($body, '</header>') - strpos($body, '<header')), '<a '), 'in der Leiste nur ein Link');
        $this->assertGreaterThan(strpos($body, '</main>'), strpos($body, 'data-theme-value'), 'Theme-Auswahl bleibt im Footer');
    }

    public function testPublicBarIsFlatAndFullWidth(): void
    {
        $body = $this->get('/')['body'];

        // volle Breite: kein .container um die Leiste, der Button sitzt direkt in der Leiste
        $this->assertMatchesRegularExpression('#<header class="public-bar">\s*<div class="public-bar-inner">#', $body);

        // flach: die Regeln der Leiste in app.css haben weder Schatten noch Rundung
        $css = $this->get('/assets/app.css')['body'];
        preg_match_all('#\.public-bar(?:-inner)?\s*\{[^}]*\}#', $css, $blocks);
        $this->assertNotEmpty($blocks[0], 'Regeln der Leiste gefunden');
        foreach ($blocks[0] as $block) {
            $this->assertStringNotContainsString('box-shadow', $block);
            $this->assertStringNotContainsString('border-radius', $block);
        }
        $this->assertDoesNotMatchRegularExpression('#\.public-bar[^{]*\{[^}]*box-shadow#', $css, 'auch nicht im Dark Mode');
        // sichtbare Abgrenzung zum Inhalt: feine Linie in der Rahmenfarbe des Themes (hell und dunkel)
        $this->assertMatchesRegularExpression('#\.public-bar\s*\{[^}]*border-bottom:\s*1px solid var\(--bs-border-color\)#', $css);
    }

    public function testAdminHasNoPublicBar(): void
    {
        $this->assertStringNotContainsString('public-bar', $this->get('/admin/stats.php')['body']);
    }

    public function testInfoPageSetsNoCookie(): void
    {
        $this->assertArrayNotHasKey('set-cookie', $this->get('/')['headers']);
    }

    public function testUnknownPathsReturn404LikeTheServer(): void
    {
        $this->assertSame(404, $this->get('/gibt-es-nicht/')['status']);
        $this->assertSame(404, $this->get('/konferenz/team/')['status']);
        $this->assertSame(404, $this->get('/README.md')['status']);
    }

    public function testAppDirectoryIsBlocked(): void
    {
        $this->assertSame(403, $this->get('/app/schema.sql')['status']);
        $this->assertSame(403, $this->get('/app/bootstrap.php')['status']);
    }

    public function testAdminStartPageIsStats(): void
    {
        $response = $this->get('/admin/');

        $this->assertSame(303, $response['status']);
        $this->assertSame('/admin/stats.php', $response['headers']['location'] ?? null);
    }
}
