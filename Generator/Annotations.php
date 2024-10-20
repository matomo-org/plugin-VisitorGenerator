<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Date;
use Piwik\Plugins\Annotations\API as AnnotationAPI;
use Piwik\Plugins\VisitorGenerator\Generator;

class Annotations extends Generator
{
    public function generate($idSite)
    {
        $date     = Date::now()->toString();
        $numWords = $this->faker->numberBetween(2, 5);
        $note     =  $this->faker->sentence($numWords);

        return $this->getApi()->add($idSite, $date, $note, $this->faker->boolean(50));
    }

    private function getApi()
    {
        return AnnotationAPI::getInstance();
    }
}
