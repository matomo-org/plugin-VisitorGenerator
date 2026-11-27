<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Integration;

use Piwik\Piwik;
use Piwik\Plugins\VisitorGenerator\Generator\VisitsFake;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @group VisitorGenerator
 * @group RequestTimeoutTest
 */
class RequestTimeoutTest extends IntegrationTestCase
{
    public function testFakeVisitsUseRequestTimeout(): void
    {
        $idSite = Fixture::createWebsite('2020-01-01 00:00:00');
        $generator = new class extends VisitsFake {
            // Core caches the real token for the whole process, which would go stale
            // once a later test class recreates the database.
            protected function getTokenAuth(): ?string
            {
                return Fixture::getTokenAuth();
            }
        };
        $generator->setRequestTimeout(120);

        $requestTimeout = null;
        Piwik::addAction('VisitorGenerator.VisitsFake.trackVisit', function (\MatomoTracker $tracker) use (&$requestTimeout) {
            $requestTimeout = $tracker->getRequestTimeout();
            // Stop at the first visit, so nothing is sent to Matomo.
            throw new \RuntimeException('stop generating');
        });

        try {
            $generator->generate(strtotime('2020-01-02'), $idSite, 1);
        } catch (\RuntimeException $e) {
            self::assertSame('stop generating', $e->getMessage());
        }

        self::assertSame(120, $requestTimeout);
    }
}
