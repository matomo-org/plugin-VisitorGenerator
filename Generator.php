<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator;

use Piwik\SettingsPiwik;
use Piwik\SettingsServer;

include_once __DIR__ . '/vendor/autoload.php';

class Generator
{
    protected $faker;
    protected $matomoUrl;
    protected $trackNonProfilable = false;

    /**
     * @param null $matomoUrl
     */
    public function __construct($matomoUrl = null)
    {
        $this->faker = \Faker\Factory::create('en_EN');
        $this->faker->addProvider(new Faker\Request($this->faker));
        $this->setMatomoUrl($matomoUrl);
    }

    protected function makeMatomoTracker($idSite)
    {
        if (SettingsServer::isMatomoForWordPress()) {
            $trackerFile = plugin_dir_path(MATOMO_ANALYTICS_FILE) . "tests/phpunit/framework/test-local-tracker.php";
            if (file_exists($trackerFile)) {
                include_once $trackerFile;
                return new \MatomoLocalTracker($idSite, $this->getMatomoUrl());
            } else {
                throw new \Exception('The visitor generator in Matomo for WordPress works only when the plugin is installed from Git and is only intended for development.');
            }
        }
        return new \MatomoTracker($idSite, $this->getMatomoUrl());
    }

    /**
     * @param $matomoUrl
     */
    protected function setMatomoUrl($matomoUrl)
    {
        $this->matomoUrl = $matomoUrl;
    }

    /**
     * @return string
     */
    protected function getMatomoUrl()
    {
        if ($this->matomoUrl) {
            $url = $this->matomoUrl;
        } else {
            $url = SettingsPiwik::getPiwikUrl();
        }

        // this is a workaround when force_ssl=1, and the HTTPS URL is not fetchable from CLI
        $url = str_replace('https://localhost', 'http://localhost', $url);
        return $url;
    }

    public function setTrackNonProfilable(bool $trackNonProfilable): void
    {
        $this->trackNonProfilable = $trackNonProfilable;
    }
}
