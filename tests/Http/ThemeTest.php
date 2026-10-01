<?php
declare(strict_types=1);

/** Dark Mode: theme.js im <head> (kein helles Aufblitzen) und Umschalter */
final class ThemeTest extends HttpTestCase
{
    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return ['öffentlich' => ['/'], 'Admin' => ['/admin/events.php']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function testLoadsThemeScriptInHeadAndShowsToggle(string $path): void
    {
        $body = $this->get($path)['body'];

        $head = substr($body, 0, (int) strpos($body, '</head>'));
        $this->assertMatchesRegularExpression('#<script src="/assets/theme\.js\?v=\d+"></script>#', $head);
        $this->assertStringContainsString('data-theme-toggle hidden', $body, 'ohne JS ausgeblendet');
        foreach (['auto', 'light', 'dark'] as $value) {
            $this->assertStringContainsString('data-theme-value="' . $value . '"', $body);
        }
    }
}
