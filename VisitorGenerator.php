<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class VisitorGenerator extends \Piwik\Plugin
{
    public function getListHooksRegistered()
    {
        return array(
            'Menu.Admin.addItems' => 'addMenu',
            'Console.addCommands' => 'addConsoleCommands'
        );
    }

    public function addMenu()
    {
        MenuAdmin::getInstance()->add(
            'CoreAdminHome_MenuDiagnostic', 'VisitorGenerator_VisitorGenerator',
            array('module' => 'VisitorGenerator', 'action' => 'index'),
            Piwik::hasUserSuperUserAccess(),
            $order = 20
        );
    }

    public function addConsoleCommands(&$commands)
    {
        $commands[] = __NAMESPACE__ . '\Commands\GenerateWebsites';
        $commands[] = __NAMESPACE__ . '\Commands\GenerateUsers';
        $commands[] = __NAMESPACE__ . '\Commands\GenerateGoals';
        $commands[] = __NAMESPACE__ . '\Commands\GenerateVisits';
        $commands[] = __NAMESPACE__ . '\Commands\AnonymizeLog';
    }
}
