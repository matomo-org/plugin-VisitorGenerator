<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Integration;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\VisitorGenerator\Generator\VisitFakeQuery;
use Piwik\Plugins\VisitorGenerator\Generator\VisitsFake;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @group VisitorGenerator
 * @group SyntheticLocationTest
 */
class SyntheticLocationTest extends IntegrationTestCase
{
    public function testDatabaseGeneratorStoresACompleteLocationAndKeepsItOnUpdate(): void
    {
        $idSite = Fixture::createWebsite('2020-01-01 00:00:00');
        $generator = new VisitFakeQuery(10, 10);
        $generator->setLocationFilter('US', 'CA');
        $query = $generator->getInsertVisitorQuery(str_repeat('a', 8), '1', strtotime('2020-01-02 12:00:00'), $idSite);
        $this->executeQuery($query);
        $visit = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit'));
        $this->assertLocation($visit);
        $query = $generator->getUpdateVisitQuery((int) $visit['idvisit'], $visit['visit_first_action_time'], strtotime('2020-01-02 12:01:00'), $idSite);
        $this->executeQuery($query);
        $updated = Db::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit'));
        foreach (['country', 'region', 'city', 'latitude', 'longitude'] as $field) {
            self::assertSame($visit['location_' . $field], $updated['location_' . $field]);
        }
        self::assertSame(2, (int) $updated['visit_total_actions']);
    }

    /**
     * The generator caches its authentication token for the process, but later
     * test classes recreate the database and invalidate that token.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTrackerGeneratorStoresCompleteLocations(): void
    {
        Fixture::createSuperUser();
        $idSite = Fixture::createWebsite('2020-01-01 00:00:00');
        $generator = new class extends VisitsFake {
            protected function makeMatomoTracker($idSite)
            {
                return Fixture::getTracker($idSite, '2020-01-02 12:00:00', true, true);
            }
        };
        $generator->setLocationFilter('US', 'CA');
        $generator->generate(strtotime('2020-01-02'), $idSite, 10);
        $visits = Db::fetchAll('SELECT * FROM ' . Common::prefixTable('log_visit'));
        self::assertNotEmpty($visits);
        foreach ($visits as $visit) {
            $this->assertLocation($visit);
        }
    }

    private function executeQuery(array $query): void
    {
        $bind = [];
        $sql = preg_replace_callback('/:[a-z_0-9]+/', static function ($match) use ($query, &$bind) {
            $bind[] = $query['bind'][$match[0]];
            return '?';
        }, $query['sql']);
        Db::query($sql, $bind);
    }

    private function assertLocation(array $visit): void
    {
        self::assertSame('us', $visit['location_country']);
        self::assertSame('CA', $visit['location_region']);
        $data = json_decode(file_get_contents(__DIR__ . '/../../data/locations.json'), true);
        $found = false;
        foreach ($data['us']['CA'] as $city) {
            if (
                $city['city'] === $visit['location_city']
                && abs($city['latitude'] - (float) $visit['location_latitude']) < 0.000001
                && abs($city['longitude'] - (float) $visit['location_longitude']) < 0.000001
            ) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'The stored visit must contain one complete bundled location.');
    }
}
