<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CustomPiwikJs\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\CustomPiwikJs\TrackerUpdater;
use Piwik\Plugins\CustomPiwikJs\TrackingCode\PluginTrackerFiles;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateTracker extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('custom-piwik-js:update');
        $this->addOption('source-file', null, InputOption::VALUE_REQUIRED, 'Absolute path to source PiwikJS file.', $this->getPathOriginalPiwikJs());
        $this->addOption('target-file', null, InputOption::VALUE_REQUIRED, 'Absolute path to target file. Useful if your /piwik.js is not writable and you want to replace the file manually', PIWIK_DOCUMENT_ROOT . TrackerUpdater::TARGET_PIWIK_JS);
        $this->addOption('ignore-minified', null, InputOption::VALUE_NONE, 'Ignore minified tracker files, useful during development so the original source file can be debugged');
        $this->setDescription('Update the Javascript Tracker with plugin tracker additions');
    }

    private function getPathOriginalPiwikJs()
    {
        return PIWIK_DOCUMENT_ROOT . TrackerUpdater::ORIGINAL_PIWIK_JS;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceFile = $input->getOption('source-file');
        $targetFile = $input->getOption('target-file');
        $ignoreMinified = (bool)$input->getOption('ignore-minified');

        $this->updateTracker($sourceFile, $targetFile, $ignoreMinified);

        $output->writeln('<info>The Javascript Tracker has been updated</info>');
    }

    public function updateTracker($sourceFile, $targetFile, $ignoreMinified)
    {
        $pluginTrackerFiles = StaticContainer::get('Piwik\Plugins\CustomPiwikJs\TrackingCode\PluginTrackerFiles');

        if ($ignoreMinified) {
            if (empty($sourceFile) || $sourceFile === $this->getPathOriginalPiwikJs()) {
                // no custom source file was requested
                $sourceFile = PIWIK_DOCUMENT_ROOT . TrackerUpdater::DEVELOPMENT_PIWIK_JS;
            }
            $pluginTrackerFiles->ignoreMinified();
        }

        $updater = StaticContainer::getContainer()->make('Piwik\Plugins\CustomPiwikJs\TrackerUpdater', array(
            'fromFile' => $sourceFile, 'toFile' => $targetFile
        ));
        $updater->setTrackerFiles($pluginTrackerFiles);
        $updater->checkWillSucceed();
        $updater->update();
    }
}
