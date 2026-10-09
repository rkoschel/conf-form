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

    public function testPublicPagesShowOptionsSideBySideInFooter(): void
    {
        $body = $this->get('/')['body'];

        $footer = substr($body, (int) strpos($body, '<footer'));
        $this->assertStringContainsString('btn-group', $footer);
        foreach (['Automatisch', 'Hell', 'Dunkel'] as $label) {
            $this->assertMatchesRegularExpression('#data-theme-value="[a-z]+" aria-pressed="false">\s*<svg[^>]*>.*?</svg> ' . $label . '#s', $footer);
        }
        $this->assertStringNotContainsString('dropdown', $body, 'öffentlich kein Dropdown');
        $this->assertLessThan(strpos($body, '<footer'), strpos($body, '</main>'), 'unter dem Inhalt');
    }

    public function testAdminKeepsDropdown(): void
    {
        $body = $this->get('/admin/events.php')['body'];

        $this->assertStringContainsString('data-theme-current', $body);
        $this->assertStringContainsString('dropdown-menu', $body);
    }
}
