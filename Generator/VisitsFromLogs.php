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
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeAPI;
use Piwik\Plugins\VisitorGenerator\Faker\Request;
use Piwik\Plugins\VisitorGenerator\Generator;
use Piwik\Plugins\VisitorGenerator\LogParser;

/**
 * Replays all *.log files within the data directory.
 */
class VisitsFromLogs extends Generator
{
    private $authToken;

    /**
     * All log lines will be replayed having the same day of the month as the one of the given time. If the same day of
     * the month is not present in any log line then one of the following days will be used.
     *
     * @param string|boolean $time  If false, defaults to "now"
     * @param int $idSite
     * @param int $timeout
     *
     * @return int
     */
    public function generate($time = false, $idSite = 1, $timeout = 10)
    {
        if (empty($time)) {
            $time = time();
        }

        $date  = date("Y-m-d", $time);
        $count = 0;

        foreach ($this->getLogFiles() as $logFile) {

            $logParser = new LogParser($logFile);
            $logs      = $logParser->getParsedLogLines();

            $prefix     = $this->getMatomoUrl() . "matomo.php";
            $dayOfMonth = $this->findDayOfMonthToUseToMakeSureWeGenerateAtLeastOneVisit($time, $logs);

            $languages = Request::getAcceptLanguages();
            $numLanguages = count($languages);

            foreach ($logs as $index => $log) {
                if (!$this->isSameDayOfMonth($dayOfMonth, $log['time'])) {
                    continue;
                }

                $url  = $this->manipulateRequestUrl($log['time'], $idSite, $log['url'], $date, $log['ip'], $prefix);
                $lang = $languages[$index % $numLanguages];

                Http::sendHttpRequest($url, $timeout, $log['ua'], $path = null, $follow = 0, $lang);
                $count++;
            }
        }

        CoreAdminHomeAPI::getInstance()->invalidateArchivedReports($idSite, $date);

        return $count;
    }

    private function getLogFiles()
    {
        return Filesystem::globr(PIWIK_INCLUDE_PATH . '/plugins/*/data', '*.log');
    }

    protected function manipulateRequestUrl($time, $idSite, $url, $date, $ip, $prefix)
    {
        $start = strpos($url, 'matomo.php?') + strlen('matomo.php?');
        $url   = substr($url, $start);
        $ip    = strlen($ip) < 9 ? "13.5.111.3" : $ip;
        $datetime = $date . " " . Date::factory($time)->toString("H:i:s");

        // Force date/ip & authenticate
        $url .= "&cdt=" . urlencode($datetime);
        if (strpos($url, 'cip') === false) {
            $url .= "&cip=" . $ip;
        }

        $url .= "&token_auth=" . $this->getTokenAuth();
        $url  = $prefix . "?" . $url;

        // Make order IDs unique per day
        $url = str_replace("ec_id=", "ec_id=$date-", $url);

        // Disable provider plugin
        $url .= "&dp=1";

        $url = preg_replace("/idsite=[0-9]+/", "idsite=$idSite", $url);

        return $url;
    }

    private function getTokenAuth()
    {
        if (empty($this->authToken)) {
            $this->authToken = Piwik::requestTemporarySystemAuthToken('VistorGenerator', 24);
        }

        return $this->authToken;
    }

    private function isSameDayOfMonth($dayOfMonth, $timeToCheck)
    {
        return (int) $dayOfMonth === $this->getDayOfMonthFromTime($timeToCheck);
    }

    private function getDayOfMonthFromTime($time)
    {
        return (int) Date::factory($time)->toString('j');
    }

    private function findDayOfMonthToUseToMakeSureWeGenerateAtLeastOneVisit($time, $parsedLogs)
    {
        $dayOfMonth  = $this->getDayOfMonthFromTime($time);
        $daysInMonth = (int) Date::factory($time)->toString('t');

        $numTriedDays = 1;
        while (!$this->isDayOfMonthPresentInLogs($dayOfMonth, $parsedLogs) && $numTriedDays < 32) {
            $dayOfMonth = ($dayOfMonth + 1) % $daysInMonth;
            $numTriedDays++;
        }

        return $dayOfMonth;
    }

    private function isDayOfMonthPresentInLogs($dayOfMonth, $parsedLogs)
    {
        foreach ($parsedLogs as $log) {
            if ($this->isSameDayOfMonth($dayOfMonth, $log['time'])) {
                return true;
            }
        }

        return false;
    }

}
