<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Piwik;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeApi;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Plugins\VisitorGenerator\Generator;

class BotRequestsFake extends Generator
{
    public function generate(int $time = null, int $idSite = 1, int $limit = 1000): int
    {
        $date = date("Y-m-d", $time);

        $tracker = $this->makeMatomoTracker($idSite);
        $tracker->enableBulkTracking();
        $tokenAuth = Piwik::requestTemporarySystemAuthToken('VistorGenerator', 24);
        $site = $this->getCurrentSite($idSite);

        $i = 0;
        while ($i < $limit) {
            $tracker->setNewVisitorId();

            $pageUrl = $this->faker->pageURL;

            // don't use link urls for bot requests
            while (0 === strpos($pageUrl, 'http')) {
                $pageUrl = $this->faker->pageURL;
            }

            $i++;
            $tracker->setTokenAuth($tokenAuth);
            $tracker->setUserAgent($this->faker->aiAssistantBotUserAgent);

            $tracker->setIp($this->faker->boolean(77) ? $this->faker->ipv4 : $this->faker->ipv6);
            $tracker->setLocalTime($this->faker->time());
            $tracker->setIdSite($idSite);

            $tracker->setPerformanceTimings(
                null,
                $this->faker->numberBetween(400, 600),
                null,
                null,
                null,
                null
            );

            $tracker->setForceVisitDateTime($date . ' ' . $this->faker->time('H:i:s'));

            $tracker->setCustomTrackingParameter('bw_bytes', $this->faker->numberBetween(1, 2000000000));
            $tracker->setCustomTrackingParameter('http_status', $this->faker->randomElement([
                // most requests should be status 200, so adding it here more often
                200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200,
                200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200,
                301, 302, 307, 308,
                401, 404, 404, 404, 404,
                500,
            ]));

            $tracker->setCustomTrackingParameter('source', $this->faker->randomElement([
                '', 'cloudflare', 'cloudfront', 'wordpress'
            ]));

            // force bot tracking mode
            $tracker->setCustomTrackingParameter('recMode', '1');


            if (false !== strpos($pageUrl, 'zip')) {
                $tracker->setUrl($site['main_url'] . $pageUrl);
                $tracker->doTrackAction($site['main_url'] . $pageUrl, 'download');
            } else {
                $tracker->setUrl($site['main_url'] . $pageUrl);

                $tracker->doTrackPageView('');
            }

            if ($i % 100 == 0) {
                $tracker->doBulkTrack();
            }
        }

        if ($i % 100 != 0) {
            $tracker->doBulkTrack();
        }

        CoreAdminHomeApi::getInstance()->invalidateArchivedReports($idSite, $date);

        return $i;
    }

    private function getCurrentSite($idSite)
    {
        return SitesManagerApi::getInstance()->getSiteFromId($idSite);
    }
}
