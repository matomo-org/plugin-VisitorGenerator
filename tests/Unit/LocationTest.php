<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Unit;

use Faker\Factory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorGenerator\Faker\Location;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @group VisitorGenerator
 * @group LocationTest
 */
class LocationTest extends TestCase
{
    public function testDatasetContainsConsistentPopulatedPlaces(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../../data/locations.json'), true, 512, JSON_THROW_ON_ERROR);
        $seen = [];
        $count = 0;
        foreach ($data as $country => $regions) {
            self::assertMatchesRegularExpression('/^[a-z]{2}$/', $country);
            self::assertNotEmpty($regions);
            foreach ($regions as $region => $cities) {
                self::assertMatchesRegularExpression('/^[A-Z0-9-]{1,3}$/', (string) $region);
                self::assertNotEmpty($cities);
                foreach ($cities as $city) {
                    self::assertSame(['city', 'latitude', 'longitude'], array_keys($city));
                    self::assertNotEmpty($city['city']);
                    self::assertLessThanOrEqual(255, mb_strlen($city['city']));
                    self::assertGreaterThanOrEqual(-90, $city['latitude']);
                    self::assertLessThanOrEqual(90, $city['latitude']);
                    self::assertGreaterThanOrEqual(-180, $city['longitude']);
                    self::assertLessThanOrEqual(180, $city['longitude']);
                    self::assertEquals(round($city['latitude'], 2), $city['latitude']);
                    self::assertEquals(round($city['longitude'], 2), $city['longitude']);
                    $key = json_encode([$country, $region, mb_strtolower($city['city'])]);
                    self::assertArrayNotHasKey($key, $seen);
                    $seen[$key] = true;
                    ++$count;
                }
            }
        }
        self::assertSame(5000, $count);
        self::assertGreaterThan(200, count($data));
        self::assertArrayNotHasKey('aq', $data);
        $counts = array_map(static fn ($regions) => array_sum(array_map('count', $regions)), $data);
        self::assertGreaterThan($counts['us'], $counts['in']);
        self::assertGreaterThan($counts['us'], $counts['cn']);
        self::assertGreaterThan($counts['is'] * 20, $counts['us']);
        foreach (['us', 'br', 'de', 'ng', 'in', 'cn', 'au'] as $country) {
            self::assertArrayHasKey($country, $data);
        }
    }

    public function testLocationsAreCompleteRecordsFromTheDataset(): void
    {
        $faker = Factory::create();
        $faker->seed(145);
        $provider = new Location($faker);
        $data = json_decode(file_get_contents(__DIR__ . '/../../data/locations.json'), true);
        for ($i = 0; $i < 100; ++$i) {
            $location = $provider->location();
            self::assertContains([
                'city' => $location['city'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
            ], $data[$location['country']][$location['region']]);
        }
    }

    public function testFiltersAndSeeding(): void
    {
        $faker = Factory::create();
        $provider = new Location($faker, 'uS', 'ca');
        $faker->seed(42);
        $first = [];
        for ($i = 0; $i < 20; ++$i) {
            $location = $provider->location();
            self::assertSame('us', $location['country']);
            self::assertSame('CA', $location['region']);
            $first[] = $location;
        }
        $faker->seed(42);
        foreach ($first as $expected) {
            self::assertSame($expected, $provider->location());
        }
        $numericRegion = new Location($faker, 'IS', '1');
        self::assertSame('1', $numericRegion->location()['region']);

        $countryOnly = new Location($faker, 'DE');
        for ($i = 0; $i < 20; ++$i) {
            self::assertSame('de', $countryOnly->location()['country']);
        }
    }

    /** @dataProvider invalidFilters */
    public function testInvalidFiltersFailBeforeSampling(?string $country, ?string $region): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Location(Factory::create(), $country, $region);
    }

    public function invalidFilters(): array
    {
        return [[null, 'CA'], ['', null], ['USA', null], ['zz', null], ['US', 'XX'], ['US', 'BE'], ['US', '']];
    }
}
