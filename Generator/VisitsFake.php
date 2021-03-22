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
use Piwik\Plugin\Manager;
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

            if ($this->faker->boolean(3)) {
                $pageUrl .= (strpos($pageUrl, '?') ? '&' : '?') . http_build_query($this->faker->campaignParameters);
            }

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

            if ($this->faker->boolean(20)) {
                $this->trackMediaProgress($tracker);
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


    private function trackMediaProgress(\MatomoTracker $t)
    {
        if (!Manager::getInstance()->isPluginActivated('MediaAnalytics')) {
            return; // plugin not available
        }

        $type = $this->faker->randomElement(['audio', 'video']);

        if ($type == 'video') {
            $player = $this->faker->randomElement(['youtube', 'html5video', 'vimeo', 'jwplayer', 'video.js', 'paella-opencast', 'flowplayer']);
            $resolution = $this->faker->resolution;
            $width = floor($resolution[0] / 2);
            $height = floor($resolution[1] / 2);
            $fullscreen = (int) $this->faker->boolean(35);
            $file = $this->faker->videoFile;
        } else {
            $player = $this->faker->randomElement(['html5audio', 'jwplayer', 'paella-opencast', 'flowplayer']);
            $fullscreen = $width = $height = 0;
            $file = $this->faker->audioFile;
        }

        $resource   = $file[0];
        $mediaTitle = $file[1];
        $length     = $file[2];
        $idView     = $this->faker->unique()->regexify('[a-zA-Z0-9]{6}');

        // default values for media impression
        $timeToPlay = 0;
        $spentTime  = 0;
        $progress   = 0;
        $segments   = [];

        // track a media progress instead
        if ($this->faker->boolean(60)) {
            $timeToPlay = $this->faker->numberBetween(0, 300);
            // 60% are starting in the beginning
            $startProgress = $this->faker->boolean(60) ? 0 : $this->faker->numberBetween(1, 75);
            // ensure finish rate is at least 10%
            $progressPercent = $this->faker->boolean(10) ? 100 : $this->faker->numberBetween($startProgress, 100);

            for ($percent = $startProgress; $percent <= $progressPercent; $percent++) {
                $progress       = ceil($length * $percent / 100);
                $segmentDivider = $progress <= 300 ? 15 : 30;
                $segments[]     = ceil($progress / $segmentDivider) * $segmentDivider;

                if ($percent + 10 < $progressPercent && $this->faker->boolean(15)) {
                    $percent += 10; // randomly skip some segments
                }
            }

            $spentTime = ceil($length * $progressPercent / 100);
            $progress  = ceil($length * $progressPercent / 100);
        }

        $t->clearCustomTrackingParameters();

        $params = [
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_ID_VIEW              => $idView,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_MEDIA_TYPE           => $type,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_PLAYER_NAME          => $player,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_MEDIA_TITLE          => $mediaTitle,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_RESOURCE             => $resource,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_SPENT_TIME           => $spentTime,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_PROGRESS             => $progress,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_MEDIA_LENGTH         => $length,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_TIME_TO_INITIAL_PLAY => $timeToPlay,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_MEDIA_WIDTH          => $width,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_MEDIA_HEIGHT         => $height,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_FULLSCREEN           => $fullscreen,
            \Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_SEGMENTS             => implode(',', array_unique($segments)),
        ];
        foreach ($params as $name => $value) {
            $t->setCustomTrackingParameter($name, $value);
        }

        $t->storedTrackingActions[] = $t->getUrlTrackPageView('This does not appear as page view');
        $t->clearCustomTrackingParameters();
    }

    private function getCurrentSite($idSite)
    {
        return SitesManagerApi::getInstance()->getSiteFromId($idSite);
    }
}
