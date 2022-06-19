<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator;

use Piwik\Plugin;

class VisitorGenerator extends Plugin
{
    public function registerEvents()
    {
        return [
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        ];
    }

    public function getClientSideTranslationKeys(&$result)
    {
        $result[] = 'VisitorGenerator_VisitorGenerator';
        $result[] = 'VisitorGenerator_PluginDescription';
        $result[] = 'VisitorGenerator_CliToolUsage';
        $result[] = 'VisitorGenerator_OverwriteLogFiles';
        $result[] = 'VisitorGenerator_DaysToCompute';
        $result[] = 'VisitorGenerator_GenerateFakeActions';
        $result[] = 'VisitorGenerator_AreYouSure';
        $result[] = 'VisitorGenerator_Warning';
        $result[] = 'VisitorGenerator_NotReversible';
        $result[] = 'VisitorGenerator_ChoiceYes';
        $result[] = 'VisitorGenerator_PleaseBePatient';
        $result[] = 'VisitorGenerator_LogImporterNote';
        $result[] = 'VisitorGenerator_Submit';
        $result[] = 'VisitorGenerator_GeneratedVisitsFor';
        $result[] = 'VisitorGenerator_NumberOfGeneratedActions';
        $result[] = 'VisitorGenerator_NbRequestsPerSec';
        $result[] = 'VisitorGenerator_AutomaticReprocess';
        $result[] = 'VisitorGenerator_ReRunArchiveScript';
    }
}
