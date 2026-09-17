<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\System;

use Piwik\Common;
use Piwik\Db;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\ConsoleCommandTestCase;

/**
 * @group VisitorGenerator
 * @group LocationCommandTest
 */
class LocationCommandTest extends ConsoleCommandTestCase
{
    /** @dataProvider invalidCommands */
    public function testInvalidFiltersFailBeforeGeneratingVisits(string $command, array $options, string $message): void
    {
        $before = Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit'));
        $code = $this->applicationTester->run(['command' => $command, '--idsite' => 999999] + $options);
        self::assertNotSame(0, $code);
        self::assertStringContainsString($message, $this->applicationTester->getDisplay());
        self::assertEquals($before, Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit')));
    }

    public function invalidCommands(): array
    {
        return [
            ['visitorgenerator:generate-visits', ['--country' => 'XX'], 'No locations available'],
            ['visitorgenerator:generate-visits-db', ['--country' => 'XX', '--threads' => 2], 'No locations available'],
            ['visitorgenerator:generate-visits', ['--region' => 'CA'], 'A country is required'],
            ['visitorgenerator:generate-visits-db', ['--country' => 'US', '--region' => 'BE'], 'No locations available'],
            ['visitorgenerator:generate-visits', ['--country' => 'US'], 'Location filters require --no-logs'],
            ['visitorgenerator:generate-visits', ['--country' => 'US', '--no-logs' => true, '--no-fake' => true], 'cannot be combined with --no-fake'],
        ];
    }

    public function testDatabaseCommandUsesLocationFilters(): void
    {
        $idSite = Fixture::createWebsite('2020-01-01 00:00:00');
        $code = $this->applicationTester->run([
            'command' => 'visitorgenerator:generate-visits-db',
            '--idsite' => $idSite,
            '--limit-visits' => 3,
            '--min-actions' => 1,
            '--max-actions' => 1,
            '--conversion-percent' => 0,
            '--start-date' => '2020-01-02',
            '--country' => 'us',
            '--region' => 'ca',
        ]);
        self::assertSame(0, $code, $this->getCommandDisplayOutputErrorMessage());

        // Inspect the worker invocation without launching a process outside the test database.
        $command = $this->application->find('visitorgenerator:generate-visits-db');
        $input = (new \ReflectionMethod($command, 'getInput'))->invoke($command);
        $output = (new \ReflectionMethod($command, 'getOutput'))->invoke($command);
        $worker = (new \ReflectionMethod($command, 'buildThreadCommand'))->invoke($command, $input, $output, $idSite, 1, 2);
        self::assertContains('--country=us', $worker);
        self::assertContains('--region=ca', $worker);
        $visits = Db::fetchAll('SELECT location_country, location_region, location_city, location_latitude, location_longitude FROM ' . Common::prefixTable('log_visit') . ' WHERE idsite = ?', [$idSite]);
        self::assertNotEmpty($visits);
        foreach ($visits as $visit) {
            self::assertSame('us', $visit['location_country']);
            self::assertSame('CA', $visit['location_region']);
            self::assertNotEmpty($visit['location_city']);
            self::assertNotNull($visit['location_latitude']);
            self::assertNotNull($visit['location_longitude']);
        }
    }
}
