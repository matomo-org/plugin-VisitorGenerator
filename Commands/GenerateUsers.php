<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use Piwik\Access;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\Generator\Users;
use Matomo\Dependencies\Symfony\Component\Console\Input\InputInterface;
use Matomo\Dependencies\Symfony\Component\Console\Input\InputOption;
use Matomo\Dependencies\Symfony\Component\Console\Output\OutputInterface;

class GenerateUsers extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-users');
        $this->setDescription('Generates many users. This command is intended for developers.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Defines how many users should be generated', 10);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = $input->getOption('limit');

        $userLogins = Access::doAsSuperUser(function () use ($limit) {
            $websiteGenerator = new Users();
            return $websiteGenerator->generate((int) $limit);
        });

        $this->writeSuccessMessage($output, array(count($userLogins) . ' Users generated'));

        return self::SUCCESS;
    }

}
