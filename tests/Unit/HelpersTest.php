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

    public function testFormatsUtcTimestampInEventTimezone(): void
    {
        $this->assertSame('15.04.2027, 23:59', format_utc_datetime('2027-04-15T21:59:00Z', 'Europe/Berlin'));
        $this->assertSame('10.01.2027, 13:00', format_utc_datetime('2027-01-10T12:00:00Z', 'Europe/Berlin'));
        $this->assertSame('15.04.2027, 17:59', format_utc_datetime('2027-04-15T21:59:00Z', 'America/New_York'));
        $this->assertSame('kaputt', format_utc_datetime('kaputt', 'Europe/Berlin'));
    }

    public function testFormatsChildcareAges(): void
    {
        $this->assertSame('0–2', childcare_ages(['group_5']));
        $this->assertSame('0–6', childcare_ages(['group_4', 'group_5']));
        $this->assertSame('3–12', childcare_ages(['group_3', 'group_4']));
        $this->assertSame('0–12', childcare_ages(['group_5', 'group_4', 'group_3']));
        $this->assertSame('0–2 und 7–12', childcare_ages(['group_3', 'group_5']));
        $this->assertSame('', childcare_ages([]));
        $this->assertSame(['group_5', 'group_3'], childcare_parse('group_3,group_2,group_5'));
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
