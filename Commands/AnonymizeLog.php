<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use Piwik\Common;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnonymizeLog extends ConsoleCommand
{
    private $domain = 'http://example.org';
    private $domainReferrer = 'http://example.com';

    protected function configure()
    {
        $this->setName('visitorgenerator:anonymize-log');
        $this->setHelp('Example usage:
./console visitorgenerator:anonymize-log /path/to/file.log --replace MyCompanyName:Example --replace MyTitle:ExampleTitle

This will read the log file, replace all occurrences of "MyCompanyName" with "Example" and "MyTitle" with "ExampleTitle".
It will replace the last 2 bits of all IP addresses with "0" and replace all url domains with "example.org" and all referrers with "example.com"');
        $this->setDescription('Anonymizes an Apache log file by anonymizing IPs and domains. It will not replace any search terms, paths or url queries. The original file will not be altered but a new file will be created within the "plugins/VisitorGenerator/data" directory having the same file name.');
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the file. Either absolute or relative to the Piwik directory');
        $this->addOption('replace', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Optional words to replace. For instance "MyName:NewName" will replace all occurrences of "MyName" with "NewName". Multiple replace options are possible');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file  = $this->getPathToFile($input);
        $lines = file($file);

        $anonymized = array();
        foreach ($lines as $line) {
            $line        = $this->replaceWords($line, $input->getOption('replace'));
            $anonymized .= $this->anonymizeLogLine($line, $anonymized);
        }

        $this->saveFile($output, $file, $anonymized);
    }

    private function replaceWords($string, $replace)
    {
        foreach ($replace as $pair) {
            $words = explode(':', $pair);

            if (2 === count($words)) {
                $string = str_replace($words[0], $words[1], $string);
            } else {
                throw new \InvalidArgumentException('Each replace option needs exactly one separator ":". For example "oldValue:newValue"');
            }
        }

        return $string;
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

        throw new \InvalidArgumentException('Cannot find file');
    }

    private function anonymizeLogLine($line)
    {
        $line = $this->anonymizeIp($line);
        $line = $this->anonymizeDomains($line);

        return $line;
    }

    private function anonymizeIp($line)
    {
        return preg_replace('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/', '${1}.${2}.0.0', $line);
    }

    private function anonymizeDomains($line)
    {
        if (!preg_match('/^(\S+) \S+ \S+ \[(.*?)\] "GET (\S+.*?)" \d+ \d+ "(.*?)" "(.*?)"/', $line, $m)) {
            return $line;
        }

        $url      = $m[3];
        $referrer = $m[4];

        $url = parse_url($url);

        if (!empty($url['query'])) {

            $params = array();
            parse_str($url['query'], $params);

            if (!empty($params['url'])) {
                $newUrl = $this->replaceDomainName($params['url'], $this->domain);
                $line   = str_replace(urlencode($params['url']), urlencode($newUrl), $line);
            }

            if (!empty($params['urlref'])) {
                $newUrlRef = $this->replaceDomainName($params['urlref'], $this->domainReferrer);
                $line      = str_replace(urlencode($params['urlref']), urlencode($newUrlRef), $line);
            }
        }

        $newReferrer = $this->replaceDomainName($referrer, $this->domainReferrer);

        return str_replace($referrer, $newReferrer, $line);
    }

    private function replaceDomainName($url, $newDomain)
    {
        if (0 !== strpos($url, 'http')) {
            return $url;
        }

        $startPosOfPath = strpos($url, '/', 8);

        if (empty($startPosOfPath)) {
            $startPosOfPath = strlen($url);
        }

        $oldDomain = substr($url, 0, $startPosOfPath);

        return str_replace($oldDomain, $newDomain, $url);
    }

    private function saveFile(OutputInterface $output, $file, $content)
    {
        $target = __DIR__ . '/../data/' . basename($file);

        if (!Common::stringEndsWith($target, '.log')) {
            $target = $target . '.log';
        }

        if (file_exists($target) && !$this->confirmOverwrite($output, $target)) {
            $output->writeln('File not written');
            return;
        }

        file_put_contents($target, $content);

        $this->writeSuccessMessage($output, array(
            'Log anonymized and saved in file ' . $target,
            'You can replay this log by executing "<comment>./console visitorgenerator:generate-visits --no-fake</comment>"'
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
}
