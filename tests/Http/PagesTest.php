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
            'Admin' => ['/admin/'],
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

    public function testAdminHasNoPublicBar(): void
    {
        $this->assertStringNotContainsString('public-bar', $this->get('/admin/')['body']);
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
}
