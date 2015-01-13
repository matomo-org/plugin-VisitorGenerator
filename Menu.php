<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        $menu->addDevelopmentItem(
            'VisitorGenerator_VisitorGenerator',
            array('module' => 'VisitorGenerator', 'action' => 'index'),
            Piwik::hasUserSuperUserAccess(),
            $order = 20
        );
    }
}
