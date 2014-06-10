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
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\Plugins\VisitorGenerator\Generator;
use Piwik\View;

class Users extends Generator
{
    public function generate($limit)
    {
        $userLogins = array();

        for ($index = 0; $index < $limit; $index++) {
            try {
                $login = $this->faker->userName;
                UsersManagerApi::getInstance()->addUser($login, 'secure', $this->faker->safeEmail);
                $userLogins[] = $login;
            } catch (\Exception $e) {
                Log::debug('Failed to generate a user, probably a duplicate: ' . $e->getMessage());
            }
        }

        return $userLogins;
    }
}
