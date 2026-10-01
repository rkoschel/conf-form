<?php
declare(strict_types=1);

final class AdminSettingsTest extends HttpTestCase
{
    public function testAdminPagesAreNotCached(): void
    {
        $this->assertSame('no-store', $this->get('/admin/settings.php')['headers']['cache-control'] ?? null);
    }

    public function testRejectsPostWithoutCsrfToken(): void
    {
        $response = $this->post('/admin/settings.php', ['inactive_text' => 'Hack']);

        $this->assertSame(400, $response['status']);
        $this->assertStringNotContainsString('Hack', $this->get('/')['body']);
    }

    public function testSavedTextAppearsOnInfoPage(): void
    {
        $csrf = $this->csrfToken('/admin/settings.php');

        $response = $this->post('/admin/settings.php', [
            'csrf' => $csrf,
            'inactive_text' => "  Anmeldung ab Januar <b>möglich</b>.\r\nBis bald!  ",
        ]);

        $this->assertSame(303, $response['status']);
        $this->assertSame('/admin/settings.php', $response['headers']['location'] ?? null);
        $this->assertStringContainsString('Einstellungen gespeichert.', $this->get('/admin/settings.php')['body']);

        $info = $this->get('/')['body'];
        $this->assertStringContainsString("Anmeldung ab Januar &lt;b&gt;möglich&lt;/b&gt;.\nBis bald!", $info);
    }

    public function testEmptyTextShowsError(): void
    {
        $csrf = $this->csrfToken('/admin/settings.php');

        $response = $this->post('/admin/settings.php', ['csrf' => $csrf, 'inactive_text' => '   ']);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('is-invalid', $response['body']);
        $this->assertStringContainsString('Bitte einen Text angeben.', $response['body']);
    }
}
