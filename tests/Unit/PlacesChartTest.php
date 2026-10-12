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

        $this->assertSame(['name' => 'Hamm', 'short' => 'Hamm', 'preferred' => true, 'other' => false, 'confirmed' => 75.0, 'pending' => 25.0,
            'people_confirmed' => 30, 'people_pending' => 10], $bars[0]);
        $this->assertSame(['name' => 'Unna', 'short' => 'Unna', 'preferred' => false, 'other' => false, 'confirmed' => 25.0, 'pending' => 25.0,
            'people_confirmed' => 10, 'people_pending' => 10], $bars[1]);
    }

    public function testPlacesBelowFivePercentOfLargestAreGroupedAsOthers(): void
    {
        $bars = places_chart([
            $this->place('Hamm', 100),
            $this->place('Unna', 5),      // genau 5 % → bleibt
            $this->place('Werne', 4),     // < 5 %
            $this->place('Ahlen', 2, 1),  // < 5 %
        ]);

        $this->assertSame(['Hamm', 'Unna', 'Andere'], array_column($bars, 'name'));
        $this->assertSame(['name' => 'Andere', 'short' => 'Andere', 'preferred' => false, 'other' => true, 'confirmed' => 6.0, 'pending' => 1.0,
            'people_confirmed' => 6, 'people_pending' => 1], $bars[2], '„Andere“ bleibt ausgeschrieben');
    }

    public function testSingleSmallPlaceKeepsItsName(): void
    {
        $this->assertSame(['Hamm', 'Werne'], array_column(places_chart([$this->place('Hamm', 100), $this->place('Werne', 2)]), 'name'));
    }

    public function testOthersHigherThanLargestPlaceSetsTheScale(): void
    {
        $places = [$this->place('Hamm', 100)];
        for ($i = 1; $i <= 30; $i++) {
            $places[] = $this->place("Ort $i", 4);
        }

        $bars = places_chart($places);

        $this->assertSame('Andere', $bars[1]['name']);
        $this->assertSame(100.0, $bars[1]['confirmed'], 'Andere (120) ist jetzt der Maßstab');
        $this->assertSame(83.33, $bars[0]['confirmed']);
    }

    public function testEmptyAndZeroPlaces(): void
    {
        $this->assertSame([], places_chart([]));
        $this->assertSame([], places_chart([$this->place('Hamm', 0)]));
    }

    public function testNamesAreShortenedToFiveCharacters(): void
    {
        $bars = places_chart([$this->place('Lippstadt-Bad Waldliesborn', 10), $this->place('Ahlen', 9), $this->place('Bönen', 5)]);

        $this->assertSame(['Lipps', 'Ahlen', 'Bönen'], array_column($bars, 'short'), 'Umlaute zählen als ein Zeichen');
        $this->assertSame('Lippstadt-Bad Waldliesborn', $bars[0]['name'], 'voller Name für den Tooltip');
    }
}
