<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

include_once __DIR__ . '/libs/Faker/autoload.php';

class Generator
{
    protected $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create('en_EN');
        $this->faker->addProvider(new Faker\Request($this->faker));
    }
}
