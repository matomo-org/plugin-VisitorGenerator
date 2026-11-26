<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Integration\Commands;

use Piwik\Piwik;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\ConsoleCommandTestCase;

/**
 * @group VisitorGenerator
 * @group GenerateVisitsTest
 * @group Integration
 */
class GenerateVisitsTest extends ConsoleCommandTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        Fixture::createWebsite('2020-01-01 00:00:00');
    }

    public function testFakeVisitsUseTimeoutOption(): void
    {
        $requestTimeout = null;
        // Stop at the first tracked visit, so nothing is sent to Matomo.
        Piwik::addAction('VisitorGenerator.VisitsFake.trackVisit', function (\MatomoTracker $tracker) use (&$requestTimeout) {
            $requestTimeout = $tracker->getRequestTimeout();
            throw new \RuntimeException('stop generating');
        });

        $this->runGenerateVisits('120');

        self::assertSame(120, $requestTimeout);
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
        yield 'negative' => ['-1'];
        yield 'not a number' => ['abc'];
        yield 'fraction' => ['1.5'];
    }

    private function runGenerateVisits(string $timeout): int
    {
        return $this->applicationTester->run([
            'command' => 'visitorgenerator:generate-visits',
            '--idsite' => 1,
            '--days' => 1,
            '--no-logs' => true,
            '--limit-fake-visits' => 1,
            '--custom-matomo-url' => 'http://matomo.test/',
            '--timeout' => $timeout,
        ]);
    }
}
