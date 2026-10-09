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

    public function testNormalizersRemoveControlCharacters(): void
    {
        $this->assertSame('ab c', normalize_line("  a\x00b\t\nc  "));
        $this->assertSame("xy\nz\tw", normalize_text("x\x07y\r\nz\tw\x1b"), 'Zeilenumbruch und Tab bleiben');
        $this->assertSame('<script>', normalize_line(' <script> '), 'HTML bleibt Text, escaped wird bei der Ausgabe');
    }

    public function testHomeUrlAllowsOnlyHttpUrlsAndRootRelativePaths(): void
    {
        $this->assertSame('https://example.org/', home_url_value('https://example.org/'));
        $this->assertSame('http://example.org/x?y=1', home_url_value('  http://example.org/x?y=1  '));
        $this->assertSame('/start/', home_url_value('/start/'));
        $this->assertSame('/', home_url_value('/'));
        $this->assertSame('https://example.org/hauptseite/', home_url(), 'aus der Konfiguration');

        foreach ([null, '', '   ', 'javascript:alert(1)', 'data:text/html,x', '//evil.example', 'ftp://example.org',
            'mailto:a@example.org', 'example.org', 'https://exa mple.org', ['https://example.org/'], 42] as $invalid) {
            $this->assertSame('/', home_url_value($invalid), 'Rückfall auf die Wurzel: ' . json_encode($invalid));
        }
    }

    public function testParsesPersonGroups(): void
    {
        $this->assertSame(person_groups_default(), person_groups_parse(''), 'leer = alle mit Standardnamen');
        $this->assertSame(person_groups_default(), person_groups_parse('kaputt'));
        $this->assertSame(
            ['group_1' => 'Eltern', 'group_4' => 'Kindergruppe 2'],
            person_groups_parse('{"group_4":"","group_1":"Eltern","x":"y"}'),
            'feste Reihenfolge, leerer Name = Standardname, Unbekanntes verworfen'
        );
        $this->assertSame(['group_2' => 'Teens'], event_groups(['person_groups' => '{"group_2":"Teens"}']));
    }

    public function testChildcareOnlyForKidsGroupsWithNames(): void
    {
        $this->assertSame(['group_3', 'group_5'], childcare_parse('group_5,group_2,group_3,x'));
        $this->assertSame(['group_4'], kids_groups(['group_1' => 'A', 'group_4' => 'B']));
        $groups = ['group_3' => 'Kinder 7–12', 'group_4' => 'Kinder 3–6', 'group_5' => 'Kinder 0–2'];
        $this->assertSame('Kinder 0–2', childcare_names(['group_5'], $groups));
        $this->assertSame('Kinder 3–6 und Kinder 0–2', childcare_names(['group_4', 'group_5'], $groups));
        $this->assertSame('Kinder 7–12, Kinder 3–6 und Kinder 0–2', childcare_names(['group_3', 'group_4', 'group_5'], $groups));
        $this->assertSame('Parallel Kinderbetreuung für Kinder 0–2', childcare_notice(['group_5'], $groups));
        $this->assertSame('', childcare_names([], $groups));
    }

    public function testTargetGroupsAndNotice(): void
    {
        $this->assertSame(['group_1', 'group_2', 'group_5'], target_groups_parse('group_5,group_2,x,group_1'));
        $this->assertSame(['group_3'], target_groups_parse(['group_3', 3, 'nope']));
        $this->assertSame([], target_groups_parse(''));

        $groups = ['group_1' => 'Erwachsene', 'group_2' => 'Jugendliche', 'group_3' => 'Kinder'];
        $this->assertSame('Zielgruppe: Jugendliche', target_notice(['group_2'], $groups));
        $this->assertSame('Zielgruppe: Erwachsene und Jugendliche', target_notice(['group_1', 'group_2'], $groups));
        $this->assertSame('Zielgruppe: Erwachsene, Jugendliche und Kinder', target_notice(['group_1', 'group_2', 'group_3'], $groups));
    }

    public function testGroupNamesJoin(): void
    {
        $groups = ['group_1' => 'A', 'group_2' => 'B', 'group_3' => 'C'];
        $this->assertSame('A', group_names_join(['group_1'], $groups));
        $this->assertSame('A und C', group_names_join(['group_1', 'group_3'], $groups));
        $this->assertSame('A, B und C', group_names_join(['group_1', 'group_2', 'group_3'], $groups));
        $this->assertSame('', group_names_join([], $groups));
        $this->assertSame('Erwachsene', group_names_join(['group_1'], []), 'Standardname, wenn die Veranstaltung keinen kennt');
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
