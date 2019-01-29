<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreAdminHome\Commands;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\Actions;
use Piwik\Archive\ArchiveInvalidator;
use Piwik\Date;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\CoreAdminHome\Model\DuplicateActionRemover;
use Piwik\Timer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Finds duplicate actions rows in log_action and removes them. Fixes references to duplicate
 * actions in the log_link_visit_action table, log_conversion table, and log_conversion_item
 * table.
 *
 * Prior to version 2.11, there was a race condition in the tracker where it was possible for
 * two or more actions with the same name and type to be inserted simultaneously. This resulted
 * in inaccurate data. A Piwik database with this problem can be fixed using this class.
 *
 * With version 2.11 and above, it is still possible for duplicate actions to be inserted, but
 * ONLY if the tracker's PHP process fails suddenly right after inserting an action. This is
 * very rare, and even if it does happen, report data will not be affected, but the extra
 * actions can be deleted w/ this class.
 */
class FixDuplicateLogActions extends ConsoleCommand
{
    /**
     * Used to invalidate archives. Only used if $shouldInvalidateArchives is true.
     *
     * @var ArchiveInvalidator
     */
    private $archiveInvalidator;

    /**
     * DAO used to find duplicate actions in log_action and fix references to them in other tables.
     *
     * @var DuplicateActionRemover
     */
    private $duplicateActionRemover;

    /**
     * DAO used to remove actions from the log_action table.
     *
     * @var Actions
     */
    private $actionsAccess;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param ArchiveInvalidator $invalidator
     * @param DuplicateActionRemover $duplicateActionRemover
     * @param Actions $actionsAccess
     * @param LoggerInterface $logger
     */
    public function __construct(ArchiveInvalidator $invalidator = null, DuplicateActionRemover $duplicateActionRemover = null,
                                Actions $actionsAccess = null, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->archiveInvalidator = $invalidator ?: StaticContainer::get('Piwik\Archive\ArchiveInvalidator');
        $this->duplicateActionRemover = $duplicateActionRemover ?: new DuplicateActionRemover();
        $this->actionsAccess = $actionsAccess ?: new Actions();
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
    }

    protected function configure()
    {
        $this->setName('core:fix-duplicate-log-actions');
        $this->addOption('invalidate-archives', null, InputOption::VALUE_NONE, "If supplied, archives for logs that use duplicate actions will be invalidated."
            . " On the next cron archive run, the reports for those dates will be re-processed.");
        $this->setDescription('Removes duplicates in the log action table and fixes references to the duplicates in '
                            . 'related tables. NOTE: This action can take a long time to run!');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $invalidateArchives = $input->getOption('invalidate-archives');

        $timer = new Timer();

        $duplicateActions = $this->duplicateActionRemover->getDuplicateIdActions();
        if (empty($duplicateActions)) {
            $output->writeln("Found no duplicate actions.");
            return;
        }

        $output->writeln("<info>Found " . count($duplicateActions) . " actions with duplicates.</info>");

        list($numberRemoved, $allArchivesAffected) = $this->fixDuplicateActionReferences($duplicateActions, $output);

        $this->deleteDuplicatesFromLogAction($output, $duplicateActions);

        if ($invalidateArchives) {
            $this->invalidateArchivesUsingActionDuplicates($allArchivesAffected, $output);
        } else {
            $this->printAffectedArchives($allArchivesAffected, $output);
        }

        $logActionTable = Common::prefixTable('log_action');
        $this->writeSuccessMessage($output, array(
            "Found and deleted $numberRemoved duplicate action entries in the $logActionTable table.",
            "References in log_link_visit_action, log_conversion and log_conversion_item were corrected.",
            $timer->__toString()
        ));
    }

    private function invalidateArchivesUsingActionDuplicates($archivesAffected, OutputInterface $output)
    {
        $output->write("Invalidating archives affected by duplicates fixed...");
        foreach ($archivesAffected as $archiveInfo) {
            $dates = array(Date::factory($archiveInfo['server_time']));
            $this->archiveInvalidator->markArchivesAsInvalidated(array($archiveInfo['idsite']), $dates, $period = false);
        }
        $output->writeln("Done.");
    }

    private function printAffectedArchives($allArchivesAffected, OutputInterface $output)
    {
        $output->writeln("The following archives used duplicate actions and should be invalidated if you want correct reports:");
        foreach ($allArchivesAffected as $archiveInfo) {
            $output->writeln("\t[ idSite = {$archiveInfo['idsite']}, date = {$archiveInfo['server_time']} ]");
        }
    }

    private function fixDuplicateActionReferences($duplicateActions, OutputInterface $output)
    {
        $dupeCount = count($duplicateActions);

        $numberRemoved = 0;
        $allArchivesAffected = array();

        foreach ($duplicateActions as $index => $dupeInfo) {
            $name = $dupeInfo['name'];
            $toIdAction = $dupeInfo['idaction'];
            $fromIdActions = $dupeInfo['duplicateIdActions'];

            $numberRemoved += count($fromIdActions);

            $output->writeln("<info>[$index / $dupeCount]</info> Fixing duplicates for '$name'");

            $this->logger->debug("  idaction = {idaction}, duplicate idactions = {duplicateIdActions}", array(
                'idaction' => $toIdAction,
                'duplicateIdActions' => $fromIdActions
            ));

            foreach (DuplicateActionRemover::$tablesWithIdActionColumns as $table) {
                $archivesAffected = $this->fixDuplicateActionsInTable($output, $table, $toIdAction, $fromIdActions);
                $allArchivesAffected = array_merge($allArchivesAffected, $archivesAffected);
            }
        }

        $allArchivesAffected = array_values(array_unique($allArchivesAffected, SORT_REGULAR));

        return array($numberRemoved, $allArchivesAffected);
    }

    private function fixDuplicateActionsInTable(OutputInterface $output, $table, $toIdAction, $fromIdActions)
    {
        $timer = new Timer();

        $archivesAffected = $this->duplicateActionRemover->getSitesAndDatesOfRowsUsingDuplicates($table, $fromIdActions);

        $this->duplicateActionRemover->fixDuplicateActionsInTable($table, $toIdAction, $fromIdActions);

        $output->writeln("\tFixed duplicates in " . Common::prefixTable($table) . ". <comment>" . $timer->__toString() . "</comment>.");

        return $archivesAffected;
    }

    private function deleteDuplicatesFromLogAction(OutputInterface $output, $duplicateActions)
    {
        $logActionTable = Common::prefixTable('log_action');
        $output->writeln("<info>Deleting duplicate actions from $logActionTable...</info>");

        $idActions = array();
        foreach ($duplicateActions as $dupeInfo) {
            $idActions = array_merge($idActions, $dupeInfo['duplicateIdActions']);
        }

        $this->actionsAccess->delete($idActions);
    }
}