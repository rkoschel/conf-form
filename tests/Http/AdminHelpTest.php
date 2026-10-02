<?php
declare(strict_types=1);

final class AdminHelpTest extends HttpTestCase
{
    public function testMenuOrder(): void
    {
        $body = $this->get('/admin/help.php')['body'];

        preg_match_all('#class="nav-link[^"]*"\s+(?:aria-current="page"\s+)?href="[^"]*">([^<]+)</a>#', $body, $m);
        $this->assertSame(['Veranstaltungen', 'Anfragen', 'Auswertung', 'Einstellungen', 'Hilfe'], $m[1]);
        $this->assertMatchesRegularExpression('#nav-link active"\s+aria-current="page"\s+href="/admin/help.php">Hilfe#', $body);
    }

    public function testHelpExplainsLogicWithCurrentValues(): void
    {
        $response = $this->get('/admin/help.php');
        $body = $response['body'];

        $this->assertSame(200, $response['status']);
        $this->assertSame('no-store', $response['headers']['cache-control'] ?? null);
        foreach (['#veranstaltungen', '#kontingent', '#orte', '#programm', '#anfragen', '#mails', '#absage', '#auswertung', '#spam'] as $anchor) {
            $this->assertStringContainsString('href="' . $anchor . '"', $body);
            $this->assertStringContainsString('id="' . substr($anchor, 1) . '"', $body);
        }
        $this->assertStringContainsString('höchstens ' . AUTO_CONFIRM_MAX_PERSONS . ' Personen insgesamt', $body);
        $this->assertStringContainsString('zwischen ' . SPAM_MIN_SECONDS . ' Sekunden', $body);
        $this->assertStringContainsString('Höchstens 10 Absendeversuche je 60 Minuten', $body, 'aus der Config');
    }
}
