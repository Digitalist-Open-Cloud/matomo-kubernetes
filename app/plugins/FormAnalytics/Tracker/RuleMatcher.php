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

namespace Piwik\Plugins\FormAnalytics\Tracker;

use Piwik\Common;
use Piwik\Piwik;

class RuleMatcher
{
    const ATTRIBUTE_FORM_NAME = 'form_name';
    const ATTRIBUTE_FORM_ID = 'form_id';
    const ATTRIBUTE_URL = 'page_url';
    const ATTRIBUTE_PATH = 'page_path';
    const ATTRIBUTE_QUERY = 'page_query';

    const PATTERN_PREFIX_NOT = 'not_';

    const PATTERN_EQUALS = 'equals';
    const PATTERN_NOT_EQUALS = 'not_equals';
    const PATTERN_EQUALS_EXACTLY = 'equals_exactly';
    const PATTERN_NOT_EQUALS_EXACTLY = 'not_equals_exactly';
    const PATTERN_CONTAINS = 'contains';
    const PATTERN_NOT_CONTAINS = 'not_contains';
    const PATTERN_STARTS_WITH = 'starts_with';
    const PATTERN_ENDS_WITH = 'ends_with';
    const PATTERN_REGEXP = 'regexp';

    /**
     * @var array
     */
    private $rule;

    public function __construct($rule)
    {
        $this->rule = $rule;
    }

    public function matches($formName, $formId, $url)
    {
        if (empty($this->rule['pattern']) || empty($this->rule['attribute']) || !isset($this->rule['value'])) {
            return false;
        }

        $attributeValues = $this->getAttributeValues($this->rule['attribute'], $formName, $formId, $url);

        if (isset($attributeValues)) {
            return $this->matchesTargetValue($attributeValues, $this->rule['pattern'], $this->rule['value']);
        }

        return false;
    }

    protected function getAttributeValues($attribute, $formName, $formId, $url)
    {
        switch ($attribute) {
            case self::ATTRIBUTE_FORM_NAME:
                if (!is_string($formName) || $formName === '') {
                    return null;
                }
                return array($formName);

            case self::ATTRIBUTE_FORM_ID:
                if (!is_string($formId) || $formId === '') {
                    return null;
                }
                return array($formId);

            case self::ATTRIBUTE_URL:
                if (!is_string($url) || $url === '') {
                    return null;
                }

                $return = array($url); // it is important that original value is always set first for equals_exactly and pattern

                $url = Common::mb_strtolower($url);

                if (strpos($url, 'http://') === 0) {
                    $return[] = str_replace('http://', 'https://', $url);
                    $return[] = str_replace('http://', '', $url);
                } elseif (strpos($url, 'https://') === 0) {
                    $return[] = str_replace('https://', 'http://', $url);
                    $return[] = str_replace('https://', '', $url);
                }

                if (strpos($url, '?') === false && strpos($url, '#') === false) {
                    foreach ($return as $url) {
                        if (Common::stringEndsWith($url, '/')) {
                            $return[] = substr($url, 0, -1); // we make sure we have ending slash or not
                        } else {
                            $return[] = $url . '/'; // we make sure we have ending slash or not
                        }
                    }
                }

                return $return;

            case self::ATTRIBUTE_PATH:
                if (!is_string($url)) {
                    return null;
                }

                $urlParsed = parse_url($url);
                if (isset($urlParsed['path'])) {
                    $path = (string) $urlParsed['path'];
                } else {
                    $path = '/';
                }

                $path = Common::mb_strtolower($path);
                $return = array($path); // it is important that original value is always set first for equals_exactly and pattern

                // we make sure we have a variation with and without leading slash
                if (strpos($path, '/') === 0) {
                    if (strlen($path) > 1) {
                        $return[] = substr($path, 1);
                    }
                } else {
                    $return[] = '/' . $path;
                }

                // we make sure we also have each version with or without ending slash
                foreach ($return as $path) {
                    if (Common::stringEndsWith($path, '/')) {
                        $return[] = substr($path, 0, -1);
                    } else {
                        $return[] = $path . '/';
                    }
                }

                return $return;

            case self::ATTRIBUTE_QUERY:
                if (!is_string($url)) {
                    return null;
                }

                $urlParsed = parse_url($url); // it is important that original value is always set first for equals_exactly and pattern
                if (isset($urlParsed['query'])) {
                    $query = (string) $urlParsed['query'];
                } else {
                    return null;
                }

                return array($query);
        }
    }

    protected function matchesTargetValue($attributeValues, $pattern, $valueToMatch)
    {
        if (!is_string($valueToMatch) || $valueToMatch === '') {
            return false;
        }

        if ($pattern !== self::PATTERN_REGEXP) {
            $valueToMatch = Common::mb_strtolower($valueToMatch);
        }

        $matches = false;

        foreach ($attributeValues as $attributeValue) {
            if (!isset($attributeValue) || $attributeValue === '') {
                if ($pattern === self::PATTERN_EQUALS_EXACTLY) {
                    break; // we only check the first entry which is always the "original value"
                }
                continue;
            }

            $attributeValue = Common::mb_strtolower($attributeValue);

            switch ($pattern) {
                case self::PATTERN_EQUALS:
                case self::PATTERN_NOT_EQUALS:
                    if ($attributeValue === $valueToMatch) {
                        $matches = true;
                    }
                    break;
                case self::PATTERN_NOT_EQUALS_EXACTLY:
                case self::PATTERN_EQUALS_EXACTLY:
                    if ($attributeValue === $valueToMatch) {
                        $matches = true;
                    }
                    break 2; // we only check the first entry which is always the "original value"
                case self::PATTERN_CONTAINS:
                case self::PATTERN_NOT_CONTAINS:
                    if ($valueToMatch && strpos($attributeValue, $valueToMatch) !== false) {
                        $matches = true;
                    }
                    break;
                case self::PATTERN_STARTS_WITH:
                    if ($valueToMatch && strpos($attributeValue, $valueToMatch) === 0) {
                        $matches = true;
                    }
                    break;
                case self::PATTERN_ENDS_WITH:
                    if ($valueToMatch && Common::stringEndsWith($attributeValue, $valueToMatch)) {
                        $matches = true;
                    }
                    break;
                case self::PATTERN_REGEXP:
                    if ($valueToMatch) {
                        $regex = '/' . str_replace('/', '\/', $valueToMatch) . '/i';
                        if (preg_match($regex, $attributeValue)) {
                            return true;
                        }
                    }

                    break;
            }

            if ($matches) {
                break;
            }
        }

        if (strpos($pattern, self::PATTERN_PREFIX_NOT) === 0) {
            return !$matches;
        }

        return $matches;
    }

    public static function getAvailableFormRules()
    {
        $attributes = array(self::ATTRIBUTE_FORM_NAME, self::ATTRIBUTE_FORM_ID);

        return self::getAvailableRules($attributes);
    }

    public static function getAvailablePageRules()
    {
        $attributes = array(self::ATTRIBUTE_URL, self::ATTRIBUTE_PATH, self::ATTRIBUTE_QUERY);

        return self::getAvailableRules($attributes);
    }

    public static function getPatternTranslations()
    {
        return array(
            self::PATTERN_EQUALS => Piwik::translate('FormAnalytics_RulePatternEquals'),
            self::PATTERN_NOT_EQUALS => Piwik::translate('FormAnalytics_RulePatternNotEquals'),
            self::PATTERN_EQUALS_EXACTLY => Piwik::translate('FormAnalytics_RulePatternEqualsExactly'),
            self::PATTERN_NOT_EQUALS_EXACTLY => Piwik::translate('FormAnalytics_RulePatternNotEqualsExactly'),
            self::PATTERN_CONTAINS => Piwik::translate('FormAnalytics_RulePatternContains'),
            self::PATTERN_NOT_CONTAINS => Piwik::translate('FormAnalytics_RulePatternNotContains'),
            self::PATTERN_STARTS_WITH => Piwik::translate('FormAnalytics_RulePatternStartsWith'),
            self::PATTERN_ENDS_WITH => Piwik::translate('FormAnalytics_RulePatternEndsWith'),
            self::PATTERN_REGEXP => Piwik::translate('FormAnalytics_RulePatternRegExp')
        );
    }

    public static function getAttributeTranslations()
    {
        return array(
            self::ATTRIBUTE_FORM_NAME => Piwik::translate('FormAnalytics_RuleAttributeFormName'),
            self::ATTRIBUTE_FORM_ID => Piwik::translate('FormAnalytics_RuleAttributeFormId'),
            self::ATTRIBUTE_URL => Piwik::translate('FormAnalytics_RuleAttributeUrl'),
            self::ATTRIBUTE_PATH => Piwik::translate('FormAnalytics_RuleAttributePath'),
            self::ATTRIBUTE_QUERY => Piwik::translate('FormAnalytics_RuleAttributeQuery')
        );
    }

    private static function getAvailableRules($attributes)
    {
        $rules = array();

        $attributeTranslations = self::getAttributeTranslations();

        $allAttributes = array(
            self::ATTRIBUTE_FORM_NAME => array(
                'name' => $attributeTranslations[self::ATTRIBUTE_FORM_NAME],
                'example' => 'formName'),
            self::ATTRIBUTE_FORM_ID => array(
                'name' => $attributeTranslations[self::ATTRIBUTE_FORM_ID],
                'example' => 'formId'),
            self::ATTRIBUTE_URL => array(
                'name' => $attributeTranslations[self::ATTRIBUTE_URL],
                'example' => 'https://example.com/signedup?success=1'),
            self::ATTRIBUTE_PATH => array(
                'name' => $attributeTranslations[self::ATTRIBUTE_PATH],
                'example' => '/signedup/successful'),
            self::ATTRIBUTE_QUERY => array(
                'name' => $attributeTranslations[self::ATTRIBUTE_QUERY],
                'example' => 'signedUp=1&test=1')
        );

        $patterns = self::getPatternTranslations();

        foreach ($attributes as $attribute) {

            $urlAttribute = array(
                'key' => $attribute,
                'name' => $allAttributes[$attribute]['name'],
                'patterns' => array(),
                'example' => $allAttributes[$attribute]['example']
            );

            foreach ($patterns as $pattern => $patternLabel) {

                if ($attribute !== self::ATTRIBUTE_URL && in_array($pattern, array(self::PATTERN_EQUALS_EXACTLY, self::PATTERN_NOT_EQUALS_EXACTLY), $strict = true)) {
                    // equals exactly is only supported for url attribute
                    continue;
                }

                $urlAttribute['patterns'][] = array(
                    'key' => $pattern,
                    'name' => $patternLabel,
                );
            }

            $rules[] = $urlAttribute;
        }

        return $rules;
    }
}
