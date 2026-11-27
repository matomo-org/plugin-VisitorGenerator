<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Integration\Commands;

use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\ConsoleCommandTestCase;

/**
 * Runs with --no-fake and --no-logs: generating would cache a system token that core reuses for
 * the rest of the process, after a later test class has recreated the database.
 *
 * @group VisitorGenerator
 * @group GenerateVisitsTest
 * @group Integration
 */
class GenerateVisitsTest extends ConsoleCommandTestCase
{
    private static int $idSite;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$idSite = Fixture::createWebsite('2020-01-01 00:00:00');
    }

    /**
     * @dataProvider getInvalidTimeouts
     */
    public function testRejectsInvalidTimeout(string $timeout): void
    {
        $exitCode = $this->runGenerateVisits($timeout);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('The --timeout option must be a whole number of seconds', $this->applicationTester->getDisplay());
    }

    public function getInvalidTimeouts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'not a number' => ['abc'];
        yield 'fraction' => ['1.5'];
    }

    private function runGenerateVisits(string $timeout): int
    {
        return $this->applicationTester->run([
            'command' => 'visitorgenerator:generate-visits',
            '--idsite' => self::$idSite,
            '--days' => 1,
            '--no-fake' => true,
            '--no-logs' => true,
            '--timeout' => $timeout,
        ]);
    }
}
