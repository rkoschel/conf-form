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

    public function testEmptyTextFallsBackToDefaultShownAsPlaceholder(): void
    {
        $this->post('/admin/settings.php', ['csrf' => $this->csrfToken('/admin/settings.php'), 'inactive_text' => '   ']);

        $this->assertStringContainsString('Derzeit ist keine Anmeldung möglich.', $this->get('/')['body']);
        $form = $this->get('/admin/settings.php')['body'];
        $this->assertMatchesRegularExpression(
            '#<textarea id="inactive_text"[^>]*placeholder="Derzeit ist keine Anmeldung möglich\.">\s*</textarea>#',
            $form,
            'leeres Feld, Standardtext grau als placeholder'
        );
    }

    public function testSavesPreferredPlacesForAllEvents(): void
    {
        $this->post('/admin/settings.php', [
            'csrf' => $this->csrfToken('/admin/settings.php'),
            'preferred_places' => "Hamm\nhamm\n  Unna ",
        ]);

        db($this->serverDb());
        $this->assertSame(['Hamm', 'Unna'], preferred_places());
        $this->assertStringContainsString("Hamm\nUnna</textarea>", $this->get('/admin/settings.php')['body']);
    }

    public function testOwnTextsAppearOnPublicPages(): void
    {
        $this->post('/admin/settings.php', [
            'csrf' => $this->csrfToken('/admin/settings.php'),
            'congregation_label' => 'Gemeinde',
            'attendance_hint' => 'Bitte <ehrlich> schätzen.',
        ]);
        db($this->serverDb());
        db()->exec('UPDATE events SET active = 0');
        db()->exec("INSERT INTO events (title, date, location, registration_deadline, max_participants, active)
                    VALUES ('Konferenz', '2099-05-02', 'Hamm', '2099-04-15T23:59', 10, 1)");
        $id = (int) db()->lastInsertId();
        db()->exec("INSERT INTO event_slots (event_id, time, label) VALUES ($id, '10:00', 'Vortrag')");

        $form = $this->get('/register/')['body'];

        $this->assertStringContainsString('>Gemeinde</label>', $form);
        $this->assertStringContainsString('Bitte &lt;ehrlich&gt; schätzen.', $form);
    }
}
