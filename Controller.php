<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

use Piwik\Access;
use Piwik\ArchiveProcessor\Rules;
use Piwik\Common;
use Piwik\Date;
use Piwik\Http;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeAPI;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\SettingsPiwik;
use Piwik\SettingsServer;
use Piwik\Site;
use Piwik\Timer;
use Piwik\Url;
use Piwik\View;

/**
 *
 */
class Controller extends ControllerAdmin
{
    public function index()
    {
        Piwik::checkUserHasSuperUserAccess();

        $sitesList = SitesManagerAPI::getInstance()->getSitesWithAdminAccess();

        $view = new View('@VisitorGenerator/index');
        $this->setBasicVariablesView($view);
        $view->assign('sitesList', $sitesList);
        $view->nonce = Nonce::getNonce('VisitorGenerator.generate');
        $view->countActionsPerRun = count($this->getAccessLog());
        $view->accessLogPath = $this->getAccessLogPath();
        return $view->render();
    }

    private function getAccessLogPath()
    {
        return PIWIK_INCLUDE_PATH . "/plugins/VisitorGenerator/data/access.log";
    }

    private function getAccessLog()
    {
        $log = file($this->getAccessLogPath());
        return $log;
    }

    public function generate()
    {
        Piwik::checkUserHasSuperUserAccess();
        $nonce = Common::getRequestVar('form_nonce', '', 'string', $_POST);
        if (Common::getRequestVar('choice', 'no') != 'yes' ||
            !Nonce::verifyNonce('VisitorGenerator.generate', $nonce)
        ) {
            Piwik::redirectToModule('VisitorGenerator', 'index');
        }
        Nonce::discardNonce('VisitorGenerator.generate');

        $daysToCompute = Common::getRequestVar('daysToCompute', false, 'int');

        if ($daysToCompute < 1) {
           throw new \Exception('Days to compute must be greater or equal to 1.');
        }

        SettingsServer::setMaxExecutionTime(0);

        $idSite = Common::getRequestVar('idSite', false, 'string', $_POST);

        if ('all' == $idSite) {
            $idSites  = SitesManagerAPI::getInstance()->getSitesIdWithAtLeastViewAccess();
            $siteName = Piwik::translate('General_MultiSitesSummary');
        } else {
            // get idSite from POST with fallback to GET
            $idSite   = Common::getRequestVar('idSite', false, 'int', $_GET);
            $idSite   = Common::getRequestVar('idSite', $idSite, 'int', $_POST);
            $idSites  = array($idSite);
            $siteName = Site::getNameFor($idSite);
        }


        $timer = new Timer;
        $time  = time() - ($daysToCompute - 1) * 86400;

        $nbActionsTotal = 0;
        $dates = array();
        while ($time <= time()) {

            foreach ($idSites as $idSite) {
                $nbActionsTotal += $this->generateVisits($time, $idSite);
            }

            $dates[] = date("Y-m-d", $time);
            $time += 86400;
        }

        foreach ($idSites as $idSite) {
            CoreAdminHomeAPI::getInstance()->invalidateArchivedReports($idSite, implode($dates, ","));
        }

        $browserArchiving = Rules::isBrowserTriggerEnabled();

        // Init view
        $view = new View('@VisitorGenerator/generate');
        $this->setBasicVariablesView($view);
        $view->assign('browserArchivingEnabled', $browserArchiving);
        $view->assign('timer', $timer);
        $view->assign('days', $daysToCompute);
        $view->assign('nbActionsTotal', $nbActionsTotal);
        $view->assign('nbRequestsPerSec', round($nbActionsTotal / $timer->getTime(), 0));
        $view->assign('siteName', $siteName);
        return $view->render();
    }

    private function generateVisits($time = false, $idSite = 1)
    {
        $logs = $this->getAccessLog();
        if (empty($time)) $time = time();
        $date = date("Y-m-d", $time);

        $acceptLanguages = array(
            "el,fi;q=0.5",
            "de-de,de;q=0.8,en-us",
            "pl,en-us;q=0.7,en;q=",
            "zh-cn",
            "fr-ca",
            "en-us",
            "en-gb",
            "fr-be",
            "fr,de-ch;q=0.5",
            "fr",
            "fr-ch",
            "fr",
        );
        $prefix = SettingsPiwik::getPiwikUrl() . "piwik.php";
        $count = 0;
        foreach ($logs as $log) {
            if (!preg_match('/^(\S+) \S+ \S+ \[(.*?)\] "GET (\S+.*?)" \d+ \d+ "(.*?)" "(.*?)"/', $log, $m)) {
                continue;
            }
            $ip = $m[1];
            $time = $m[2];
            $url = $m[3];
            $referrer = $m[4];
            $ua = $m[5];

            $start = strpos($url, 'piwik.php?') + strlen('piwik.php?');
            $url = substr($url, $start, strrpos($url, " ") - $start);
            $datetime = $date . " " . Date::factory($time)->toString("H:i:s");
            $ip = strlen($ip) < 10 ? "13.5.111.3" : $ip;

            // Force date/ip & authenticate
            $url .= "&cdt=" . urlencode($datetime);
            if (strpos($url, 'cip') === false) {
                $url .= "&cip=" . $ip;
            }
            $url .= "&token_auth=" . Piwik::getCurrentUserTokenAuth();
            $url = $prefix . "?" . $url;

            // Make order IDs unique per day
            $url = str_replace("ec_id=", "ec_id=$date-", $url);

            // Disable provider plugin
            $url .= "&dp=1";

            // Replace idsite
            $url = preg_replace("/idsite=[0-9]+/", "idsite=$idSite", $url);

            $acceptLanguage = $acceptLanguages[$count % count($acceptLanguages)];

            if ($output = Http::sendHttpRequest($url, $timeout = 5, $ua, $path = null, $follow = 0, $acceptLanguage)) {
                $count++;
            }
        }
        return $count;
    }
}
