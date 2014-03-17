<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Date;
use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugins\VisitorGenerator\Generator;
use Piwik\SettingsPiwik;
use Piwik\View;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeAPI;

/**
 * TODO once we have more logs (eg ecommerce etc) read all logs from data dir and replay them, not just access.log
 */
class VisitsFromLogs extends Generator
{
    public function generate($time = false, $idSite = 1)
    {
        $logs = $this->getAccessLog();
        if (empty($time)) $time = time();
        $date = date("Y-m-d", $time);

        $acceptLanguages = $this->getAcceptLanguages();

        $prefix = SettingsPiwik::getPiwikUrl() . "piwik.php";
        $count = 0;
        foreach ($logs as $log) {
            if (!preg_match('/^(\S+) \S+ \S+ \[(.*?)\] "GET (\S+.*?)" \d+ \d+ "(.*?)" "(.*?)"/', $log, $m)) {
                continue;
            }

            $ip   = $m[1];
            $time = $m[2];
            $url  = $m[3];
            $referrer = $m[4];
            $ua   = $m[5];

            $url = $this->manipulateRequestUrl($time, $idSite, $url, $date, $ip, $prefix);

            $acceptLanguage = $acceptLanguages[$count % count($acceptLanguages)];

            if ($output = Http::sendHttpRequest($url, $timeout = 5, $ua, $path = null, $follow = 0, $acceptLanguage)) {
                $count++;
            }
        }

        CoreAdminHomeAPI::getInstance()->invalidateArchivedReports($idSite, $date);

        return $count;
    }

    public function getAccessLogPath()
    {
        return PIWIK_INCLUDE_PATH . "/plugins/VisitorGenerator/data/access.log";
    }

    public function getAccessLog()
    {
        $log = file($this->getAccessLogPath());
        return $log;
    }

    private function getAcceptLanguages()
    {
        return array(
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
    }

    private function manipulateRequestUrl($time, $idSite, $url, $date, $ip, $prefix)
    {
        $start = strpos($url, 'piwik.php?') + strlen('piwik.php?');
        $url   = substr($url, $start, strrpos($url, " ") - $start);
        $ip    = strlen($ip) < 10 ? "13.5.111.3" : $ip;
        $datetime = $date . " " . Date::factory($time)->toString("H:i:s");

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

        return $url;
    }

}
