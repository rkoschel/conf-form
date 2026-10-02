<?php
declare(strict_types=1);

final class TeamKeyTest extends DbTestCase
{
    public function testDisabledByDefault(): void
    {
        $this->assertSame('', team_key());
        $this->assertNull(team_url());
        $this->assertFalse(team_key_valid(''), 'leerer Schlüssel öffnet nie');
    }

    public function testGeneratedKeyIsValidAndLong(): void
    {
        $key = team_key_generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $key);
        $this->assertTrue(team_key_valid($key));
        $this->assertFalse(team_key_valid(substr($key, 0, 39)));
        $this->assertFalse(team_key_valid(['array']));
        $this->assertSame('https://example.org/konferenz/team/?k=' . $key, team_url());
    }

    public function testRegeneratingInvalidatesOldKey(): void
    {
        $old = team_key_generate();
        $new = team_key_generate();

        $this->assertNotSame($old, $new);
        $this->assertFalse(team_key_valid($old));
        $this->assertTrue(team_key_valid($new));
    }

    public function testDisableInvalidatesKey(): void
    {
        $key = team_key_generate();
        team_key_disable();

        $this->assertFalse(team_key_valid($key));
        $this->assertNull(team_url());
    }
}
