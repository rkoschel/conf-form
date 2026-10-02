<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuotaSharesTest extends TestCase
{
    /** @return array<string, array{int, int, int, array{confirmed: int, waitlist: int, free: int}}> */
    public static function cases(): array
    {
        // [bestätigt, offen, Kapazität, erwartet]
        return [
            'leer' => [0, 0, 150, ['confirmed' => 0, 'waitlist' => 0, 'free' => 100]],
            'teilweise' => [75, 30, 150, ['confirmed' => 50, 'waitlist' => 20, 'free' => 30]],
            'Rundung, Summe 100' => [1, 1, 3, ['confirmed' => 33, 'waitlist' => 33, 'free' => 34]],
            'genau voll' => [100, 50, 150, ['confirmed' => 67, 'waitlist' => 33, 'free' => 0]],
            'Warteliste größer als Rest' => [120, 45, 150, ['confirmed' => 80, 'waitlist' => 20, 'free' => 0]],
            'bestätigt über Kontingent' => [170, 10, 150, ['confirmed' => 100, 'waitlist' => 0, 'free' => 0]],
            'keine Kapazität' => [5, 5, 0, ['confirmed' => 0, 'waitlist' => 0, 'free' => 100]],
        ];
    }

    /** @param array{confirmed: int, waitlist: int, free: int} $expected */
    #[DataProvider('cases')]
    public function testCalculatesWholePercentagesOfCapacity(int $confirmed, int $pending, int $max, array $expected): void
    {
        $shares = quota_shares($confirmed, $pending, $max);

        $this->assertSame($expected, $shares);
        $this->assertSame(100, array_sum($shares));
    }
}
