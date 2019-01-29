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

namespace Piwik\Plugins\MediaAnalytics\Archiver;

class GroupedDataArray extends DataArray
{

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        if (!$this->isEmptyLabel($row['label'])) {
            $row['label'] = $this->uniqueResource($row['label']);
        }

        parent::computeMetrics($row);
    }
    
    public function computeMetricsSubtable($secondaryDimension, $parentLabel, $label, $row)
    {
        if (!$this->isEmptyLabel($parentLabel)) {
            $parentLabel = $this->uniqueResource($parentLabel);
        }

        parent::computeMetricsSubtable($secondaryDimension, $parentLabel, $label, $row);
    }

    protected function uniqueResource($resource)
    {
        if (empty($resource)) {
            return $resource;
        }

        $parsed = parse_url(strtolower($resource));

        $resource = '';
        $compose = array('host','path'); // we ignore port, user, pass and fragments

        foreach ($compose as $index) {
            if (isset($parsed[$index])) {
                if ($index === 'path') {
                    $parsedPath = pathinfo($parsed[$index]);

                    if (!empty($parsedPath['dirname'])) {
                        $resource .= $parsedPath['dirname'];

                        if (!empty($parsedPath['filename']) && substr($resource, -1, 1) !== '/') {
                            $resource .= '/';
                        }
                    }

                    $resource .= $parsedPath['filename'];
                } elseif ($index === 'host') {
                    if (strpos($parsed[$index], 'www.') === 0) {
                        $resource .= substr($parsed[$index], 4);
                    } else {
                        $resource .= $parsed[$index];
                    }
                }
            }
        }

        return $resource;
    }

}
