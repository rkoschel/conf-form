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

    public function testParsesGermanDates(): void
    {
        $this->assertSame('2027-05-01', parse_date_de('01.05.2027'));
        $this->assertSame('2027-05-01', parse_date_de(' 1.5.2027 '));
        $this->assertNull(parse_date_de('29.02.2027'), 'kein Schaltjahr');
        $this->assertSame('2028-02-29', parse_date_de('29.02.2028'));
        $this->assertNull(parse_date_de('2027-05-01'));
        $this->assertNull(parse_date_de('05/01/2027'));
        $this->assertNull(parse_date_de('1.5.27'));
    }

    public function testParses24HourTimes(): void
    {
        $this->assertSame('09:30', parse_time('9:30'));
        $this->assertSame('00:00', parse_time('00:00'));
        $this->assertSame('23:59', parse_time('23:59'));
        $this->assertNull(parse_time('24:00'));
        $this->assertNull(parse_time('12:60'));
        $this->assertNull(parse_time('11:59 PM'));
        $this->assertNull(parse_time('1130'));
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
