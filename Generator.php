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

include_once __DIR__ . '/libs/Faker/autoload.php';

class Generator
{
    protected $faker;
    protected $piwikUrl;

    /**
     * @param null $piwikUrl
     */
    public function __construct($piwikUrl = null)
    {
        $this->faker = \Faker\Factory::create('en_EN');
        $this->faker->addProvider(new Faker\Request($this->faker));
        $this->setPiwikUrl($piwikUrl);
    }

    /**
     * @param $piwikUrl
     */
    protected function setPiwikUrl($piwikUrl) {
        $this->piwikUrl = $piwikUrl;
    }

    /**
     * @return string
     */
    protected function getPiwikUrl()
    {
        if($this->piwikUrl) {
            $url = $this->piwikUrl;
        } else {
            $url = SettingsPiwik::getPiwikUrl();
        }

        // this is a workaround when force_ssl=1, and the HTTPS URL is not fetchable from CLI
        $url = str_replace('https://localhost', 'http://localhost', $url);
        return $url;
    }

}
