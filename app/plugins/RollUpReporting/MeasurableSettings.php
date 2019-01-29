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

namespace Piwik\Plugins\RollUpReporting;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\RollUpReporting\Settings\Storage\RollUpBackend;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Exception;
use Piwik\Site;

class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $childrenSiteIds;

    /** @var Setting */
    public $siteGroup;

    const ROLLUP_FIELDNAME = 'rollup_idsites';

    const KEY_NO_SITE_DEFINED = 'nositedefined';

    protected function init()
    {
        if ($this->idMeasurableType === Type::ID) {
            $this->childrenSiteIds = $this->makeSourceIdSites();
            $this->childrenSiteIds->setIsWritableByCurrentUser(Piwik::hasUserSuperUserAccess());

            if (!empty($this->idSite)) {
                $backend = new RollUpBackend($this->idSite, self::ROLLUP_FIELDNAME);

                $storageFactory = StaticContainer::get('Piwik\Settings\Storage\Factory');
                $this->childrenSiteIds->setStorage($storageFactory->makeStorage($backend));
            }
        }
    }

    private function makeSourceIdSites()
    {
        $settingsIdSite = $this->idSite;
        return $this->makeSetting(self::ROLLUP_FIELDNAME, $default = array(), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) use ($settingsIdSite) {

            $field->customUiControlTemplateFile = 'plugins/RollUpReporting/angularjs/field-rollup-children.html';

            $field->title = Piwik::translate('RollUpReporting_RollUpMeasurableSettingTitle');
            $field->inlineHelp = Piwik::translate('RollUpReporting_RollUpMeasurableSettingHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_MULTI_SELECT;
            $field->validate = function ($idSites) use ($settingsIdSite) {
                if (is_array($idSites) && in_array(self::KEY_NO_SITE_DEFINED, $idSites, true)) {
                    $idSites = array_unique($idSites);
                    // hack to prevent users not being able to select "no source sites". We add this dummy value
                    // so JS will actually send a value. Otherwise if in JS the value is an empty array, $.param
                    // will not send a value meaning this validate method is never called and user can set no
                    // source sites
                    $index = array_search(self::KEY_NO_SITE_DEFINED, $idSites, true);
                    if ($index !== false) {
                        array_splice($idSites, $index, 1);
                    }
                }

                if (!is_array($idSites) || count($idSites) <= 0) {
                    throw new Exception(Piwik::translate('RollUpReporting_ErrorNoSiteAssigned'));
                }

                if (!empty($idSites)) {
                    $idSites = array_map('trim', $idSites);
                    $idSites = array_filter($idSites, 'strlen');

                    foreach ($idSites as $idSite) {
                        if ($idSite === Model::KEY_ALL_WEBSITES) {
                            continue;
                        }

                        if (!is_numeric($idSite)) {
                            throw new Exception('idSites for Roll-Up site needs to be numeric');
                        } elseif ($idSite == $settingsIdSite) {
                            throw new Exception('Cannot assign this site to itself.');
                        } elseif (!Site::getSite($idSite)) {
                            // getSite will already trigger the exception if it does not exist but just in case we also
                            // throw it here
                            throw new Exception('The idsite ' . (int) $idSite . ' cannot be assigned to a Roll-Up site, the site does not exist');
                        } else {
                            $model = new Model();
                            if ($model->isRollUpIdSite($idSite)) {
                                // make sure it is not in another rollup?
                                $idSites = $model->getChildIdSites($idSite);
                                if (in_array($settingsIdSite, $idSites)) {
                                    throw new Exception(Piwik::translate('RollUpReporting_ErrorCannotAssignRollUpToAnotherRollUp', array((int) $idSite, (int) $idSite)));
                                }
                            }
                        }
                    }
                }
            };
            $field->transform = function ($idSites) {
                if (empty($idSites)) {
                    return array();
                }

                $idSites = array_map('trim', $idSites);

                if (is_array($idSites) && in_array(self::KEY_NO_SITE_DEFINED, $idSites, true)) {
                    $idSites = array_unique($idSites);
                    // hack to prevent users not being able to select "no source sites". We add this dummy value
                    // so JS will actually send a value. Otherwise if in JS the value is an empty array, $.param
                    // will not send a value meaning this validate method is never called and user can set no
                    // source sites
                    $index = array_search(self::KEY_NO_SITE_DEFINED, $idSites, true);
                    if ($index !== false) {
                        array_splice($idSites, $index, 1);
                    }
                }

                foreach ($idSites as &$idSite) {
                    if ($idSite !== Model::KEY_ALL_WEBSITES) {
                        $idSite = (int) $idSite;
                    }
                }

                return $idSites;
            };
        });
    }


}
