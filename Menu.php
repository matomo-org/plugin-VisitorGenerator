<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\SettingsServer;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            if (SettingsServer::isMatomoForWordPress()) {
                $menu->addSystemItem(
                    'VisitorGenerator_VisitorGenerator',
                    ['module' => 'VisitorGenerator', 'action' => 'index'],
                    $order = 20
                );
            } else {
                $menu->addDevelopmentItem(
                    'VisitorGenerator_VisitorGenerator',
                    ['module' => 'VisitorGenerator', 'action' => 'index'],
                    $order = 20
                );
            }
        }
    }
}
