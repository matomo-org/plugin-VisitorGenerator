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

class VisitsFake extends Generator
{
    public function generate($time = false, $idSite = 1, $limit = 1000)
    {
        $date = date("Y-m-d", $time);

        $tracker = new \MatomoTracker(1, $this->getMatomoUrl());
        $tracker->setDebugStringAppend('dp=1');
        $tracker->enableBulkTracking();
        $tokenAuth = Piwik::requestTemporarySystemAuthToken('VistorGenerator', 24);
        $site = $this->getCurrentSite($idSite);

        $numSearches = rand(floor($limit / 40), ceil($limit / 20));
        $numSearchesDone = 0;

        $i = 0;
        $userId = null;
        while ($i < $limit) {
            $pageUrl = $this->faker->pageURL;

            $i++;
            $tracker->setTokenAuth($tokenAuth);
            $tracker->setUserAgent($this->faker->userAgent);
            $tracker->setBrowserLanguage($this->faker->locale);
            $tracker->setCity($this->faker->city);
            $countryCode = $this->faker->countryCode;
            $tracker->setCountry(strtolower($countryCode));
            $tracker->setRegion($this->faker->region($countryCode));
            $tracker->setLatitude($this->faker->latitude);
            $tracker->setLongitude($this->faker->longitude);
            $tracker->setIp($this->faker->boolean(77) ? $this->faker->ipv4 : $this->faker->ipv6);
            $tracker->setLocalTime($this->faker->time());
            $tracker->setIdSite($idSite);

            if ($this->faker->boolean(10)) {
                $tracker->setNewVisitorId();
                if ($this->faker->boolean(50)) {
                    $tracker->setUserId($this->faker->firstName);
                } else {
                    $tracker->setUserId(false);
                }
            }

            $resolution = $this->faker->resolution;
            $tracker->setResolution( $resolution[0], $resolution[1]);
            $tracker->setBrowserHasCookies($this->faker->boolean(90));
            $tracker->setPlugins($flash = $this->faker->boolean(90), $java = $this->faker->boolean(85), $quickTime = $this->faker->boolean(40), $realPlayer = $this->faker->boolean(10), $pdf = $this->faker->boolean(75), $windowsMedia = $this->faker->boolean(60), $silverlight = $this->faker->boolean(5));
            $tracker->setCustomVariable(1, 'gender', $this->faker->gender, 'visit');
            $tracker->setCustomVariable(2, 'age', $this->faker->randomDigit, 'visit');
            $tracker->setCustomVariable(3, 'languageCode', $this->faker->languageCode, 'visit');
            $tracker->setCustomVariable(1, 'tld', $this->faker->tld, 'page');
            $tracker->setCustomVariable(2, 'ean', $this->faker->numerify('########'), 'page');

            $tracker->setCustomDimension('1', $this->faker->gender);
            $tracker->setCustomDimension('2', $this->faker->randomDigit);
            $tracker->setCustomDimension('3', $this->faker->languageCode);
            $tracker->setCustomDimension('4', $this->faker->tld);
            $tracker->setCustomDimension('5', $this->faker->numerify('########'));

            $tracker->setPerformanceTimings(
                $this->faker->numberBetween(0, 199),
                $this->faker->numberBetween(400, 600),
                $this->faker->numberBetween(20, 250),
                $this->faker->numberBetween(500, 3000),
                $this->faker->numberBetween(250, 1500),
                $this->faker->numberBetween(10, 200)
            );

            $tracker->setForceVisitDateTime($date . ' ' . $this->faker->time('H:i:s'));
            $tracker->setUrlReferrer($this->faker->referrer);

            $tracker->setCustomTrackingParameter('bw_bytes', $this->faker->numberBetween(1, 2000000000));

            if (0 === strpos($pageUrl, 'http')) {
                $tracker->doTrackAction($pageUrl, 'link');
            } elseif (false !== strpos($pageUrl, 'zip')) {
                $tracker->setUrl($site['main_url'] . $pageUrl);
                $tracker->doTrackAction($site['main_url'] . $pageUrl, 'download');
            } else {
                $tracker->setUrl($site['main_url'] . $pageUrl);

                if ($this->faker->boolean(10)) {
                    $tracker->setEcommerceView($this->faker->productSku, $this->faker->productName, $this->faker->categories(5), $this->faker->randomNumber(2));
                }

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

            if ($this->faker->boolean(10)) {
                $price =  $this->faker->randomNumber(2);
                $quantity = $this->faker->numberBetween(1,4);

                $tracker->addEcommerceItem($this->faker->productSku, $this->faker->productName, $this->faker->categories(5), $price, $quantity);

                if ($this->faker->boolean(50)) {
                    $tracker->doTrackEcommerceCartUpdate(50);
                } else {
                    $subtotal = $price * $quantity;
                    $tax = $subtotal * 0.19;
                    $shipping = $subtotal * 0.05;
                    $discount = $subtotal * 0.10;
                    $grandTotal = $subtotal + $shipping + $tax - $discount;
                    $tracker->doTrackEcommerceOrder($this->faker->randomNumber(5), $grandTotal, $subtotal, $tax, $shipping, $discount);
                }
            }

            if ($numSearchesDone < $numSearches) {
                $tracker->doTrackSiteSearch($this->faker->word, $this->faker->searchEngine, $this->faker->numberBetween(0, 10));
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
}
