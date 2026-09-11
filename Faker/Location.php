<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\Faker;

use Faker\Generator;
use Faker\Provider\Base;
use InvalidArgumentException;

include_once __DIR__ . '/../vendor/autoload.php';

class Location extends Base
{
    /** @var array<string, array<string, list<array{city: string, latitude: float, longitude: float}>>>|null */
    private static ?array $data = null;

    /** @var list<array{country: string, region: string, city: string, latitude: float, longitude: float}>|null */
    private ?array $locations = null;

    public function __construct(Generator $generator, ?string $country = null, ?string $region = null)
    {
        parent::__construct($generator);

        if ($region !== null && $country === null) {
            throw new InvalidArgumentException('A country is required when selecting a region.');
        }

        if ($country !== null) {
            $this->locations = $this->selectLocations(strtolower($country), $region === null ? null : strtoupper($region));
        }
    }

    /** @return array{country: string, region: string, city: string, latitude: float, longitude: float} */
    public function location(): array
    {
        $this->locations ??= $this->selectLocations(null, null);

        return $this->locations[self::numberBetween(0, count($this->locations) - 1)];
    }

    /** @return list<array{country: string, region: string, city: string, latitude: float, longitude: float}> */
    private function selectLocations(?string $country, ?string $region): array
    {
        self::$data ??= json_decode(file_get_contents(__DIR__ . '/../data/locations.json'), true, 512, JSON_THROW_ON_ERROR);

        if ($country !== null && !isset(self::$data[$country])) {
            throw new InvalidArgumentException('No locations available for country "' . $country . '". Use a two-letter country code.');
        }
        if ($region !== null && !isset(self::$data[$country][$region])) {
            throw new InvalidArgumentException('No locations available for region "' . $region . '" in country "' . $country . '".');
        }

        $locations = [];
        foreach (self::$data as $countryCode => $regions) {
            if ($country !== null && $countryCode !== $country) {
                continue;
            }
            foreach ($regions as $regionCode => $cities) {
                if ($region !== null && (string) $regionCode !== $region) {
                    continue;
                }
                foreach ($cities as $city) {
                    $locations[] = ['country' => $countryCode, 'region' => (string) $regionCode] + $city;
                }
            }
        }

        if (!$locations) {
            throw new InvalidArgumentException('No locations available for the selected country and region.');
        }

        return $locations;
    }
}
