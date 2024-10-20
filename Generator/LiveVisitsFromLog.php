<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Http;
use Piwik\Plugins\VisitorGenerator\Faker\Request;
use Piwik\Plugins\VisitorGenerator\Iterator\TransformIterator;
use Piwik\Plugins\VisitorGenerator\LogParser;
use Piwik\Log\LoggerInterface;

class LiveVisitsFromLog extends VisitsFromLogs
{
    public const SECONDS_IN_DAY = 86400;

    /**
     * @var \Iterator
     */
    private $logIterator;

    /**
     * @var int
     */
    private $idSite;

    /**
     * @var int
     */
    private $timeOfDay;

    /**
     * @var int
     */
    private $timeOfDayDelta;

    /**
     * @var int|null
     */
    private $dayOfMonth;

    /**
     * @var \SeekableIterator
     */
    private $fileIterator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string[]
     */
    private $languages;

    /**
     * @var int
     */
    private $languageIndex = 0;

    /**
     * @var int
     */
    private $timeout = 10;

    /**
     * @var int|null
     */
    private $initialWaitTime;

    public function __construct($logFile, $idSite, $timeOfDay, $timeOfDayDelta, $dayOfMonth = null, $matomoUrl = null, $timeout = 10)
    {
        parent::__construct($matomoUrl);

        $this->idSite = $idSite;
        $this->timeOfDay = $timeOfDay;
        $this->timeOfDayDelta = $timeOfDayDelta;
        $this->dayOfMonth = $dayOfMonth;
        $this->logger = StaticContainer::get(LoggerInterface::class);
        $this->languages = Request::getAcceptLanguages();

        $this->logIterator = $this->makeIterator($logFile);
        $this->logIterator->rewind();

        $this->initialWaitTime = $this->skipAheadToTimeOfDay();
        $this->timeout = $timeout;
    }

    public function tick()
    {
        if (!$this->logIterator->valid()) {
            return [null, null];
        }

        if ($this->initialWaitTime) {
            $initialWaitTime = $this->initialWaitTime;
            $this->initialWaitTime = null;
            return [0, $initialWaitTime];
        }

        $currentDate = date('Y-m-d');

        $count = 0;
        while ($this->logIterator->valid()) {
            $log = $this->logIterator->current();
            $this->track($log, $this->logIterator->key(), $currentDate);

            ++$count;

            $currentLogDateTimeOfDay = $this->getDateWithoutTimzeone($log['time'])->getTimestamp() % self::SECONDS_IN_DAY;

            // if no next log, stop waiting
            $this->logIterator->next();
            if (!$this->logIterator->valid()) {
                return [$count, null];
            }

            $nextLog = $this->logIterator->current();
            if (!$this->isForDayOfMonth($nextLog)) {
                return [$count, null];
            }

            $nextLogDateTimeOfDay = $this->getDateWithoutTimzeone($nextLog['time'])->getTimestamp() % self::SECONDS_IN_DAY;

            // if the next log's time is ahead of the time we started tracking, return the wait time
            if ($nextLogDateTimeOfDay > $currentLogDateTimeOfDay) {
                $waitTime = $nextLogDateTimeOfDay - $currentLogDateTimeOfDay;

                $this->logger->debug("wait time is {waitTime}s", [
                    'waitTime' => $waitTime,
                ]);

                return [$count, $waitTime];
            }

            if ($nextLogDateTimeOfDay < $currentLogDateTimeOfDay) {
                return [$count, null]; // if next log is for the next day, we're done replaying for now
            }
        }

        return [$count, null]; // no more logs
    }

    public function close()
    {
        $this->fileIterator = null;
    }

    public function getCurrentLineNumber()
    {
        return $this->fileIterator->key();
    }

    private function track($log, $lineNumber, $date)
    {
        $this->logger->debug("Tracking log on line {line}.", [
            'line' => $lineNumber,
        ]);

        $lang = $this->languages[$this->languageIndex % count($this->languages)];
        ++$this->languageIndex;

        $url = $this->manipulateRequestUrl($log['time'], $this->idSite, $log['url'], $date, $log['ip'], '');

        $queryStart = strpos($url, '?');
        $requestBody = substr($url, $queryStart + 1);

        Http::sendHttpRequestBy(
            Http::getTransportMethod(),
            $this->getMatomoUrl() . '/matomo.php',
            $this->timeout,
            $log['ua'],
            $path = null,
            $file = null,
            $follow = 0,
            $lang,
            $acceptInvalidSsl = false,
            $byteRange = false,
            $getExtendedInfo = false,
            $httpMethod = 'POST',
            $httpUsername = null,
            $httpPassword = null,
            $requestBody,
            $additionalHeaders = [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Content-Length' => strlen($requestBody),
            ]
        );
    }

    private function makeIterator($logFile)
    {
        // read log file lines
        $this->fileIterator = new \SplFileObject($logFile);

        // parse lines to logs
        $iterator = new TransformIterator($this->fileIterator, function ($line, $lineNumber) {
            $log = LogParser::parseLogLine($line);

            if (
                empty($log)
                && strpos($line, 'POST') === false
            ) {
                $this->logger->debug("Failed to parse line {lineNo}: {line}", [
                    'lineNo' => $lineNumber,
                    'line' => $line,
                ]);
            }

            return $log;
        });

        // filter failed parses
        $iterator = new \CallbackFilterIterator($iterator, function ($log) {
            return !empty($log);
        });

        // filter logs that do not have the correct day of month
        if ($this->dayOfMonth) {
            $iterator = new \CallbackFilterIterator($iterator, function ($log, $lineNumber) {
                $isForDayOfMonth = $this->isForDayOfMonth($log);

                if (!$isForDayOfMonth) {
                    $this->logger->debug("Log line {line} has incorrect day of month.", [
                        'line' => $lineNumber,
                    ]);
                }

                return $isForDayOfMonth;
            });
        }

        return $iterator;
    }

    private function isForDayOfMonth($log)
    {
        $date = $this->getDateWithoutTimzeone($log['time']);
        return $date->toString('j') == $this->dayOfMonth;
    }

    private function skipAheadToTimeOfDay()
    {
        while ($this->logIterator->valid()) {
            $log = $this->logIterator->current();

            $logTimeOfDay = Date::factory($log['time'])->getTimestamp() % self::SECONDS_IN_DAY;
            $distance = $logTimeOfDay - $this->timeOfDay;
            if ($distance >= 0 && $distance <= $this->timeOfDayDelta) {
                return $distance;
            }

            $this->logIterator->next();
        }

        return null;
    }

    private function getDateWithoutTimzeone($time)
    {
        $timeWithoutTimezone = explode(' ', $time);
        $timeWithoutTimezone = reset($timeWithoutTimezone) . ' +0000';
        return Date::factory($timeWithoutTimezone);
    }
}
