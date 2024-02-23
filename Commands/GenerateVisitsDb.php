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
use Piwik\Date;
use Piwik\Timer;
use Piwik\Config;
use Piwik\Plugins\VisitorGenerator\Generator\VisitFakeQuery;

class GenerateVisitsDb extends GenerateVisits
{

    private $verbosity = 0;

    protected function configure()
    {
        $this->setName('visitorgenerator:generate-visits-db');
        $this->setDescription('Generates many visits for a given amount of days in the past, directly inserted into the database. This command is intended for developers.');
        $this->addRequiredValueOption('idsite', null, 'Defines the site the visits should be generated for');
        $this->addRequiredValueOption('days', null, 'Defines for how many days in the past visits should be generated', 1);
        $this->addRequiredValueOption('start-date', null, 'Date to start generating on.');
        $this->addRequiredValueOption('limit-visits', null, 'Limits the number of generated visits', null);
        $this->addRequiredValueOption('limit-random-percent', null, 'Adjust the daily limit up or down by a random percent', 0);
        $this->addNoValueOption('non-profilable', null, "If supplied, tracks data without visitor IDs so it will be considered 'not profilable'.");
        $this->addNoValueOption('v', null, "Minimal output verbosity.");
        $this->addNoValueOption('vv', null, "Medium output verbosity.");
        $this->addNoValueOption('vvv', null, "Maximum output verbosity.");
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $output = $this->getOutput();
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
        $timer = new Timer();

        $startDate = $input->getOption('start-date');
        if (empty($startDate) || $startDate == 'now') {
            $startTime = time();
        } else {
            $startTime = strtotime($startDate);
        }

        $time = $startTime - ($days - 1) * 86400;

        $visitsTotal = 0;
        $visitActionsTotal = 0;
        $actionsTotal = 0;
        $conversionsTotal = 0;

        $output->writeln("Generating data for " . $days . " days...");
        while ($time <= $startTime) {

            $limit = $this->getLimitVisits();

            // use Visits Fake
            //$fakeVisits = new VisitsFake(null, true);
            //$fakeVisits->setTrackNonProfilable($trackNonProfilable);
            //$nbActionsTotal += $fakeVisits->generate($time, $idSite, $limit);

            $stats = $this->generate($time, $idSite, $limit);
            $visitsTotal += $stats['visits'];
            $visitActionsTotal += $stats['visitActions'];
            $actionsTotal += $stats['actions'];
            $conversionsTotal += $stats['conversions'];

            $output->writeln(array(
                sprintf("%s:", Date::factory($time)->toString()) .
                " Visits: " . str_pad($stats['visits'], 10) .
                " Visit Actions: " . str_pad($stats['visitActions'], 10) .
                " Actions: " . str_pad($stats['actions'], 10) .
                " Conversions: " . str_pad($stats['conversions'], 10)
            ));


            $time += 86400;
        }

        $this->writeSuccessMessage([
            'idsite = ' . $idSite,
            $visitsTotal . ' Visits generated',
            $visitActionsTotal . ' Visits actions generated',
            $actionsTotal . ' Actions generated',
            $conversionsTotal . ' Conversions generated',
            round($visitsTotal / $timer->getTime(), 0) . ' visits per second',
            round(($visitsTotal + $visitActionsTotal + $actionsTotal + $conversionsTotal) / $timer->getTime(), 0) . ' queries per second'
        ]);

        return self::SUCCESS;
    }

    private function getLimitVisits()
    {
        $input = $this->getInput();
        if ($input->getOption('limit-visits')) {
            $limit =  $input->getOption('limit-visits');
            $randomPercent = $input->getOption('limit-random-percent');
            if ($randomPercent > 0) {
                $limit = rand(floor($limit - ($limit * ($randomPercent / 100))), ceil($limit + ($limit * ($randomPercent / 100))));
            }
            return $limit;
        }
        return rand(400, 1000);
    }

    /**
     * @param int $time     Start time of the day for visits to be generated
     * @param     $idSite
     * @param     $limit
     *
     * @return array
     * @throws \Exception
     */
    private function generate(int $time, $idSite, $limit): array
    {

        $conversionPercent = 5; // TODO make a parameter
        $actionCountMin = 1; // TODO Make a parameter?
        $actionCountMax = 8; // TODO Make a parameter?
        $goalMin = 1;
        $goalMax = 10;

        $dsn = "mysql:host=" . Config::getInstance()->database['host'] . ";port=" . Config::getInstance()->database['port'] .
            ";charset=" . Config::getInstance()->database['charset'] . ";dbname=" . Config::getInstance()->database['dbname'];
        $pdo = new PDO($dsn, Config::getInstance()->database['username'], Config::getInstance()->database['password']);

        $output = $this->getOutput();

        $prepareCache = [];
        $requestCount = 0;
        $queryGenerator = new VisitFakeQuery();

        if ($this->verbosity > 0) {
            $output->write("Generating requests.");
            if ($this->verbosity > 1) {
                $output->writeln("..");
            }
        }

        $stats = [
            'visits' => 0,
            'visitActions' => 0,
            'actions' => 0,
            'conversions' => 0
            ];

        $lastTimeSample = microtime(true);
        $lastCount = 0;
        while ($requestCount < $limit || $limit < 0) {

            $this->query($prepareCache, $pdo, ['sql' => 'START TRANSACTION', 'bind' => []]);
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
                $actionRows = $this->query($prepareCache, $pdo, $findActionQuery, true);
                if (count($actionRows) === 0) {
                    if ($this->verbosity === 3) {
                        $output->writeln("New action, doing insert...");
                    }
                    $stats['actions']++;

                    // Insert new action
                    $insertActionQuery = $queryGenerator->getInsertActionQuery($actionUrl);
                    $visitorRows = $this->query($prepareCache, $pdo, $insertActionQuery, false);
                    $idactions[] = $pdo->lastInsertId();
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
            $visitorRows = $this->query($prepareCache, $pdo, $findVisitorQuery, true);

            if (count($visitorRows) == 0) {

                if ($this->verbosity == 3) {
                    $output->write("New visitor, doing insert...");
                }

                // Insert new visit
                $insertVisitorQuery = $queryGenerator->getInsertVisitorQuery($idvisitor, reset($idactions), $timestampUTC, $idSite);
                $visitorRows = $this->query($prepareCache, $pdo, $insertVisitorQuery, false);
                $idvisit = $pdo->lastInsertId();
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
                $this->query($prepareCache, $pdo, $updateVisitQuery, false);

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
                    $this->query($prepareCache, $pdo, $insertActionLinkQuery, false);
                    $idlinkva = $pdo->lastInsertId();
                }
            }

            // TODO support multiple conversions in one visit?
            // Insert conversion (conversion will always use the last idlinkva if there are multiple actions
            if ($idlinkva && $conversionPercent > 0 && (rand(0, 100) < $conversionPercent)) {
                $idgoal = rand($goalMin, $goalMax);
                if ($this->verbosity == 3) {
                    $output->writeln("Inserting conversion...");
                }

                $insertConversionQuery = $queryGenerator->getInsertConversionQuery($idvisitor, $idvisit, end($idactions), $actionUrl, $timestampUTC,
                    $idlinkva, $idgoal, $idSite);
                $this->query($prepareCache, $pdo, $insertConversionQuery, false);
                $stats['conversions']++;
            }
            $this->query($prepareCache, $pdo, ['sql' => 'COMMIT', 'bind' => []]);
            $requestCount++;

        }

        return $stats;
    }

    private function query(&$prepareCache, $pdo, $q, $result = false, $usePrepareCache = true)
    {
        if ($usePrepareCache && isset($prepareCache[$q['sql']])) {
            if (!is_array($q['bind'])) {
                $q['bind'] = array($q['bind']);
            }
            $stmt = $prepareCache[$q['sql']];
        } else {
            $stmt = $pdo->prepare($q['sql']);
            $prepareCache[$q['sql']] = $stmt;
        }

        $stmt->execute($q['bind']);
        if ($result) {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
    }

}
