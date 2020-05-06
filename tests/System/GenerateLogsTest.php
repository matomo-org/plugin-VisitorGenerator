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
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\ConsoleCommandTestCase;

/**
 * @group VisitorGenerator
 * @group GenerateLogsTest
 */
class GenerateLogsTest extends ConsoleCommandTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Fixture::createWebsite('2012-01-01 00:00:00');
    }

    public function testCommand()
    {
        $code = $this->applicationTester->run([
            'command' => 'visitorgenerator:generate-live-visits',
            '--idsite' => 1,
            '--custom-matomo-url' => Fixture::getTestRootUrl(),
            '--day-of-month' => 23,
            '--time-of-day' => 0,
            '--log-file' => __DIR__ . '/../data/test.log',
            '--stop-after' => 10,
            '-vvv' => false,
        ]);

        $this->assertEquals(0, $code, $this->getCommandDisplayOutputErrorMessage());

        $output = $this->applicationTester->getDisplay();
        self::assertStringContainsString('tracked 2 actions', $output);

        $actions = Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable('log_link_visit_action'));
        $this->assertEquals(3, $actions);
    }

    public function provideContainerConfig()
    {
        return array(
            'Piwik\Access' => new FakeAccess()
        );
    }
}