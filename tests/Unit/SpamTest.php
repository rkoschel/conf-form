<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SpamTest extends TestCase
{
    private const T = 1_800_000_000;

    public function testAcceptsTimestampAfterMinimumAge(): void
    {
        $this->assertTrue(spam_timestamp_valid(spam_timestamp(self::T), self::T + 3));
    }

    public function testRejectsFormSubmittedTooFast(): void
    {
        $this->assertFalse(spam_timestamp_valid(spam_timestamp(self::T), self::T + 2));
    }

    public function testAcceptsTimestampAtMaximumAge(): void
    {
        $this->assertTrue(spam_timestamp_valid(spam_timestamp(self::T), self::T + 7200));
    }

    public function testRejectsFormOlderThanTwoHours(): void
    {
        $this->assertFalse(spam_timestamp_valid(spam_timestamp(self::T), self::T + 7201));
    }

    public function testRejectsTimestampFromTheFuture(): void
    {
        $this->assertFalse(spam_timestamp_valid(spam_timestamp(self::T + 60), self::T));
    }

    public function testRejectsTamperedTime(): void
    {
        [, $signature] = explode('.', spam_timestamp(self::T));
        $this->assertFalse(spam_timestamp_valid((self::T - 60) . '.' . $signature, self::T + 10));
    }

    public function testRejectsTamperedSignature(): void
    {
        $value = spam_timestamp(self::T);
        $value[-1] = $value[-1] === '0' ? '1' : '0';
        $this->assertFalse(spam_timestamp_valid($value, self::T + 10));
    }

    public function testRejectsMalformedTimestamp(): void
    {
        foreach (['', 'abc', (string) self::T, self::T . '.', '-5.' . str_repeat('0', 64)] as $value) {
            $this->assertFalse(spam_timestamp_valid($value, self::T + 10), $value);
        }
    }

    public function testHoneypot(): void
    {
        $this->assertFalse(spam_honeypot_filled([]));
        $this->assertFalse(spam_honeypot_filled([SPAM_HONEYPOT_FIELD => '']));
        $this->assertTrue(spam_honeypot_filled([SPAM_HONEYPOT_FIELD => 'http://spam.example']));
        $this->assertTrue(spam_honeypot_filled([SPAM_HONEYPOT_FIELD => ['x']]));
    }

    public function testFieldsContainHoneypotAndSignedTimestamp(): void
    {
        $html = spam_fields(self::T);

        $this->assertStringContainsString('name="' . SPAM_HONEYPOT_FIELD . '"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('value="' . spam_timestamp(self::T) . '"', $html);
    }

    public function testIpHashIsKeyedAndDoesNotContainIp(): void
    {
        $hash = ip_hash('192.0.2.1');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertNotSame(hash('sha256', '192.0.2.1'), $hash);
        $this->assertNotSame($hash, ip_hash('192.0.2.2'));
    }

    public function testMessages(): void
    {
        $this->assertNull(spam_message(SPAM_OK));
        $this->assertNull(spam_message(SPAM_HONEYPOT), 'Bots bekommen eine Erfolgsantwort');
        $this->assertSame('Bitte Formular neu laden und erneut absenden.', spam_message(SPAM_TIMESTAMP));
        $this->assertNotNull(spam_message(SPAM_RATE_LIMIT));
    }
}
