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
namespace Piwik\Plugins\FormAnalytics;
use Piwik\Piwik;


/**
 * API for plugin FormAnalytics
 *
 * @method static \Piwik\Plugins\FormAnalytics\Metrics getInstance()
 */
class Metrics
{
    const SUM_FORM_VIEWS = 'nb_form_views';
    const SUM_FORM_VIEWERS = 'nb_form_viewers';

    const SUM_FORM_STARTS = 'nb_form_starts';
    const SUM_FORM_STARTERS = 'nb_form_starters';
    const RATE_FORM_STARTERS = 'form_starters_rate';

    const SUM_FORM_SUBMISSIONS = 'nb_form_submissions';
    const SUM_FORM_SUBMITTERS = 'nb_form_submitters';
    const RATE_FORM_SUBMITTER = 'form_submitter_rate';

    const SUM_FORM_RESUBMITTERS = 'nb_form_resubmitters';
    const RATE_FORM_RESUBMITTERS = 'form_resubmitters_rate';

    const SUM_FORM_CONVERSIONS = 'nb_form_conversions';
    const RATE_FORM_CONVERSION = 'form_conversion_rate';

    const SUM_FORM_HESITATION_TIME = 'nb_form_time_hesitation';
    const AVG_FORM_HESITATION_TIME = 'avg_form_time_hesitation';
    const AVG_FORM_TIME_SPENT = 'avg_form_time_spent';

    const SUM_FORM_TIME_TO_FIRST_SUBMISSION = 'nb_form_time_to_first_submission';
    const AVG_FORM_TIME_TO_FIRST_SUBMISSION = 'avg_form_time_to_first_submission';
    const SUM_FORM_TIME_TO_CONVERSION = 'nb_form_time_to_conversion';
    const SUM_FORM_TIME_SPENT = 'nb_form_time_spent';
    const AVG_FORM_TIME_TO_CONVERSION = 'avg_form_time_to_conversion';

    const SUM_FIELD_ENTRIES = 'nb_field_entries';
    const SUM_FIELD_UNIQUE_ENTRIES = 'nb_field_uniq_entries';

    const SUM_FIELD_DROPOFFS = 'nb_field_dropoff';
    const SUM_FIELD_UNIQUE_DROPOFFS = 'nb_field_uniq_dropoff';

    const SUM_FIELD_UNIQUE_INTERACTIONS = 'nb_field_uniq_interactions';
    const SUM_FIELD_INTERACTIONS = 'nb_field_interactions';
    const SUM_FIELD_INTERACTIONS_SUBMIT = 'nb_field_interactions_submitted';
    const SUM_FIELD_INTERACTIONS_UNSUBMIT = 'nb_field_interactions_unsubmitted';

    const SUM_FIELD_TIME_SPENT = 'nb_field_time_spent';
    const SUM_FIELD_FIELDS_WITH_TIME_SPENT = 'nb_field_with_time_spent';
    const AVG_FIELD_TIME_SPENT = 'avg_field_time_spent';
    const SUM_FIELD_HESITATION_TIME = 'nb_field_hesitation_time';
    const SUM_FIELD_FIELDS_WITH_HESITATION_TIME = 'nb_field_with_hesitation_time';
    const AVG_FIELD_HESITATION_TIME = 'avg_field_hesitation_time';

    const SUM_FIELD_SUBMITTED = 'nb_field_views_submitted';
    const SUM_FIELD_CONVERTED = 'nb_field_views_converted';

    const SUM_FIELD_LEFTBLANK_SUBMITTED = 'nb_field_leftblank_submitted';
    const RATE_FIELD_LEFTBLANK_SUBMITTED = 'avg_field_leftblank_submitted';
    const SUM_FIELD_LEFTBLANK_CONVERTED = 'nb_field_leftblank_converted';
    const RATE_FIELD_LEFTBLANK_CONVERTED = 'avg_field_leftblank_converted';

    const SUM_FIELD_SUBMITTED_WITH_FIELDSIZE = 'nb_field_submitted_with_fieldsize';
    const SUM_FIELD_CONVERTED_WITH_FIELDSIZE = 'nb_field_converted_with_fieldsize';
    const SUM_FIELD_WITH_FIELDSIZE = 'nb_field_with_fieldsize';
    const SUM_FIELD_FIELDSIZE_SUBMITTED = 'nb_field_size_submitted';
    const SUM_FIELD_FIELDSIZE_UNSUBMITTED = 'nb_field_size_unsubmitted';
    const SUM_FIELD_FIELDSIZE_CONVERTED = 'nb_field_size_converted';
    const SUM_FIELD_FIELDSIZE = 'nb_field_size';

    const AVG_FIELD_FIELDSIZE_SUBMITTED = 'avg_field_size_submitted';
    const AVG_FIELD_FIELDSIZE_UNSUBMITTED = 'avg_field_size_unsubmitted';
    const AVG_FIELD_FIELDSIZE_CONVERTED = 'avg_field_size_converted';
    const AVG_FIELD_FIELDSIZE = 'avg_field_size';

    const SUM_FIELD_UNIQUE_REFOCUS = 'nb_field_uniq_refocuses';
    const SUM_FIELD_REFOCUSES = 'nb_field_refocuses';
    const SUM_FIELD_UNIQUE_AMENDMENTS = 'nb_field_uniq_amendments';
    const SUM_FIELD_AMENDMENTS = 'nb_field_amendments';
    const SUM_FIELD_TOTAL_CHANGES = 'nb_field_changes';
    const SUM_FIELD_UNIQUE_CHANGES = 'nb_field_uniq_changes';
    const RATE_FIELD_AMENDMENTS = 'field_amendments_rate';
    const RATE_FIELD_REFOCUS = 'field_refocus_rate';
    const RATE_FIELD_DELETES = 'field_delete_rate';
    const RATE_FIELD_CURSORS = 'field_cursor_rate';

    const SUM_FIELD_UNIQUE_DELETES = 'nb_field_uniq_deletes';
    const SUM_FIELD_DELETES = 'nb_field_deletes';
    const SUM_FIELD_UNIQUE_CURSOR = 'nb_field_uniq_cursor';
    const SUM_FIELD_CURSOR = 'nb_field_cursor';

    public static function getNumericFormMetrics()
    {
        return array(
            Metrics::SUM_FORM_VIEWS,
            Metrics::SUM_FORM_VIEWERS,
            Metrics::SUM_FORM_STARTS,
            Metrics::SUM_FORM_STARTERS,
            Metrics::SUM_FORM_HESITATION_TIME,
            Metrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION,
            Metrics::SUM_FORM_TIME_SPENT,
            Metrics::SUM_FORM_SUBMISSIONS,
            Metrics::SUM_FORM_SUBMITTERS,
            Metrics::SUM_FORM_RESUBMITTERS,
            Metrics::SUM_FORM_TIME_TO_CONVERSION,
            Metrics::SUM_FORM_CONVERSIONS,
        );
    }

    public static function getMetricsTranslations()
    {
        return array(
            Metrics::SUM_FORM_VIEWS => 'FormAnalytics_ColumnFormViews',
            Metrics::SUM_FORM_VIEWERS => 'FormAnalytics_ColumnFormViewers',
            Metrics::SUM_FORM_STARTS => 'FormAnalytics_ColumnFormStarts',
            Metrics::SUM_FORM_STARTERS => 'FormAnalytics_ColumnFormStarters',
            Metrics::SUM_FORM_SUBMITTERS => 'FormAnalytics_ColumnFormSubmitters',
            Metrics::SUM_FORM_SUBMISSIONS => 'FormAnalytics_ColumnFormSubmissions',
            Metrics::SUM_FORM_HESITATION_TIME => 'FormAnalytics_ColumnHesitationTime',
            Metrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION => 'FormAnalytics_ColumnTimeToFirstSubmit',
            Metrics::SUM_FORM_RESUBMITTERS => 'FormAnalytics_ColumnFormResubmitters',
            Metrics::SUM_FORM_TIME_TO_CONVERSION => 'FormAnalytics_ColumnTimeToConversion',
            Metrics::SUM_FORM_CONVERSIONS => 'FormAnalytics_ColumnFormConversions',
            Metrics::SUM_FORM_TIME_SPENT => 'FormAnalytics_ColumnTimeSpent',
            Metrics::AVG_FORM_TIME_SPENT => 'FormAnalytics_ColumnFormAvgTimeSpent',
            Metrics::AVG_FORM_HESITATION_TIME => 'FormAnalytics_ColumnFormAvgHesitationTime',
            Metrics::AVG_FORM_TIME_TO_CONVERSION => 'FormAnalytics_ColumnAvgTimeToConversion',
            Metrics::AVG_FORM_TIME_TO_FIRST_SUBMISSION => 'FormAnalytics_ColumnAvgTimeToFirstSubmit',
            Metrics::RATE_FORM_CONVERSION => 'FormAnalytics_ColumnFormConversionRate',
            Metrics::RATE_FORM_STARTERS => 'FormAnalytics_ColumnRateStarters',
            Metrics::SUM_FIELD_DROPOFFS => 'FormAnalytics_ColumnDropOffs',
            Metrics::SUM_FIELD_UNIQUE_DROPOFFS => 'FormAnalytics_ColumnUniqueDropOffs',
            Metrics::SUM_FIELD_ENTRIES => 'FormAnalytics_ColumnEntries',
            Metrics::SUM_FIELD_UNIQUE_ENTRIES => 'FormAnalytics_ColumnUniqueEntries',
            Metrics::SUM_FIELD_UNIQUE_AMENDMENTS => 'FormAnalytics_ColumnUniqueAmendments',
            Metrics::SUM_FIELD_UNIQUE_REFOCUS => 'FormAnalytics_ColumnUniqueRefocuses',
            Metrics::SUM_FIELD_UNIQUE_CHANGES => 'FormAnalytics_ColumnUniqueChanges',
            Metrics::SUM_FIELD_UNIQUE_DELETES => 'FormAnalytics_ColumnUniqueDeletes',
            Metrics::SUM_FIELD_DELETES => 'FormAnalytics_ColumnDeletes',
            Metrics::SUM_FIELD_UNIQUE_CURSOR => 'FormAnalytics_ColumnUniqueCursors',
            Metrics::SUM_FIELD_CURSOR => 'FormAnalytics_ColumnCursors',
            Metrics::SUM_FIELD_AMENDMENTS => 'FormAnalytics_ColumnAmendments',
            Metrics::SUM_FIELD_REFOCUSES => 'FormAnalytics_ColumnRefocuses',
            Metrics::SUM_FIELD_TOTAL_CHANGES => 'FormAnalytics_ColumnChanges',
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS => 'FormAnalytics_ColumnUniqueInteractions',
            Metrics::SUM_FIELD_INTERACTIONS => 'FormAnalytics_ColumnInteractions',
            Metrics::SUM_FIELD_INTERACTIONS_UNSUBMIT => 'FormAnalytics_ColumnInteractionsUnsubmitted',
            Metrics::SUM_FIELD_TIME_SPENT => 'FormAnalytics_ColumnTimeSpent',
            Metrics::AVG_FIELD_TIME_SPENT => 'FormAnalytics_ColumnAvgTimeSpent',
            Metrics::SUM_FIELD_HESITATION_TIME => 'FormAnalytics_ColumnHesitationTime',
            Metrics::AVG_FIELD_HESITATION_TIME => 'FormAnalytics_ColumnAvgHesitationTime',
            Metrics::AVG_FIELD_FIELDSIZE => 'FormAnalytics_ColumnAvgFieldSize',
            Metrics::AVG_FIELD_FIELDSIZE_UNSUBMITTED => 'FormAnalytics_ColumnAvgFieldSizeUnsubmitted',
            Metrics::AVG_FIELD_FIELDSIZE_SUBMITTED => 'FormAnalytics_ColumnAvgFieldSizeSubmit',
            Metrics::AVG_FIELD_FIELDSIZE_CONVERTED => 'FormAnalytics_ColumnAvgFieldSizeConverted',
            Metrics::SUM_FIELD_SUBMITTED => 'FormAnalytics_ColumnFieldSubmissions',
            Metrics::SUM_FIELD_LEFTBLANK_SUBMITTED => 'FormAnalytics_ColumnLeftBlankSubmit',
            Metrics::SUM_FIELD_LEFTBLANK_CONVERTED => 'FormAnalytics_ColumnLeftBlankConverted',
            Metrics::RATE_FIELD_LEFTBLANK_SUBMITTED => 'FormAnalytics_ColumnRateLeftBlankSubmit',
            Metrics::RATE_FIELD_LEFTBLANK_CONVERTED => 'FormAnalytics_ColumnRateLeftBlankConverted',
            Metrics::RATE_FIELD_AMENDMENTS => 'FormAnalytics_ColumnRateAmendment',
            Metrics::RATE_FIELD_REFOCUS => 'FormAnalytics_ColumnRateRefocus',
            Metrics::RATE_FIELD_DELETES => 'FormAnalytics_ColumnRateDeletes',
            Metrics::RATE_FIELD_CURSORS => 'FormAnalytics_ColumnRateCursors',
            Metrics::RATE_FORM_RESUBMITTERS => 'FormAnalytics_ColumnRateResubmitter',
            Metrics::RATE_FORM_SUBMITTER => 'FormAnalytics_ColumnRateSubmitter',
            Metrics::SUM_FIELD_INTERACTIONS_SUBMIT => 'FormAnalytics_ColumnInteractionsSubmitted',
            Metrics::SUM_FIELD_CONVERTED => 'FormAnalytics_ColumnFieldConversions',
        );
    }

    public static function getMetricsDocumentationTranslations()
    {
        return array(
            Metrics::SUM_FORM_VIEWS => 'FormAnalytics_ColumnDescriptionNbFormViews',
            Metrics::SUM_FORM_VIEWERS => 'FormAnalytics_ColumnDescriptionNbFormViewers',
            Metrics::SUM_FORM_STARTS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormStarts') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormStarts'),
            Metrics::SUM_FORM_STARTERS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormStarters') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormStarts'),
            Metrics::SUM_FORM_TIME_TO_FIRST_SUBMISSION => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormTimeToFirstSubmission') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeToFirstSubmission'),
            Metrics::SUM_FORM_SUBMITTERS => 'FormAnalytics_ColumnDescriptionNbFormSubmitters',
            Metrics::SUM_FORM_RESUBMITTERS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormResubmitters') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoResubmitters'),
            Metrics::SUM_FORM_HESITATION_TIME => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormTimeHesitation') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeHesitation'),
            Metrics::SUM_FORM_SUBMISSIONS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormSubmissions'),
            Metrics::SUM_FORM_TIME_TO_CONVERSION => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormTimeToConversion') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeToConversion'),
            Metrics::SUM_FORM_TIME_SPENT => Piwik::translate('FormAnalytics_ColumnDescriptionNbFormTimeSpent') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeSpent'),
            Metrics::SUM_FORM_CONVERSIONS => 'FormAnalytics_ColumnDescriptionNbFormConversions',
            Metrics::SUM_FIELD_DROPOFFS => 'FormAnalytics_ColumnDescriptionNbFieldDropoff',
            Metrics::SUM_FIELD_UNIQUE_DROPOFFS => 'FormAnalytics_ColumnDescriptionNbFieldUniqDropoff',
            Metrics::SUM_FIELD_ENTRIES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldEntries') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoInteraction'),
            Metrics::SUM_FIELD_UNIQUE_ENTRIES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqEntries') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoInteraction'),
            Metrics::SUM_FIELD_UNIQUE_AMENDMENTS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqAmendments') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldAmendments'),
            Metrics::SUM_FIELD_AMENDMENTS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldAmendments') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldAmendments'),
            Metrics::SUM_FIELD_UNIQUE_REFOCUS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqRefocuses') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldRefocuses'),
            Metrics::SUM_FIELD_REFOCUSES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldRefocuses') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldRefocuses'),
            Metrics::SUM_FIELD_UNIQUE_DELETES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqDeletes') . ' ' . Piwik::translate('FormAnalytics_MetricOnlyForTextField'),
            Metrics::SUM_FIELD_DELETES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldDeletes') . ' ' . Piwik::translate('FormAnalytics_MetricOnlyForTextField'),
            Metrics::SUM_FIELD_UNIQUE_CURSOR => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqCursor') . ' ' . Piwik::translate('FormAnalytics_MetricOnlyForTextField'),
            Metrics::SUM_FIELD_CURSOR => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldCursor') . ' ' . Piwik::translate('FormAnalytics_MetricOnlyForTextField'),
            Metrics::SUM_FIELD_SUBMITTED => 'FormAnalytics_ColumnDescriptionNbFieldSubmitted',
            Metrics::SUM_FIELD_UNIQUE_CHANGES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqChanges') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldChanges'),
            Metrics::SUM_FIELD_TOTAL_CHANGES => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldChanges') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldChanges'),
            Metrics::SUM_FIELD_UNIQUE_INTERACTIONS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldUniqueInteractions') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldInteractions'),
            Metrics::SUM_FIELD_INTERACTIONS => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldInteractions') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldInteractions'),
            Metrics::SUM_FIELD_INTERACTIONS_SUBMIT => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldInteractionsSubmit') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldInteractions'),
            Metrics::SUM_FIELD_INTERACTIONS_UNSUBMIT => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldInteractionsUnsubmit') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldInteractions'),
            Metrics::SUM_FIELD_TIME_SPENT => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldTimeSpent') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldTimeSpent'),
            Metrics::AVG_FIELD_TIME_SPENT => Piwik::translate('FormAnalytics_ColumnDescriptionAvgFieldTimeSpent') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldTimeSpent'),
            Metrics::SUM_FIELD_HESITATION_TIME => Piwik::translate('FormAnalytics_ColumnDescriptionNbFieldFieldsHesitationTime') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldFieldsHesitationTime'),
            Metrics::AVG_FIELD_HESITATION_TIME => Piwik::translate('FormAnalytics_ColumnDescriptionAvgFieldFieldsHesitationTime') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldFieldsHesitationTime'),
            Metrics::AVG_FIELD_FIELDSIZE => 'FormAnalytics_ColumnDescriptionAvgFieldFieldsize',
            Metrics::AVG_FIELD_FIELDSIZE_UNSUBMITTED => 'FormAnalytics_ColumnDescriptionAvgFieldFieldsizeUnsubmitted',
            Metrics::AVG_FIELD_FIELDSIZE_SUBMITTED => 'FormAnalytics_ColumnDescriptionAvgFieldFieldsizeSubmitted',
            Metrics::AVG_FIELD_FIELDSIZE_CONVERTED => 'FormAnalytics_ColumnDescriptionAvgFieldFieldsizeConverted',
            Metrics::SUM_FIELD_LEFTBLANK_SUBMITTED => 'FormAnalytics_ColumnDescriptionNbFieldLeftblankSubmitted',
            Metrics::SUM_FIELD_LEFTBLANK_CONVERTED => 'FormAnalytics_ColumnDescriptionNbFieldLeftblankConverted',
            Metrics::RATE_FIELD_LEFTBLANK_SUBMITTED => 'FormAnalytics_ColumnDescriptionAvgFieldLeftblankSubmitted',
            Metrics::RATE_FIELD_LEFTBLANK_CONVERTED => 'FormAnalytics_ColumnDescriptionAvgFieldLeftblankConverted',
            Metrics::SUM_FIELD_CONVERTED => 'FormAnalytics_ColumnDescriptionNbFieldConverted',
            Metrics::RATE_FORM_RESUBMITTERS => Piwik::translate('FormAnalytics_ColumnDescriptionFormResubmittersRate') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoResubmitters'),
            Metrics::RATE_FORM_SUBMITTER => 'FormAnalytics_ColumnDescriptionFormSubmitterRate',
            Metrics::RATE_FORM_CONVERSION => 'FormAnalytics_ColumnDescriptionFormConversionRate',
            Metrics::RATE_FORM_STARTERS => Piwik::translate('FormAnalytics_ColumnDescriptionFormStartersRate') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormStarts'),
            Metrics::AVG_FORM_TIME_SPENT => Piwik::translate('FormAnalytics_ColumnDescriptionAvgFormTimeSpent') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeSpent'),
            Metrics::AVG_FORM_HESITATION_TIME => Piwik::translate('FormAnalytics_ColumnDescriptionAvgFormTimeHesitation') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeHesitation'),
            Metrics::AVG_FORM_TIME_TO_CONVERSION => Piwik::translate('FormAnalytics_ColumnDescriptionAvgFormTimeToConversion') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeToConversion'),
            Metrics::AVG_FORM_TIME_TO_FIRST_SUBMISSION => Piwik::translate('FormAnalytics_ColumnDescriptionAvgFormTimeToFirstSubmission') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFormTimeToFirstSubmission'),
            Metrics::RATE_FIELD_AMENDMENTS => Piwik::translate('FormAnalytics_ColumnDescriptionFieldAmendmentsRate') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldAmendments'),
            Metrics::RATE_FIELD_REFOCUS => Piwik::translate('FormAnalytics_ColumnDescriptionFieldRefocusRate') . ' ' . Piwik::translate('FormAnalytics_ColumnInfoFieldRefocuses'),
            Metrics::RATE_FIELD_DELETES => Piwik::translate('FormAnalytics_ColumnDescriptionRateFieldDeletes') . ' ' . Piwik::translate('FormAnalytics_MetricOnlyForTextField'),
            Metrics::RATE_FIELD_CURSORS => Piwik::translate('FormAnalytics_ColumnDescriptionRateFieldCursors') . ' ' . Piwik::translate('FormAnalytics_MetricOnlyForTextField'),
        );
    }

}
