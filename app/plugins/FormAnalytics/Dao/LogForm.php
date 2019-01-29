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
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\FormAnalytics\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\FormAnalytics\Metrics;
use Piwik\Segment;

class LogForm
{
    private $table = 'log_form';
    private $tablePrefixed = '';

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
    }

    private function getDb()
    {
        return Db::get();
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idlogform` BIGINT(15) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idsiteform` INT(11) UNSIGNED NOT NULL,
                  `idsite` INT(11) UNSIGNED NOT NULL,
                  `idvisit` INT(11) UNSIGNED NOT NULL,
                  `idvisitor` BINARY(8) NOT NULL,
                  `first_idformview` VARCHAR(6) NOT NULL,
                  `last_idformview` VARCHAR(6) NOT NULL,
                  `num_views` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 1,
                  `num_starts` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 1,
                  `num_submissions` SMALLINT(8) UNSIGNED NOT NULL DEFAULT 0,
                  `converted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                  `form_last_action_time` DATETIME NOT NULL,
                  `time_hesitation` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
                  `time_spent` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                  `time_to_first_submission` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                  PRIMARY KEY(`idlogform`),
                  KEY key_idsite_last_time (`idsite`, `form_last_action_time`),
                  UNIQUE unique_idvisit_form_view (`idvisit`, `idsiteform`, `last_idformview`)");

        // The last_idformview prevents race conditions when multiple requests try to create form entry at the same time!
        // eg when 2 requests want to create a new log form entry at the same time, they both have the same idformview
        // meaning we have a duplicate meaning the 2nd request will update instead of insert and can reuse same log id
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function getAllRecords()
    {
        return $this->getDb()->fetchAll('SELECT * FROM ' . $this->tablePrefixed);
    }

    public function findRecordByIdView($idVisit, $idSiteForm, $idFormView)
    {
        // we need to use a select query to find a record because we want to make sure to get the form the user is currently operating on.
        // if the idformview matches, we need to make sure to apply all tracking requests with that form view on this idLogForm in case there
        // are race conditions with other form requests.
        $sql = sprintf('SELECT idlogform FROM %s WHERE idvisit = ? AND idsiteform = ?  AND last_idformview = ? ORDER BY idlogform DESC LIMIT 1', $this->tablePrefixed);
        $bind = array($idVisit, $idSiteForm, $idFormView);
        $idLogForm = Db::fetchOne($sql, $bind);

        if (!empty($idLogForm)) {
            return $idLogForm;
        }
    }

    public function findUnconvertedRecord($idVisit, $idSiteForm)
    {
        // we need to use a select query to find a record because we want to make sure to get a form that has not been
        // converted yet. And if a form exists that has been converted yet, we will need to make sure to create a new
        // log_form entry
        $sql = sprintf('SELECT idlogform FROM %s WHERE idvisit = ? AND idsiteform = ? AND converted = 0 ORDER BY idlogform DESC LIMIT 1', $this->tablePrefixed);
        $bind = array($idVisit, $idSiteForm);
        $idLogForm = Db::fetchOne($sql, $bind);

        if (!empty($idLogForm)) {
            return $idLogForm;
        }
    }

    public function findUnconvertedRecordButInteractedWith($idVisit, $idSiteForm)
    {
        $sql = sprintf('SELECT idlogform FROM %s WHERE idvisit = ? AND idsiteform = ? AND converted = 0 AND num_starts > 0 ORDER BY idlogform DESC LIMIT 1', $this->tablePrefixed);
        $bind = array($idVisit, $idSiteForm);
        $idLogForm = Db::fetchOne($sql, $bind);

        if (!empty($idLogForm)) {
            return $idLogForm;
        }
    }

    public function recordConversion($idVisit, $idSiteForm)
    {
        // we first try to find a not yet converted form from that visit that where a user also spent time on.
        // we do not check for "where converted = 0" only because if there are for some reason 2 records set to converted = 0
        // then we make sure to convert one where a user actually spent time on.
        // otherwise imagine eg user spents time on form and submits. on the next pageview for some reason it creates a
        // new log_form entry that has converted = 0 as well. Then a new tracking request comes in with manually tracked
        // conoversion. This means 2 have converted = 0 but only on one was actually time spent on. So we try to convert
        // that one.
        // also imagine user converts form on landing page, this page shows same form again which means it creates a new
        // form view. If then user reloads page or depending on which request comes first it would otherwise directly
        // convert that just newly create form request again
        // If for some reason a user uses tracking API and does not send time_spent values, we fallback to usual behaviour
        // and convert the given form. This may be also the case if user can for example convert form without spending
        // "actively" time on it.
        $idLogForm = $this->findUnconvertedRecordButInteractedWith($idVisit, $idSiteForm);

        if (!empty($idLogForm)) {
            // we make sure we have recorded a time spent for this form
            $sql = sprintf('UPDATE %s SET converted = 1, time_spent = IF(time_spent > 0, time_spent, 1) WHERE idlogform = ?',
                           $this->tablePrefixed);

            $bind = array($idLogForm);

            $db = $this->getDb();
            $db->query($sql, $bind);
        }
    }

    public function addRecord($idVisitor, $idVisit, $idSite, $idSiteForm, $idFormView, $isViewed, $isStarted, $isSubmitted, $serverTime, $timeHesitation, $timeSpent, $timeToSubmission)
    {
        $timeSpent = !empty($timeSpent) ? $timeSpent : 0;

        if (!empty($timeHesitation) && $timeHesitation > 7200000) {
            $timeHesitation = 7200000;
            // we limit hesitation time to 2 hours (7200000 ms). Unlikely a user is actually actively longer on it and
            // field size allows us to max store about 4 hours
        }

        $values = array(
            'idvisitor' => $idVisitor,
            'idvisit' => $idVisit,
            'idsite' => $idSite,
            'idsiteform' => $idSiteForm,
            'first_idformview' => $idFormView,
            'last_idformview' => $idFormView,
            'num_views' => 1, // it is always 1 when we add a record, if there was no view we wouldn't add the record
            'num_starts' => $isStarted ? 1 : 0,
            'num_submissions' => $isSubmitted ? 1 : 0,
            'form_last_action_time' => $serverTime,
            'time_hesitation' => $timeHesitation ? $timeHesitation : 0,
            'time_to_first_submission' => $timeToSubmission ? $timeToSubmission : 0,
            'time_spent' => $timeSpent
        );

        $columns = implode('`,`', array_keys($values));
        $vals = Common::getSqlStringFieldsArray($values);

        $update = $this->getUpdateSqlAndBind($serverTime, $isViewed, $isStarted, $isSubmitted, $timeHesitation, $timeToSubmission, $timeSpent);

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(%s) 
                        ON DUPLICATE KEY UPDATE ' . $update['sql'] . ',
                        idlogform = LAST_INSERT_ID(idlogform)',
            $this->tablePrefixed, $columns, $vals);

        $bind = array_values($values);
        $bind = array_merge($bind, $update['bind']);

        $db = $this->getDb();
        $db->query($sql, $bind);

        $idLogForm = $db->lastInsertId();
        return $idLogForm;
    }

    public function updateRecord($idLogForm, $idFormView, $isViewed, $isStarted, $isSubmitted, $serverTime, $timeHesitation, $timeSpent, $timeToSubmission)
    {
        if (!empty($timeHesitation) && $timeHesitation > 7200000) {
            $timeHesitation = 7200000;
            // we limit hesitation time to 2 hours (7200000 ms). Unlikely a user is actually actively longer on it and
            // field size allows us to max store about 4 hours
        }

        $update = $this->getUpdateSqlAndBind($serverTime, $isViewed, $isStarted, $isSubmitted, $timeHesitation, $timeToSubmission, $timeSpent);

        $sql = sprintf('UPDATE %s SET 
                        last_idformview = ?, ' . $update['sql'] . '
                        WHERE idlogform = ?',
                        $this->tablePrefixed);

        $bind = array($idFormView);
        $bind = array_merge($bind, $update['bind']);
        $bind[] = $idLogForm;

        $db = $this->getDb();
        $db->query($sql, $bind);

        $idLogForm = $db->lastInsertId();
        return $idLogForm;
    }

    private function getUpdateSqlAndBind($serverTime, $isViewed, $isStarted, $isSubmitted, $timeHesitation, $timeToSubmission, $timeSpent)
    {
        $sql = 'form_last_action_time = ?, 
                num_views = num_views + ?, 
                num_starts = num_starts + ?, 
                num_submissions = num_submissions + ?, 
                time_spent = time_spent + ?,
                time_hesitation = IF(time_hesitation > 0, time_hesitation, ?),
                time_to_first_submission = IF(time_to_first_submission > 0, time_to_first_submission, ?)';

        // we do not set time_spent = ? and do not send total value from tracker file as user might operate on same
        // form in 2 browser tabs at same time. The values would not be accurate in this case and we always need to sum
        // it in that case. However, time_hesitation is only taken from one tab in such a case anyway so we might as
        // well ignore this case in t he future and only store the sum of time_spent from one browser tab.

        $bind = array();
        $bind[] = $serverTime;
        $bind[] = !empty($isViewed) ? 1 : 0;
        $bind[] = !empty($isStarted) ? 1 : 0;
        $bind[] = !empty($isSubmitted) ? 1 : 0;
        $bind[] = !empty($timeSpent) ? $timeSpent : 0;
        $bind[] = !empty($timeHesitation) ? $timeHesitation : 0;
        $bind[] = !empty($timeToSubmission) ? $timeToSubmission : 0;

        return array('sql' => $sql, 'bind' => $bind);
    }

    public function getCounters($idSite, $fromServerTime, $segment)
    {
        $select = sprintf('count(log_form.num_views) as %s,
                           sum(if(log_form.num_starts > 0, 1, 0)) as %s,
                           sum(log_form.time_spent) as %s,
                           sum(if(log_form.num_submissions > 0, 1, 0)) as %s,
                           sum(if(log_form.num_submissions > 1, 1, 0)) as %s,
                           sum(log_form.converted) as %s',
                           Metrics::SUM_FORM_VIEWERS,
                           Metrics::SUM_FORM_STARTERS,
                           Metrics::SUM_FORM_TIME_SPENT,
                           Metrics::SUM_FORM_SUBMITTERS,
                           Metrics::SUM_FORM_RESUBMITTERS,
                           Metrics::SUM_FORM_CONVERSIONS);

        $where = sprintf('%1$s.idsite = ? and %1$s.form_last_action_time > ?', $this->table);
        $segment = new Segment($segment, $idSite);
        $query = $segment->getSelectQuery($select, $this->table, $where, array($idSite, $fromServerTime));

        $db = $this->getDb();
        return $db->fetchRow($query['sql'], $query['bind']);
    }

    public function getCurrentMostPopularForms($idSite, $fromServerTime, $limit, $segment)
    {
        $select = sprintf('site_form.name as label,
                           site_form.idsiteform,
                           count(log_form.num_views) as %s,
                           sum(if(log_form.num_starts > 0, 1, 0)) as %s,
                           sum(if(log_form.num_submissions > 0, 1, 0)) as %s,
                           sum(if(log_form.num_submissions > 1, 1, 0)) as %s,
                           sum(log_form.converted) as %s',
                           Metrics::SUM_FORM_VIEWERS,
                           Metrics::SUM_FORM_STARTERS,
                           Metrics::SUM_FORM_SUBMITTERS,
                           Metrics::SUM_FORM_RESUBMITTERS,
                           Metrics::SUM_FORM_CONVERSIONS);

        $where = sprintf('%1$s.idsite = ? and %1$s.form_last_action_time > ?', $this->table);
        $segment = new Segment($segment, $idSite);
        $orderBy = Metrics::SUM_FORM_STARTERS . ' DESC, ' . Metrics::SUM_FORM_VIEWERS . ' DESC';
        $groupBy = 'site_form.name';
        $from = array($this->table, array(
            'table'  => 'site_form',
            'joinOn' => 'site_form.idsiteform = log_form.idsiteform'
        ));
        $query = $segment->getSelectQuery($select, $from, $where, array($idSite, $fromServerTime), $orderBy, $groupBy, (int) $limit);

        $db = $this->getDb();
        return $db->fetchAll($query['sql'], $query['bind']);
    }

}

