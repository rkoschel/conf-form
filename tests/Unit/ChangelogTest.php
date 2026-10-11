<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ChangelogTest extends TestCase
{
    public function testChangelogIsWellFormedNewestFirst(): void
    {
        $previous = null;
        foreach (CHANGELOG as $release) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+(\.\d+)?$/', $release['version']);
            $this->assertNotFalse(DateTimeImmutable::createFromFormat('!Y-m-d', $release['date']), $release['date']);
            $this->assertNotEmpty(array_intersect_key($release, CHANGELOG_SECTIONS), 'mindestens ein Abschnitt');
            $this->assertSame([], array_diff(array_keys($release), ['version', 'date', ...array_keys(CHANGELOG_SECTIONS)]), 'nur bekannte Abschnitte');
            if ($previous !== null) {
                $this->assertTrue(version_compare($release['version'], $previous['version'], '<'), 'neueste Version zuerst');
                $this->assertLessThanOrEqual($previous['date'], $release['date']);
            }
            $previous = $release;
        }
        $this->assertSame(CHANGELOG[0]['version'], app_version());
    }

    public function testDevToolsInSbomMatchInstallScript(): void
    {
        $script = (string) file_get_contents(ROOT_DIR . '/tools/install.sh');
        foreach (SBOM_DEV_TOOLS as $tool) {
            $this->assertStringContainsString($tool['source'] . ' ' . $tool['files'][0]['sha256'], $script, $tool['name'] . ': URL und Prüfsumme wie in tools/install.sh');
            $this->assertStringContainsString($tool['version'], $tool['source']);
        }
    }
}
