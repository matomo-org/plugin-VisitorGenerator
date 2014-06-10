<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitorGenerator;

class LogParser
{
    private $file = array();

    /**
     * An absoulte path to a log file that should be parsed.
     *
     * @param string $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Get the raw log lines of all files. Will contain even empty lines and comments
     *
     * @return string[]
     */
    public function getLogLines()
    {
        $logs = file($this->file);

        return $logs;
    }

    /**
     * Get all log lines separated into ip, time, url, referrer and user agent. Empty lines and comments won't be
     * returned.
     *
     * @return array[]
     */
    public function getParsedLogLines()
    {
        $parsedLines = array();

        $lines = $this->getLogLines();
        foreach ($lines as $line) {
            $parsed = self::parseLogLine($line);

            if (!empty($parsed)) {
                $parsedLines[] = $parsed;
            }
        }

        return $parsedLines;
    }

    /**
     * Parses a single raw log line into ip, time, url, referrer and user agent. Returns an empty array if it is not a
     * valid log line.
     *
     * @param  string $log
     * @return array
     */
    public static function parseLogLine($log)
    {
        if (!preg_match('/^(\S+) \S+ \S+ \[(.*?)\] "GET (\S+.*?)" \d+ \d+ "(.*?)" "(.*?)"/', $log, $m)) {
            return array();
        }

        return array(
            'ip'       => $m[1],
            'time'     => $m[2],
            'url'      => $m[3],
            'referrer' => $m[4],
            'ua'       => $m[5],
        );
    }
}
