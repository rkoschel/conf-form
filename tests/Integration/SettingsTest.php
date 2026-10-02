<?php
declare(strict_types=1);

final class SettingsTest extends DbTestCase
{
    public function testReturnsDefaultForUnknownKey(): void
    {
        $this->assertSame('fallback', setting_get('unknown', 'fallback'));
    }

    public function testSetInsertsAndUpdates(): void
    {
        setting_set('foo', 'eins');
        setting_set('foo', 'zwei');

        $this->assertSame('zwei', setting_get('foo'));
    }

    public function testTextFallsBackToDefault(): void
    {
        $this->assertSame(SETTING_TEXTS['result_confirmed']['default'], setting_text('result_confirmed'));

        setting_set('result_confirmed', '   ');
        $this->assertSame(SETTING_TEXTS['result_confirmed']['default'], setting_text('result_confirmed'));
    }

    public function testOwnTextAndPlaceholders(): void
    {
        setting_text_set('mail_signature', "  Liebe Grüße\r\n{organizer}  ");

        $this->assertSame("Liebe Grüße\nOrga", setting_text('mail_signature', ['{organizer}' => 'Orga']));
    }

    public function testDefaultIsStoredAsEmpty(): void
    {
        setting_text_set('cancel_done', SETTING_TEXTS['cancel_done']['default']);

        $this->assertSame('', setting_get('cancel_done'), 'Änderungen am Standardtext im Code greifen weiter');
    }

    public function testSingleLineTextIsNormalized(): void
    {
        setting_text_set('congregation_label', "  Heimat-\n  gemeinde ");

        $this->assertSame('Heimat- gemeinde', setting_text('congregation_label'));
    }

    public function testUnknownTextKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        setting_text('gibt_es_nicht');
    }

    public function testPreferredPlacesAreGlobalNormalizedAndDeduplicated(): void
    {
        $this->assertSame([], preferred_places());

        preferred_places_set(places_parse("Hamm\r\n  bad   Hamm \n\nHAMM\nBad Hamm\nUnna"));

        $this->assertSame(['Hamm', 'bad Hamm', 'Unna'], preferred_places());
    }
}
