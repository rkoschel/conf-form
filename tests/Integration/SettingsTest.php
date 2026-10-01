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
}
