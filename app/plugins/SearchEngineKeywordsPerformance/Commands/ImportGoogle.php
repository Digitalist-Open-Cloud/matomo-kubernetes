<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Importer\Google;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 */
class ImportGoogle extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('searchengines:import-google')
             ->setDescription('Imports Google Keywords')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force reimport for data')
             ->addOption('idsite', '', InputOption::VALUE_REQUIRED, 'Site id')
             ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'specific date');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Starting to import Google Keywords");

        $start = microtime(true);

        $idSite           = $input->getOption('idsite');
        $setting          = new MeasurableSettings($idSite);
        $searchConsoleUrl = $setting->googleSearchConsoleUrl;
        if (!$searchConsoleUrl || !$searchConsoleUrl->getValue()) {
            $output->writeln("Site with ID $idSite not configured for Google Import");
        }

        $importer = new Google($idSite, $input->hasOption('force'));

        $date = $input->hasOption('date') ? $input->getOption('date') : null;

        $importer->importAllAvailableData($date);

        $output->writeln("Finished in " . round(microtime(true) - $start, 3) . "s");
    }

}
