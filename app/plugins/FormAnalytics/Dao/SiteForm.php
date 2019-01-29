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
use Piwik\Piwik;
use Piwik\Plugins\FormAnalytics\Input\Description;
use Piwik\Plugins\FormAnalytics\Input\Name;
use Piwik\Plugins\FormAnalytics\Model\FormsModel;
use Exception;

class SiteForm
{
    private $table = 'site_form';
    private $tablePrefixed = '';

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idsiteform` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idsite` int(11) UNSIGNED NOT NULL,
                  `name` VARCHAR(" . Name::MAX_LENGTH . ") NOT NULL,
                  `description` VARCHAR(" . Description::MAX_LENGTH . ") NOT NULL ,
                  `status` VARCHAR(10) NOT NULL DEFAULT '" . FormsModel::STATUS_RUNNING . "',
                  `in_overview` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                  `match_form_rules` TEXT NULL,
                  `match_page_rules` TEXT NULL,
                  `conversion_rules` TEXT NULL,
                  `fields` TEXT NULL,
                  `auto_created` TINYINT(1) UNSIGNED NOT NULL,
                  `created_date` DATETIME NOT NULL,
                  `updated_date` DATETIME NOT NULL,
                  PRIMARY KEY (idsiteform),
                  UNIQUE unique_site_name (`idsite`, `name`)");
        //  we have an index on idSite, name to not create 2 or several forms at the same time during tracking
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    private function getDb()
    {
        return Db::get();
    }

    /**
     * @return array
     */
    public function getAllForms()
    {
        $table = $this->tablePrefixed;
        $forms = $this->getDb()->fetchAll("SELECT * FROM $table");
        return $this->enrichForms($forms);
    }

    /**
     * @return int
     */
    public function getNumFormsTotal()
    {
        $sql = sprintf("SELECT count(*) as numforms FROM %s where `status` != ?", $this->tablePrefixed);
        return $this->getDb()->fetchOne($sql, array(FormsModel::STATUS_DELETED));
    }

    /**
     * @param $idSite
     * @return bool
     */
    public function hasFormsForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $numForms = $this->getDb()->fetchOne("SELECT count(idsite) FROM $table WHERE idsite = ? LIMIT 1", array($idSite));

        return !empty($numForms);
    }

    /**
     * @return array
     */
    public function getAllFormsForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $forms = $this->getDb()->fetchAll("SELECT * FROM $table WHERE idsite = ?", array($idSite));

        return $this->enrichForms($forms);
    }

    /**
     * @param int $idSite
     * @param array $statuses
     * @return array
     */
    public function getFormsByStatuses($idSite, $statuses)
    {
        if (empty($statuses)) {
            // no form matches no status
            return array();
        }

        $bind = $statuses;
        $bind[] = $idSite;

        $fields = Common::getSqlStringFieldsArray($statuses);

        $table = $this->tablePrefixed;
        $forms = $this->getDb()->fetchAll("SELECT * FROM $table WHERE status IN($fields) AND idsite = ?", $bind);

        return $this->enrichForms($forms);
    }

    /**
     * @param $idSiteForm
     * @param $idSite
     * @return array|false
     * @throws \Exception
     */
    public function getForm($idSite, $idSiteForm)
    {
        $table = $this->tablePrefixed;
        $form = $this->getDb()->fetchRow("SELECT * FROM $table WHERE idsiteform = ? and idsite = ?", array($idSiteForm, $idSite));

        return $this->enrichForm($form);
    }

    public function createForm($idSite, $name, $description, $status, $matchFormRules, $matchPageRules, $conversionRules, $createdDate, $autoCreated)
    {
        $columns = array(
            'idsite' => $idSite,
            'name' => $name,
            'description' => $description,
            'match_form_rules' => $matchFormRules,
            'match_page_rules' => $matchPageRules,
            'conversion_rules' => $conversionRules,
            'status' => $status,
            'auto_created' => $autoCreated,
            'created_date' => $createdDate,
            'updated_date' => $createdDate,
        );
        $columns = $this->encodeFieldsWhereNeeded($columns);

        $bind = array_values($columns);
        $placeholder = Common::getSqlStringFieldsArray($columns);

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(%s)',
            $this->tablePrefixed, implode('`,`', array_keys($columns)), $placeholder);

        $db = $this->getDb();

        try {
            $db->query($sql, $bind);

        } catch (Exception $e) {
            if ($e->getCode() == 23000
                || strpos($e->getMessage(), 'Duplicate entry') !== false
                || strpos($e->getMessage(), ' 1062 ') !== false) {
                throw new Exception(Piwik::translate('FormAnalytics_ErrorFormNameDuplicate'));
            }
            throw $e;
        }

        $idSiteForm = $db->lastInsertId();

        return (int) $idSiteForm;
    }

    public function updateFormColumns($idSite, $idSiteForm, $columns)
    {
        $columns = $this->encodeFieldsWhereNeeded($columns);

        if (!empty($columns)) {
            $fields = array();
            $bind = array();
            foreach ($columns as $key => $value) {
                $fields[] = ' ' . $key . ' = ?';
                $bind[] = $value;
            }
            $fields = implode(',', $fields);

            $query = sprintf('UPDATE %s SET %s WHERE idsiteform = ? AND idsite = ?', $this->tablePrefixed, $fields);
            $bind[] = (int) $idSiteForm;
            $bind[] = (int) $idSite;

            // we do not use $db->update() here as this method is as well used in Tracker mode and the tracker DB does not
            // support "->update()". Therefore we use the query method where we know it works with tracker and regular DB

            $db = $this->getDb();

            try {
                $db->query($query, $bind);

            } catch (Exception $e) {
                if ($e->getCode() == 23000
                    || strpos($e->getMessage(), 'Duplicate entry') !== false
                    || strpos($e->getMessage(), ' 1062 ') !== false) {
                    throw new Exception(Piwik::translate('FormAnalytics_ErrorFormNameDuplicate'));
                }
                throw $e;
            };
        }
    }

    /**
     * @param int $idSite
     */
    public function deleteFormsForSite($idSite)
    {
        $table = $this->tablePrefixed;

        $query = "DELETE FROM $table WHERE idsite = ?";
        $bind = array($idSite);

        $this->getDb()->query($query, $bind);
    }

    /**
     * @param int $idSite
     * @param int $idSiteForm
     */
    public function deleteForm($idSite, $idSiteForm)
    {
        $table = $this->tablePrefixed;

        $query = "DELETE FROM $table WHERE idsite = ? and idsiteform = ?";
        $bind = array($idSite, $idSiteForm);

        $this->getDb()->query($query, $bind);
    }

    private function enrichForms($forms)
    {
        if (empty($forms)) {
            return array();
        }

        foreach ($forms as $index => $form) {
            $forms[$index] = $this->enrichForm($form);
        }

        return $forms;
    }

    private function enrichForm($form)
    {
        if (empty($form)) {
            return $form;
        }

        $form['idsiteform'] = (int) $form['idsiteform'];
        $form['idsite'] = (int) $form['idsite'];
        $form['auto_created'] = (bool) $form['auto_created'];
        $form['in_overview'] = (int) $form['in_overview'];
        $form['match_form_rules'] = $this->decodeField($form['match_form_rules']);
        $form['match_page_rules'] = $this->decodeField($form['match_page_rules']);
        $form['conversion_rules'] = $this->decodeField($form['conversion_rules']);
        $form['fields'] = $this->decodeField($form['fields']);

        return $form;
    }

    private function encodeFieldsWhereNeeded($columns)
    {
        foreach ($columns as $column => $value) {
            if (in_array($column, array('match_form_rules', 'match_page_rules', 'conversion_rules', 'fields'))) {
                $columns[$column] = $this->encodeField($value);
            }

            if (in_array($column, array('auto_created'))) {
                $columns[$column] = (int) $value;
            }
        }

        return $columns;
    }

    private function encodeField($field)
    {
        if (empty($field) || !is_array($field)) {
            $field = array();
        }

        return json_encode($field);
    }

    private function decodeField($field)
    {
        if (!empty($field)) {
            $field = @json_decode($field, true);
        }

        if (empty($field) || !is_array($field)) {
            $field = array();
        }

        return $field;
    }
}

