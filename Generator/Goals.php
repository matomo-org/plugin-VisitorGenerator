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
use Piwik\Plugins\Goals\API as GoalsAPI;
use Piwik\Plugins\VisitorGenerator\Generator;
use Piwik\View;

class Goals extends Generator
{
    private $goals = array(
        array('name' => 'Download Software',  'match' => 'url', 'pattern' => 'download',   'patternType' => 'contains', 'revenue' => 0.10),
        array('name' => 'Download Software2', 'match' => 'url', 'pattern' => 'latest.zip', 'patternType' => 'contains', 'revenue' => 0.05),
        array('name' => 'Opens Contact Form', 'match' => 'url', 'pattern' => 'contact',    'patternType' => 'contains', 'revenue' => false),
        array('name' => 'Visit Docs',         'match' => 'url', 'pattern' => 'docs',       'patternType' => 'contains', 'revenue' => false),
    );

    public function generate($idSite)
    {
        $goalIds = array();

        foreach ($this->goals as $goal) {
            try {
                if ($this->hasGoal($idSite, $goal['name'])) {
                    continue;
                }

                $goalIds[] = $this->getApi()->addGoal($idSite, $goal['name'], $goal['match'], $goal['pattern'], $goal['patternType'], $caseSensitive = false, $goal['revenue'], $allowMultipleConversionsPerVisit = false);
            } catch (\Exception $e) {
                Log::debug('Failed to generate a goal for idSite ' . $idSite . ': ' . $e->getMessage());
            }
        }

        return $goalIds;
    }

    private function hasGoal($idSite, $goalName)
    {
        $existingGoals = $this->getApi()->getGoals($idSite);

        foreach ($existingGoals as $goal) {
            if ($goal['name'] == $goalName) {
                return true;
            }
        }

        return false;
    }

    private function getApi()
    {
        return GoalsAPI::getInstance();
    }
}
