<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Piwik;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeApi;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\Plugins\VisitorGenerator\Generator;
use Piwik\SettingsPiwik;
use Piwik\View;

include_once __DIR__ . '/../../../libs/PiwikTracker/PiwikTracker.php';

class VisitsFake extends Generator
{
    public function generate($time = false, $idSite = 1, $limit = 1000)
    {
        $date = date("Y-m-d", $time);

        $tracker = new \PiwikTracker(1, $this->getPiwikUrl());
        $tracker->enableBulkTracking();
        $user = $this->getCurrentUser();
        $site = $this->getCurrentSite($idSite);

        $numSearches = rand(floor($limit / 40), ceil($limit / 20));
        $numSearchesDone = 0;

        $i = 0;
        while ($i < $limit) {
            $pageUrl = $this->faker->pageURL;

            $i++;
            $tracker->setTokenAuth($user['token_auth']);
            $tracker->setUserAgent($this->faker->userAgent);
            $tracker->setBrowserLanguage($this->faker->locale);
            $tracker->setCity($this->faker->city);
            $tracker->setRegion($this->faker->region);
            $tracker->setCountry(strtolower($this->faker->countryCode));
            $tracker->setLatitude($this->faker->latitude);
            $tracker->setLongitude($this->faker->longitude);
            $tracker->setIp($this->faker->ipv4);
            $tracker->setLocalTime($this->faker->time());
            $tracker->setIdSite($idSite);

            if ($this->faker->boolean(10)) {
                $tracker->setNewVisitorId();
            }

            $resolution = $this->faker->resolution;
            $tracker->setResolution( $resolution[0], $resolution[1]);
            $tracker->setBrowserHasCookies($this->faker->boolean(90));
            $tracker->setPlugins($flash = $this->faker->boolean(90), $java = $this->faker->boolean(85), $director = $this->faker->boolean(50), $quickTime = $this->faker->boolean(40), $realPlayer = $this->faker->boolean(10), $pdf = $this->faker->boolean(75), $windowsMedia = $this->faker->boolean(60), $gears = $this->faker->boolean(10), $silverlight = $this->faker->boolean(5));
            $tracker->setCustomVariable(1, 'gender', $this->faker->gender, 'visit');
            $tracker->setCustomVariable(2, 'age', $this->faker->randomDigit, 'visit');
            $tracker->setCustomVariable(3, 'languageCode', $this->faker->languageCode, 'visit');
            $tracker->setCustomVariable(1, 'tld', $this->faker->tld, 'page');
            $tracker->setCustomVariable(2, 'ean', $this->faker->numerify('########'), 'page');
            $tracker->setGenerationTime($this->faker->randomNumber(190, 3000));
            $tracker->setForceVisitDateTime($date . ' ' . $this->faker->time('H:i:s'));
            $tracker->setUrlReferrer($this->faker->referrer);

            if (0 === strpos($pageUrl, 'http')) {
                $tracker->doTrackAction($pageUrl, 'link');
            } elseif (false !== strpos($pageUrl, 'zip')) {
                $tracker->setUrl($site['main_url'] . $pageUrl);
                $tracker->doTrackAction($site['main_url'] . $pageUrl, 'download');
            } else {
                $tracker->setUrl($site['main_url'] . $pageUrl);
                $tracker->doTrackPageView($this->faker->sentence());
            }

            if ($numSearchesDone < $numSearches) {
                $tracker->doTrackSiteSearch($this->faker->word, $this->faker->searchEngine, $this->faker->randomNumber(0, 10));
                $numSearchesDone++;
                $i++;
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

    private function getCurrentUser()
    {
        return UsersManagerApi::getInstance()->getUser(Piwik::getCurrentUserLogin());
    }

}
