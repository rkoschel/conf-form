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

    public function testInfoPageSetsNoCookie(): void
    {
        $this->assertArrayNotHasKey('set-cookie', $this->get('/')['headers']);
    }

    public function testAppDirectoryIsBlocked(): void
    {
        $this->assertSame(403, $this->get('/app/schema.sql')['status']);
        $this->assertSame(403, $this->get('/app/bootstrap.php')['status']);
    }
}
