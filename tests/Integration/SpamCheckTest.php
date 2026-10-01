<?php
declare(strict_types=1);

final class SpamCheckTest extends DbTestCase
{
    private const T = 1_800_000_000;
    private const IP = '198.51.100.7';

    public function testAllowsUpToLimitThenRejects(): void
    {
        // tests/config.test.php: 10 Versuche in 60 Minuten
        for ($i = 0; $i < 10; $i++) {
            $this->assertFalse(rate_limit_exceeded(self::IP, self::T + $i), "Versuch $i");
        }
        $this->assertTrue(rate_limit_exceeded(self::IP, self::T + 10));
    }

    public function testCountsIpsSeparately(): void
    {
        for ($i = 0; $i < 11; $i++) {
            rate_limit_exceeded(self::IP, self::T);
        }
        $this->assertFalse(rate_limit_exceeded('198.51.100.8', self::T));
    }

    public function testAllowsAgainAfterWindowAndDeletesOldRows(): void
    {
        for ($i = 0; $i < 11; $i++) {
            rate_limit_exceeded(self::IP, self::T);
        }
        $this->assertTrue(rate_limit_exceeded(self::IP, self::T + 59 * 60));

        $this->assertFalse(rate_limit_exceeded(self::IP, self::T + 60 * 60));
        // Nur noch die Versuche innerhalb des Fensters sind gespeichert
        $this->assertSame(2, $this->rowCount('rate_limit'));
    }

    public function testStoresOnlyHashedIp(): void
    {
        rate_limit_exceeded(self::IP, self::T);

        $row = db()->query('SELECT * FROM rate_limit')->fetch();
        $this->assertSame(ip_hash(self::IP), $row['ip_hash']);
        $this->assertStringNotContainsString(self::IP, implode('|', $row));
        $this->assertSame('2027-01-15T08:00:00Z', $row['created_at']);
    }

    public function testCheckPassesValidSubmission(): void
    {
        $post = [SPAM_HONEYPOT_FIELD => '', SPAM_TIMESTAMP_FIELD => spam_timestamp(self::T)];
        $this->assertSame(SPAM_OK, spam_check($post, self::IP, self::T + 30));
    }

    public function testCheckDetectsHoneypotBeforeTimestamp(): void
    {
        $post = [SPAM_HONEYPOT_FIELD => 'spam', SPAM_TIMESTAMP_FIELD => 'kaputt'];
        $this->assertSame(SPAM_HONEYPOT, spam_check($post, self::IP, self::T));
    }

    public function testCheckDetectsMissingTimestamp(): void
    {
        $this->assertSame(SPAM_TIMESTAMP, spam_check([], self::IP, self::T));
    }

    public function testCheckCountsEveryAttemptAndRejectsOverLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            spam_check([], self::IP, self::T);
        }
        $post = [SPAM_TIMESTAMP_FIELD => spam_timestamp(self::T)];
        $this->assertSame(SPAM_RATE_LIMIT, spam_check($post, self::IP, self::T + 30));
    }
}
