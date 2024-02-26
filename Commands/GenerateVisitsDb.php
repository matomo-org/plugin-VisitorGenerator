<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use PDO;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Timer;
use Piwik\Config;
use Piwik\CliMulti\CliPhp;
use Piwik\Plugins\VisitorGenerator\Generator\VisitFakeQuery;
use Piwik\NumberFormatter;
use Piwik\Metrics\Formatter;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Process\Process as SymponyProcess;

class GenerateVisitsDb extends GenerateVisits
{

    private $verbosity = 0;
    private $pdo;
    private $prepareCache;
    /**
     * @var NumberFormatter
     */
    private $formatter;
    private $metricFormatter;
    private $actionsPoolSize = 1000000;
    private $siteGoals = [];

    protected function configure()
    {
        $this->setName('visitorgenerator:generate-visits-db');
        $this->setDescription('Generates many visits for a given amount of days in the past, directly inserted into the database. This command is intended for developers.');
        $this->addRequiredValueOption('idsite', null, 'Defines the site the visits should be generated for');
        $this->addRequiredValueOption('days', null, 'Defines for how many days in the past visits should be generated', 1);
        $this->addRequiredValueOption('start-date', null, 'Date to start generating on.');
        $this->addRequiredValueOption('limit-visits', null, 'Limits the number of generated visits', null);
        $this->addRequiredValueOption('limit-random-percent', null, 'Adjust the daily limit up or down by a random percent', 0);
        $this->addNoValueOption('v', null, "Minimal output verbosity.");
        $this->addNoValueOption('vv', null, "Medium output verbosity.");
        $this->addNoValueOption('vvv', null, "Maximum output verbosity.");
        $this->addNoValueOption('json-summary', null, "Output the final summary statistics in json.");
        $this->addRequiredValueOption('threads', null, 'Divide the task across multiple sub-processes', 1);
        $this->addRequiredValueOption('conversion-percent', null, 'The percent of visits that will include a conversion', 5);
        $this->addRequiredValueOption('min-actions', null, 'The minimum number of actions each visit will have, a random number will be chosen between min-actions and max-actions', 1);
        $this->addRequiredValueOption('max-actions', null, 'The maximum number of actions each visit will have, a random number will be chosen between min-actions and max-actions', 8);
        $this->addRequiredValueOption('actions-pool-size', null, 'Specifies the maxmium number of new random actions that will be created', 1000000);
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        // Setup formatters
        $translator = StaticContainer::get('Piwik\Translation\Translator');
        $this->formatter = new NumberFormatter($translator);
        $this->metricFormatter = new Formatter();

        // Get input options
        $input = $this->getInput();
        $output = $this->getOutput();
        $threads = $input->getOption('threads');
        $days = $this->checkDays();
        $idSite = $this->getIdSite();
        if ($input->getOption('v')) {
            $this->verbosity = 1;
        }
        if ($input->getOption('vv')) {
            $this->verbosity = 2;
        }
        if ($input->getOption('vvv')) {
            $this->verbosity = 3;
        }

        if ($threads > 1) {
            return $this->doThreadedRun($input, $output, $idSite, $days, $threads);
        }

        $this->actionsPoolSize = $input->getOption('actions-pool-size');
        $startDate = $input->getOption('start-date');
        if (empty($startDate) || $startDate == 'now') {
            $startTime = time();
        } else {
            $startTime = strtotime($startDate);
        }

        // Connect to db
        $this->prepareCache = [];
        $dsn = "mysql:host=" . Config::getInstance()->database['host'] . ";port=" . Config::getInstance()->database['port'] .
            ";charset=" . Config::getInstance()->database['charset'] . ";dbname=" . Config::getInstance()->database['dbname'];
        $this->pdo = new PDO($dsn, Config::getInstance()->database['username'], Config::getInstance()->database['password']);

        $goals = $this->query(['sql' => "SELECT idgoal FROM " . Common::prefixTable('goal') . " WHERE idsite = ? AND deleted = 0", 'bind' => [$idSite]], true);
        foreach ($goals as $g) {
            $this->siteGoals[] = $g->idgoal;
        }

        $time = $startTime - ($days - 1) * 86400;

        $visitsTotal = 0;
        $visitActionsTotal = 0;
        $actionsTotal = 0;
        $conversionsTotal = 0;
        $overallTimer = new Timer();

        $jsonSummary = $input->getOption('json-summary');
        if (!$jsonSummary) {
            $output->writeln("Generating data for " . $days . " days...");
        }

        while ($time <= $startTime) {

            $limit = $this->getLimitVisits();

            $stats = $this->generate($time, $idSite, $limit);
            $visitsTotal += $stats['visits'];
            $visitActionsTotal += $stats['visitActions'];
            $actionsTotal += $stats['actions'];
            $conversionsTotal += $stats['conversions'];

            if ($this->verbosity > 0) {
                $output->writeln([
                    sprintf("%s:", Date::factory($time)->toString()).
                    "  Visits: ".str_pad($this->formatter->format($stats['visits']), 8, ' ', STR_PAD_LEFT).
                    "  Visit Actions: ".str_pad($this->formatter->format($stats['visitActions']), 8, ' ', STR_PAD_LEFT).
                    "  Actions: ".str_pad($this->formatter->format($stats['actions']), 8, ' ', STR_PAD_LEFT).
                    "  Conversions: ".str_pad($this->formatter->format($stats['conversions']), 8, ' ', STR_PAD_LEFT)
                ]);
            }

            $time += 86400;
        }

        $summary =[
                'visits'        => $visitsTotal,
                'visitActions'  => $visitActionsTotal,
                'actions'       => $actionsTotal,
                'conversions'   => $conversionsTotal,
                'timeTaken'     => $overallTimer->getTime()
            ];

        if ($jsonSummary) {
            sleep(1);
            $output->writeln('|' . json_encode($summary));
        } else {
            $this->writeSummary($idSite, $summary);
        }

        return self::SUCCESS;
    }

    private function doThreadedRun(Input $input, Output $output, int $idSite, int $days, int $threads)
    {
        $overallTimer = new Timer();
        $command = $this->buildThreadCommand($input, $output, $idSite, $days, $threads);
        if (!$command) {
            return 1;
        }

        $grandSummary = [
            'visits'        => 0,
            'visitActions'  => 0,
            'actions'       => 0,
            'conversions'   => 0,
            'timeTaken'     => 0
            ];

        // Start threads
        $output->writeln($threads. " threads requested");
        $processList = [];
        $output->write("Starting threads");
        $threadsComplete = [];
        for ($t = 1; $t < $threads + 1; $t++) {

            if ($this->verbosity > 2) {
                $output->writeln(implode(' ', $command));
            }

            $process = new SymponyProcess($command);
            $processList[$t] = $process;
            $process->start(function ($type, $buffer) use ($threadsComplete, $output, &$grandSummary) {

                if (strpos($buffer, '|') !== false) {
                    // Record summary
                    $json = json_decode(trim($buffer, "| \n\r"),true);
                    $grandSummary['visits'] += $json['visits'];
                    $grandSummary['visitActions'] += $json['visitActions'];
                    $grandSummary['actions'] += $json['actions'];
                    $grandSummary['conversions'] += $json['conversions'];
                } else {
                    // Normal processing output
                    $output->write($buffer);
                }
            });
            $output->write(".");
        }
        $output->writeln(' [' . $threads . " threads were started]");
        $threadsAreRunning = true;

        // Monitor threads and wait for completion
        while ($threadsAreRunning) {
            foreach ($processList as $pid => $process) {
                if (!$process->isRunning()) {
                    $threadsComplete[$pid] = true;
                    if (count($threadsComplete) == $threads) {
                        $threadsAreRunning = false;
                    }
                }
            }
            sleep(1);
        }

        $grandSummary['timeTaken'] = $overallTimer->getTime();
        $this->writeSummary($idSite, $grandSummary);

        return 0;
    }

    /**
     * Build the command line used for thread sub-processes
     *
     * @param Input  $input
     * @param Output $output
     * @param int    $idSite
     * @param int    $days
     * @param int    $threads
     *
     * @return array|null
     */
    private function buildThreadCommand(Input $input, Output $output, int $idSite, int $days, int $threads): ?array
    {
        // Find php binary
        $cliPhp = new CliPhp();
        $phpBinary = $cliPhp->findPhpBinary();
        if (!$phpBinary) {
            $output->writeln('Failed to find PHP binary');
            return null;
        }
        $phpBinary = rtrim($phpBinary, ' -q');

        // Setup command
        $limit = $input->getOption('limit-visits');
        $command = [
            $phpBinary,
            PIWIK_DOCUMENT_ROOT . '/console',
            'visitorgenerator:generate-visits-db',
            '--json-summary', // Return stats in json so we can combine into a grand total for all threads
            '--idsite=' . $idSite,
            '--days=' . $days
            ];

        $randomPercent = $input->getOption('limit-random-percent');
        if ($randomPercent) {
            $command[] = '--limit-random-percent=' . $randomPercent;
        }

        $conversionPercent = $input->getOption('conversion-percent');
        if ($conversionPercent) {
            $command[] = '--conversion-percent=' . $conversionPercent;
        }

        $minActions = $input->getOption('min-actions');
        if ($minActions) {
            $command[] = '--min-actions=' . $minActions;
        }

        $maxActions = $input->getOption('max-actions');
        if ($maxActions) {
            $command[] = '--max-actions=' . $maxActions;
        }

        $actionsPoolSize = $input->getOption('actions-pool-size');
        if ($actionsPoolSize) {
            $command[] = '--actions-pool-size=' . $actionsPoolSize;
        }

        $startDate = $input->getOption('start-date');
        if ($startDate) {
            $command[] = '--start-date=' . $startDate;
        }

        switch($this->verbosity) {
            case 1: $command[] = '--v'; break;
            case 2: $command[] = '--vv'; break;
            case 3: $command[] = '--vvv'; break;
            default: break;
        }

        // Split workload simplistically by dividing the limit across all threads
        $command[] = '--limit-visits=' . ($limit / $days / $threads);

        return $command;
    }

    /**
     * Write the final summary statistics message
     *
     * @param int   $idSite
     * @param array $summaryInfo
     *
     * @return void
     */
    private function writeSummary(int $idSite, array $summaryInfo): void
    {
        $summary = [
            'Site Id                  ' .str_pad($idSite, 12, ' ', STR_PAD_LEFT),
            'Time taken               ' . str_pad($this->metricFormatter->getPrettyTimeFromSeconds($summaryInfo['timeTaken'], true), 12, ' ', STR_PAD_LEFT),
            'Visits generated         ' . str_pad($this->formatter->format($summaryInfo['visits']), 12, ' ', STR_PAD_LEFT),
            'Visits actions generated ' . str_pad($this->formatter->format($summaryInfo['visitActions']), 12, ' ', STR_PAD_LEFT),
            'Actions generated        ' . str_pad($this->formatter->format($summaryInfo['actions']), 12, ' ', STR_PAD_LEFT),
            'Conversions generated    ' . str_pad($this->formatter->format($summaryInfo['conversions']), 12, ' ', STR_PAD_LEFT),
            'Visits per second        ' . str_pad($this->formatter->format(round($summaryInfo['visits'] / $summaryInfo['timeTaken'], 0)), 12, ' ', STR_PAD_LEFT) . " / sec",
            'Queries per second       ' . str_pad($this->formatter->format(round(($summaryInfo['visits'] + $summaryInfo['visitActions'] + $summaryInfo['actions'] +
                        $summaryInfo['conversions']) / $summaryInfo['timeTaken'], 0)), 12, ' ', STR_PAD_LEFT) . ' / sec'
            ];

        $this->writeSuccessMessage($summary);
    }

    /**
     * Return the number of visits to be created for a day, includes randomization and default values if required
     *
     * @return int
     */
    private function getLimitVisits(): int
    {
        $input = $this->getInput();
        if ($input->getOption('limit-visits')) {
            $limit = $input->getOption('limit-visits');
            $randomPercent = $input->getOption('limit-random-percent');
            if ($randomPercent > 0) {
                $limit = rand(floor($limit - ($limit * ($randomPercent / 100))), ceil($limit + ($limit * ($randomPercent / 100))));
            }
            return $limit;
        }
        return rand(400, 1000);
    }

    /**
     * Generates visits and related data for a specific day
     *
     * @param int $time     Start time of the day for visits to be generated
     * @param int $idSite   Site for which to generate the visits
     * @param int $limit    Number of visits to generate
     *
     * @return array        Statistics array for the data that was generated
     *                      ['visits' => 0, 'visitActions' => 0, 'actions' => 0, 'conversions' => 0]
     * @throws \Exception
     */
    private function generate(int $time, int $idSite, int $limit): array
    {
        $input = $this->getInput();
        $output = $this->getOutput();
        $conversionPercent = $input->getOption('conversion-percent');
        $actionCountMin = $input->getOption('min-actions');
        $actionCountMax = $input->getOption('max-actions');

        $requestCount = 0;
        $queryGenerator = new VisitFakeQuery($this->actionsPoolSize);

        if ($this->verbosity == 0) {
            $output->write(".");
        }

        $stats = [
            'visits' => 0,
            'visitActions' => 0,
            'actions' => 0,
            'conversions' => 0
            ];

        while ($requestCount < $limit || $limit < 0) {

            $this->query(['sql' => 'START TRANSACTION', 'bind' => []]);
            // Choose a random timestamp within supplied date range
            $timestampUTC = rand($time, $time + 86000); // no new visits will start for 400 seconds before midnight

            // Create actions for the visit
            $idactions = [];
            $actionUrl = '';
            $actionCount = rand($actionCountMin, $actionCountMax);
            for ($i = 0; $i != $actionCount; $i++) {

                // Get random action, 50% chance of being new until pool is full, then always an existing action
                $actionUrl = $queryGenerator->getRandomActionURL();

                // Check if the action exists in the db, create new action if not
                $findActionQuery = $queryGenerator->getCheckActionExistsQuery($actionUrl);
                $actionRows = $this->query($findActionQuery, true);
                if (count($actionRows) === 0) {
                    if ($this->verbosity === 3) {
                        $output->writeln("New action, doing insert...");
                    }
                    $stats['actions']++;

                    // Insert new action
                    $insertActionQuery = $queryGenerator->getInsertActionQuery($actionUrl);
                    $this->query($insertActionQuery, false);
                    $idactions[] = $this->pdo->lastInsertId();
                } else {
                    $idactions[] = $actionRows[0]->idaction;
                }
            }
            $stats['visitActions'] += $actionCount;

            // Get random visitor id (10% chance of a returning visitor id)
            $idvisitor = $queryGenerator->getVisitor(10);
            if ($this->verbosity == 3) {
                $output->writeln("Got idvisitor '".bin2hex($idvisitor)."'");
            }

            // Check if visit exists in db, create new visit if not
            $findVisitorQuery = $queryGenerator->getCheckIfNewVisitorQuery($idvisitor, $idSite);
            $visitorRows = $this->query($findVisitorQuery, true);

            if (count($visitorRows) == 0) {

                if ($this->verbosity == 3) {
                    $output->write("New visitor, doing insert...");
                }

                // Insert new visit
                $insertVisitorQuery = $queryGenerator->getInsertVisitorQuery($idvisitor, reset($idactions), $timestampUTC, $idSite);
                $visitorRows = $this->query($insertVisitorQuery, false);
                $idvisit = $this->pdo->lastInsertId();
                if ($this->verbosity == 3) {
                    $output->writeln($idvisit);
                }
            } else {
                $idvisit = $visitorRows[0]->idvisit;

                // Update random visit time to always be after an existing visit's first action time
                $visitFirstTime = strtotime($visitorRows[0]->visit_first_action_time);
                $timestampUTC = rand($visitFirstTime, $time + 86000);

                if ($this->verbosity == 3) {
                    $output->writeln("Existing visitor, updating...");
                }

                // Update visit
                $updateVisitQuery = $queryGenerator->getUpdateVisitQuery($idvisit, $visitorRows[0]->visit_first_action_time, $timestampUTC, $idSite);
                $this->query($updateVisitQuery, false);

            }
            $stats['visits']++;

            if ($this->verbosity == 3) {
                $output->writeln("idvisit is " . $idvisit);
            }

            // Insert the action link(s)
            $idlinkva = null;
            foreach($idactions as $idaction) {
                if ($idvisit && $idaction) {

                    if ($this->verbosity == 3) {
                        $output->writeln("Inserting action link...");
                    }

                    // Random time between visit time and end of day
                    $actionTime = rand($timestampUTC, $timestampUTC + ((($timestampUTC + 86399) - $time)));

                    $insertActionLinkQuery = $queryGenerator->getInsertActionLinkQuery($idvisitor, $idvisit, $idaction, $actionTime, $idSite);
                    $this->query($insertActionLinkQuery, false);
                    $idlinkva = $this->pdo->lastInsertId();
                }
            }

            // TODO support multiple conversions in one visit?
            // Insert conversion (conversion will always use the last idlinkva if there are multiple actions
            if ($idlinkva && $conversionPercent > 0 && (rand(0, 100) < $conversionPercent)) {
                if ($this->verbosity == 3) {
                    $output->writeln("Inserting conversion...");
                }
                $insertConversionQuery = $queryGenerator->getInsertConversionQuery($idvisitor, $idvisit, end($idactions), $actionUrl, $timestampUTC,
                    $idlinkva, $this->siteGoals[array_rand($this->siteGoals)], $idSite);
                $this->query($insertConversionQuery, false);
                $stats['conversions']++;
            }
            $this->query(['sql' => 'COMMIT', 'bind' => []]);
            $requestCount++;
        }

        return $stats;
    }

    /**
     * Wraps executed queries so that a prepare cache can be used
     *
     * @param array $query      Array containing the query and bind parameters ['sql' => 'select ...', 'bind' => []]
     * @param bool  $result     If set to true then the query result will be returned
     *
     * @return array|null
     */
    private function query(array $query, $result = false): ?array
    {
        if (isset($this->prepareCache[$query['sql']])) {
            if (!is_array($query['bind'])) {
                $q['bind'] = array($query['bind']);
            }
            $stmt = $this->prepareCache[$query['sql']];
        } else {
            $stmt = $this->pdo->prepare($query['sql']);
            $this->prepareCache[$query['sql']] = $stmt;
        }

        $stmt->execute($query['bind']);
        if ($result) {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
        return null;
    }

}
