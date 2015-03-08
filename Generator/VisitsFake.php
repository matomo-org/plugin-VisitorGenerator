<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeApi;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\Plugins\UsersManager\Model;
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
        $tracker->setDebugStringAppend('dp=1');
        $tracker->enableBulkTracking();
        $user = $this->getAnySuperUser();
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
            $countryCode = $this->faker->countryCode;
            $tracker->setCountry(strtolower($countryCode));
            $tracker->setRegion($this->faker->region($countryCode));
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

            if ($this->faker->boolean(10)) {
                $i++;
                $tracker->doTrackContentImpression('Product 1', '/path/product1.jpg', 'http://product1.example.com');
                $tracker->doTrackContentImpression('Product 1', 'Buy Product 1 Now!', 'http://product1.example.com');
                $tracker->doTrackContentImpression('Product 2', '/path/product2.jpg', $site['main_url'] . '/product2');
                $tracker->doTrackContentImpression('Product 3', 'Product 3 on sale', 'http://product3.example.com');
                $tracker->doTrackContentImpression('Product 4');

                if ($this->faker->boolean(50)) {
                    $tracker->doTrackContentInteraction('click', 'Product 1', '/path/product1.jpg', 'http://product1.example.com');
                }

                if ($this->faker->boolean(35)) {
                    $tracker->doTrackContentInteraction('click', 'Product 1', 'Buy Product 1 Now!', 'http://product1.example.com');
                }

                if ($this->faker->boolean(60)) {
                    $tracker->doTrackContentInteraction('submit', 'Product 2', '/path/product2.jpg', $site['main_url'] . '/product2');
                }

                if ($this->faker->boolean(40)) {
                    $tracker->doTrackContentInteraction('click', 'Product 4');
                }
            }

            if ($this->faker->boolean(10)) {
                $i++;
                $tracker->doTrackEvent('Movies', 'play', 'Movie Name');

                if ($this->faker->boolean(50)) {
                    $tracker->doTrackEvent('Movies', 'stop', 'Movie Name');
                }
            }

            if ($this->faker->boolean(10)) {
                $i++;
                $tracker->doTrackEvent('Movies', 'play', 'Another Movie');

                if ($this->faker->boolean(50)) {
                    $tracker->doTrackEvent('Movies', 'stop', 'Another Movie');
                }
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

    private function getAnySuperUser()
    {
        // The visitor generator may be executed in the command line
        // in the command line there is token_auth set to the user running the command line
        // so we manually fetch a super user token_auth.
        $superUser = Db::get()->fetchRow("SELECT login, token_auth
                                          FROM " . Common::prefixTable("user") . "
                                          WHERE superuser_access = 1
                                          ORDER BY date_registered ASC");
        return $superUser;
    }

}
