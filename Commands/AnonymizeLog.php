<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use Piwik\Common;
use Piwik\Filesystem;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\LogParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnonymizeLog extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:anonymize-log');
        $this->setHelp('Example usage:
<comment>./console visitorgenerator:anonymize-log --replace MyCompanyName:Example --replace MyTitle:ExampleTitle /path/to/file.log</comment>

This will read the log file, replace all occurrences of "MyCompanyName" with "Example" and "MyTitle" with "ExampleTitle".
It will replace the last 2 bits of all IP addresses with "0" and replace domains with "*.example.org".

<comment>./console visitorgenerator:anonymize-log --pluginname=CustomVariables /path/to/file.log</comment>

This will anonymize the log file and place the log in the plugins/CustomVariables/data directory. The data directory will be created if needed.
');
        $this->setDescription('Anonymizes an Apache log file by anonymizing IPs and domains. It will not replace any search terms, paths or url queries. The original file will not be altered.');
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the log file. Either an absolute path or a path relative to the Piwik directory');
        $this->addOption('replace', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Words to replace. For instance "MyName:NewName" will replace all occurrences of "MyName" with "NewName", "myname" with "newname" and "MYNAME" with "NEWNAME" (case sensitive).');
        $this->addOption('pluginname', null, InputOption::VALUE_REQUIRED, 'If defined, the log file will be placed in the specified plugin instead of the VisitorGenerator plugin', 'VisitorGenerator');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file    = $this->getPathToFile($input);
        $replace = $this->getReplace($input);
        $plugin  = $this->getPluginName($input);

        $logParser = new LogParser($file);
        $lines     = $logParser->getLogLines();

        $anonymized = '';
        foreach ($lines as $line) {
            $line = $this->replaceWords($line, $replace);
            $line = $this->anonymizeIp($line);
            $line = $this->anonymizeDomains($line);

            $anonymized .= $line;
        }

        $target = $this->buildTargetFileName($plugin, $file);
        $this->saveFile($output, $target, $anonymized);
    }

    private function getReplace(InputInterface $input)
    {
        $parsedReplace = array();

        $replaces = $input->getOption('replace');
        foreach ($replaces as $replace) {
            $words = explode(':', $replace);

            if (2 !== count($words)) {
                throw new \InvalidArgumentException('Each replace option needs exactly one ":" separator. For example "oldValue:newValue"');
            }

            $parsedReplace[] = $words;
        }

        return $parsedReplace;
    }

    private function getPluginName(InputInterface $input)
    {
        $pluginName  = $input->getOption('pluginname');
        $pathToCheck = PIWIK_INCLUDE_PATH . '/plugins/' . $pluginName;

        if (!is_dir($pathToCheck) || !is_writable($pathToCheck) || !is_readable($pathToCheck)) {
            throw new \Exception('Invalid plugin name or plugin directory is not readable/writeable');
        }

        return $pluginName;
    }

    private function getPathToFile(InputInterface $input)
    {
        $file = $input->getArgument('file');

        if (file_exists($file)) {
            return $file;
        }

        if (file_exists(PIWIK_INCLUDE_PATH . '/' . $file)) {
            return PIWIK_INCLUDE_PATH . '/' . $file;
        }

        throw new \InvalidArgumentException('Cannot find file, please specify an absoulute path and make sure the file is readable.');
    }

    private function replaceWords($subject, $wordsToBeReplaced)
    {
        foreach ($wordsToBeReplaced as $words) {
            $subject = str_replace($words[0], $words[1], $subject);
            $subject = str_replace(strtolower($words[0]), strtolower($words[1]), $subject);
            $subject = str_replace(strtoupper($words[0]), strtoupper($words[1]), $subject);
        }

        return $subject;
    }

    private function anonymizeIp($line)
    {
        return preg_replace('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/', '${1}.${2}.0.0', $line);
    }

    private function anonymizeDomains($line)
    {
        $log = LogParser::parseLogLine($line);

        if (empty($log)) {
            return $line;
        }

        $url = parse_url($log['url']);

        if (!empty($url['query'])) {

            $params = array();
            parse_str($url['query'], $params);

            $toBeReplaced = array('link' => 'outlink', 'urlref' => 'referrer', '_ref' => 'referrer', 'url' => '', 'download' => 'download');
            foreach ($toBeReplaced as $param => $subdomain) {
                if (!empty($params[$param])) {
                    $newUrl = $this->replaceDomainName($params[$param], $subdomain);
                    $line   = str_replace(urlencode($params[$param]), urlencode($newUrl), $line);
                }
            }

        }

        $referrer    = $log['referrer'];
        $newReferrer = $this->replaceDomainName($referrer, 'referrer');

        return str_replace($referrer, $newReferrer, $line);
    }

    private function replaceDomainName($url, $subdomain)
    {
        if (0 !== strpos($url, 'http')) {
            return $url;
        }

        $startPosOfPath = strpos($url, '/', 8);

        if (empty($startPosOfPath)) {
            $startPosOfPath = strlen($url);
        }

        $oldDomain = substr($url, 0, $startPosOfPath);

        $newDomain = 'http://example.org';
        if (!empty($subdomain)) {
            $newDomain = 'http://' . $subdomain . '.example.org';
        }

        return str_replace($oldDomain, $newDomain, $url);
    }

    private function saveFile(OutputInterface $output, $target, $content)
    {
        if (file_exists($target) && !$this->confirmOverwrite($output, $target)) {
            $output->writeln('File not written');
            return;
        }

        Filesystem::mkdir(dirname($target));

        file_put_contents($target, $content);

        $this->writeSuccessMessage($output, array(
            'Log anonymized and saved in file ' . $target,
            'You can replay this log - among others - by executing ',
            '"<comment>./console visitorgenerator:generate-visits --no-fake --idsite=?</comment>"'
        ));
    }

    private function confirmOverwrite(OutputInterface $output, $target)
    {
        $output->writeln('');

        $dialog = $this->getHelperSet()->get('dialog');
        return $dialog->askConfirmation(
            $output,
            sprintf('<question>File "%s" already exists, overwrite? (y/N)</question>', $target),
            false
        );
    }

    private function buildTargetFileName($pluginName, $file)
    {
        $target = PIWIK_INCLUDE_PATH . '/plugins/' . $pluginName . '/data/' . basename($file);

        if (!Common::stringEndsWith($target, '.log')) {
            $target = $target . '.log';
        }

        return $target;
    }
}
