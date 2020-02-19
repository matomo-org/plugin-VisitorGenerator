<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

use Piwik\SettingsPiwik;
use Piwik\Config;

include_once __DIR__ . '/vendor/autoload.php';

class Generator
{
    protected $faker;
    protected $matomoUrl;

    /**
     * @param null $matomoUrl
     */
    public function __construct($matomoUrl = null)
    {
        $this->faker = \Faker\Factory::create('en_EN');
        $this->faker->addProvider(new Faker\Request($this->faker));
        $this->setMatomoUrl($matomoUrl);
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

        if ($this->useHTTP() && $this->hasHTTPS($url)) {
            $url = str_replace('https://', 'http://', $url);
        }

        return $url;
    }


    /**
     * Should we use http on requests?
     *
     * @return bool  True if checks enabled; false otherwise
     */
    protected function useHTTP()
    {
        if (isset(Config::getInstance()->VisitorGenerator['use_http'])) {
            if (Config::getInstance()->VisitorGenerator['use_http'] == 1) {
                return true;
            }
        }
        return false;
    }


    /**
     * Should we use http on requests?
     *
     * @return bool  True if checks enabled; false otherwise
     */
    protected function hasHTTPS($url)
    {
        $parsed_url = parse_url($url);
        if ($parsed_url['scheme'] == 'https') {
            return true;
        }
    }
}
