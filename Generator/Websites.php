<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Log;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Plugins\VisitorGenerator\Generator;
use Piwik\View;

class Websites extends Generator
{
    public function generate($limit)
    {
        $siteIds = array();

        for ($index = 0; $index < $limit; $index++) {
            try {
                $siteIds[] = SitesManagerAPI::getInstance()->addSite($this->faker->company, $this->faker->url);
            } catch (\Exception $e) {
                Log::debug('Failed to generate a site, probably a duplicate: ' . $e->getMessage());
            }
        }

        return $siteIds;
    }
}
