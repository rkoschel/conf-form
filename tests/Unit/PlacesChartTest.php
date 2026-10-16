<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlacesChartTest extends TestCase
{
    /** @return array{name: string, confirmed: int, pending: int, preferred: bool} */
    private function place(string $name, int $confirmed, int $pending = 0, bool $preferred = false): array
    {
        return ['name' => $name, 'confirmed' => $confirmed, 'pending' => $pending, 'preferred' => $preferred];
    }

    public function testLargestPlaceIsHundredPercentOthersRelative(): void
    {
        $bars = places_chart([$this->place('Hamm', 30, 10, true), $this->place('Unna', 10, 10)]);

        $this->assertSame(['name' => 'Hamm', 'preferred' => true, 'confirmed' => 75.0, 'pending' => 25.0,
            'people_confirmed' => 30, 'people_pending' => 10], $bars[0]);
        $this->assertSame(['name' => 'Unna', 'preferred' => false, 'confirmed' => 25.0, 'pending' => 25.0,
            'people_confirmed' => 10, 'people_pending' => 10], $bars[1]);
    }

    public function testAllPlacesAreShownEvenVerySmallOnes(): void
    {
        $bars = places_chart([$this->place('Hamm', 100), $this->place('Werne', 4), $this->place('Ahlen', 1, 1)]);

        $this->assertSame(['Hamm', 'Werne', 'Ahlen'], array_column($bars, 'name'), 'keine Zusammenfassung unter „Andere“');
        $this->assertSame([1.0, 1.0], [$bars[2]['confirmed'], $bars[2]['pending']]);
    }

    public function testEmptyAndZeroPlaces(): void
    {
        $this->assertSame([], places_chart([]));
        $this->assertSame([], places_chart([$this->place('Hamm', 0)]));
    }
}
