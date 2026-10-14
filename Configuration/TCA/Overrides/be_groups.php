<?php

declare(strict_types=1);

use GeorgRinger\AllowedFieldsFilter\Processor\TablePermissionFilterProcessor;

defined('TYPO3') or die();

// Runs after the "itemsProcFunc" of the core, which builds the list of all exclude fields.
$GLOBALS['TCA']['be_groups']['columns']['non_exclude_fields']['config']['itemsProcessors'][100] = [
    'class' => TablePermissionFilterProcessor::class,
];
// The filter may remove all items, e.g. for a new group. Since TYPO3 v14.2 such a field would be hidden,
// but the field and its description are needed to explain how to get fields listed.
$GLOBALS['TCA']['be_groups']['columns']['non_exclude_fields']['config']['showIfEmpty'] = true;
$GLOBALS['TCA']['be_groups']['columns']['non_exclude_fields']['description'] = 'LLL:EXT:allowed_fields_filter/Resources/Private/Language/locallang.xlf:non_exclude_fields.description';
