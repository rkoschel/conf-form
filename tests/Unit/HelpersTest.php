<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapesHtmlSpecialCharacters(): void
    {
        $this->assertSame(
            '&lt;script&gt;&quot;a&quot; &amp; &#039;b&#039;',
            e('<script>"a" & \'b\'')
        );
    }

    public function testEscapesNonStringValues(): void
    {
        $this->assertSame('42', e(42));
        $this->assertSame('', e(null));
    }

    public function testFormatsDates(): void
    {
        $this->assertSame('01.05.2027', format_date('2027-05-01'));
        $this->assertSame('Samstag, 01.05.2027', format_date_long('2027-05-01'));
        $this->assertSame('15.04.2027, 23:59 Uhr', format_local_datetime('2027-04-15T23:59'));
        $this->assertSame('kein Datum', format_date_long('kein Datum'), 'ungültige Werte unverändert');
    }

    public function testUrlUsesBaseUrl(): void
    {
        // base_url ist in tests/config.test.php leer
        $this->assertSame('/', url());
        $this->assertSame('/register/', url('register/'));
        $this->assertSame('/register/', url('/register/'));
    }

    public function testAssetAddsVersionForCacheBusting(): void
    {
        $this->assertMatchesRegularExpression('#^/assets/app\.css\?v=\d+$#', asset('app.css'));
    }
}
