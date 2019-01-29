<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\CoreConsole\Commands;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Decompress\Tar;
use Piwik\Development;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DevelopmentSyncProcessedSystemTests extends ConsoleCommand
{
    public function isEnabled()
    {
        return Development::isEnabled();
    }

    protected function configure()
    {
        $this->setName('development:sync-system-test-processed');
        $this->setDescription('For Piwik core devs. Copies processed system tests from travis artifacts to local processed directories');
        $this->addArgument('buildnumber', InputArgument::REQUIRED, 'Travis build number you want to sync, eg "14820".');
        $this->addOption('expected', 'e', InputOption::VALUE_NONE, 'If given file will be copied in expected directories instead of processed');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updateCoreFiles($input, $output);
        $this->updatePluginsFiles($input, $output);
    }

    protected function updateCoreFiles(InputInterface $input, OutputInterface $output)
    {
        $buildNumber = $input->getArgument('buildnumber');
        $expected    = $input->getOption('expected');
        $targetDir   = sprintf(PIWIK_INCLUDE_PATH . '/tests/PHPUnit/System/%s', $expected ? 'expected' : 'processed');
        $tmpDir      = StaticContainer::get('path.tmp') . '/';

        $this->validate($buildNumber, $targetDir, $tmpDir);

        if (Common::stringEndsWith($buildNumber, '.1')) {
            // eg make '14820.1' to '14820' to be backwards compatible
            $buildNumber = substr($buildNumber, 0, -2);
        }

        $filename = sprintf('system.%s.tar.bz2', $buildNumber);
        $urlBase  = sprintf('https://builds-artifacts.matomo.org/matomo-org/matomo/%s', $filename);
        $tests    = Http::sendHttpRequest($urlBase, $timeout = 120);

        $tarFile = $tmpDir . $filename;
        file_put_contents($tarFile, $tests);

        $tar = new Tar($tarFile, 'bz2');

        $tar->extract($targetDir);

        $this->writeSuccessMessage($output, array(
            'All processed system test results were copied to <comment>' . $targetDir . '</comment>',
            'Compare them with the expected test results and commit them if needed.'
        ));

        unlink($tarFile);
    }


    protected function updatePluginsFiles(InputInterface $input, OutputInterface $output)
    {
        $buildNumber = $input->getArgument('buildnumber');
        $expected    = $input->getOption('expected');
        $targetDir   = sprintf(PIWIK_INCLUDE_PATH . '/plugins/%%s/tests/System/%s/', $expected ? 'expected' : 'processed');
        $tmpDir      = StaticContainer::get('path.tmp') . '/';

        if (Common::stringEndsWith($buildNumber, '.1')) {
            // eg make '14820.1' to '14820' to be backwards compatible
            $buildNumber = substr($buildNumber, 0, -2);
        }

        $filename = sprintf('system.plugin.%s.tar.bz2', $buildNumber);
        $urlBase  = sprintf('https://builds-artifacts.matomo.org/matomo-org/matomo/%s', $filename);
        $tests    = Http::sendHttpRequest($urlBase, $timeout = 120);

        $tarFile = $tmpDir . $filename;
        file_put_contents($tarFile, $tests);

        $tar = new Tar($tarFile, 'bz2');

        $extractionTarget = $tmpDir . '/artifacts';

        Filesystem::mkdir($extractionTarget);
        $tar->extract($extractionTarget);

        $artifacts = Filesystem::globr($extractionTarget, '*~~*');

        foreach($artifacts as $artifact) {
            $artifactName = basename($artifact);
            list($plugin, $file) = explode('~~', $artifactName);
            Filesystem::copy($artifact, sprintf($targetDir, $plugin) . $file);
        }

        Filesystem::unlinkRecursive($extractionTarget, true);

        $this->writeSuccessMessage($output, array(
            'All processed plugin system test results were copied to <comment>' . $targetDir . '</comment>',
            'Compare them with the expected test results and commit them if needed.'
        ));

        unlink($tarFile);
    }

    private function validate($buildNumber, $targetDir, $tmpDir)
    {
        if (empty($buildNumber)) {
            throw new \InvalidArgumentException('Missing build number.');
        }

        if (!is_writable($targetDir)) {
            throw new \RuntimeException('Target dir is not writable: ' . $targetDir);
        }

        if (!is_writable($tmpDir)) {
            throw new \RuntimeException('Tempdir is not writable: ' . $tmpDir);
        }
    }
}
