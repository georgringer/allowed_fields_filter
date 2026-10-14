<?php

declare(strict_types=1);

namespace GeorgRinger\AllowedFieldsFilter\Processor;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\ItemsProcessorContext;
use TYPO3\CMS\Core\DataHandling\ItemsProcessorInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Schema\Struct\SelectItem;
use TYPO3\CMS\Core\Schema\Struct\SelectItemCollection;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reduces the "Allowed fields" (non_exclude_fields) of a backend user group to the
 * fields of tables, which have the table permission "select" or "modify".
 *
 * Fields which are already stored for a table without table permission are kept, but only
 * those. Otherwise they would silently vanish with the next save, as the checkboxes are
 * not rendered and therefore not submitted. Those groups are marked and a warning is shown.
 */
#[Autoconfigure(public: true)]
final readonly class TablePermissionFilterProcessor implements ItemsProcessorInterface
{
    private const LLL = 'LLL:EXT:allowed_fields_filter/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private FlashMessageService $flashMessageService,
    ) {}

    public function processItems(SelectItemCollection $items, ItemsProcessorContext $context): SelectItemCollection
    {
        $permittedTables = array_flip($this->getPermittedTables($context->row));
        $storedFields = array_flip($this->toList($context->row['non_exclude_fields'] ?? []));

        $result = new SelectItemCollection();
        $tablesWithoutPermission = [];
        foreach ($this->groupByHeader($items) as $group) {
            if ($group['table'] === null || isset($permittedTables[$group['table']])) {
                $this->addGroup($result, $group['header'], $group['fields']);
                continue;
            }

            $storedGroupFields = array_filter(
                $group['fields'],
                static fn(SelectItem $field): bool => isset($storedFields[(string)$field->getValue()])
            );
            if ($storedGroupFields === []) {
                continue;
            }
            $header = $group['header'];
            $tablesWithoutPermission[$header?->getLabel() ?? $group['table']] = true;
            $this->addGroup(
                $result,
                $header?->withLabel($header->getLabel() . ' – ' . $this->translate('group.noTablePermission')),
                $storedGroupFields
            );
        }

        if ($tablesWithoutPermission !== []) {
            $this->addWarning(array_keys($tablesWithoutPermission));
        }

        // The select checkbox element renders nothing at all without items, not even the label of the field.
        // A single header explains why the list is empty.
        if ($result->count() === 0) {
            $result->add(new SelectItem('select', $this->translate('group.empty'), '--div--'));
        }

        return $result;
    }

    /**
     * @param SelectItem[] $fields
     */
    private function addGroup(SelectItemCollection $collection, ?SelectItem $header, array $fields): void
    {
        if ($header !== null) {
            $collection->add($header);
        }
        foreach ($fields as $field) {
            $collection->add($field);
        }
    }

    /**
     * Splits the flat item list into groups, each starting with a header ("--div--").
     * The table of a group is taken from the first field, e.g. "tt_content" of "tt_content:header".
     *
     * @return list<array{header: ?SelectItem, table: ?string, fields: list<SelectItem>}>
     */
    private function groupByHeader(SelectItemCollection $items): array
    {
        $groups = [];
        $current = null;
        foreach ($items as $item) {
            if ($item->isDivider()) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['header' => $item, 'table' => null, 'fields' => []];
                continue;
            }
            $current ??= ['header' => null, 'table' => null, 'fields' => []];
            $current['table'] ??= $this->getTable((string)$item->getValue());
            $current['fields'][] = $item;
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    private function getTable(string $value): ?string
    {
        $table = strstr($value, ':', true);

        return $table === false || $table === '' ? null : $table;
    }

    /**
     * Tables with table permission "select" or "modify".
     *
     * @return string[]
     */
    private function getPermittedTables(array $row): array
    {
        $modify = $row['tables_modify'] ?? [];
        // the form data provider of the table permission element turns the value into ['modify' => [], 'select' => []]
        if (is_array($modify) && (isset($modify['modify']) || isset($modify['select']))) {
            $tables = [...$this->toList($modify['modify'] ?? []), ...$this->toList($modify['select'] ?? [])];
        } else {
            $tables = $this->toList($modify);
        }

        return array_values(array_unique([...$tables, ...$this->toList($row['tables_select'] ?? [])]));
    }

    /**
     * @return string[]
     */
    private function toList(mixed $value): array
    {
        if (is_string($value)) {
            return GeneralUtility::trimExplode(',', $value, true);
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn(mixed $entry): string => trim((string)$entry), $value)));
        }

        return [];
    }

    /**
     * @param string[] $tableTitles
     */
    private function addWarning(array $tableTitles): void
    {
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
            sprintf($this->translate('warning.message'), implode(', ', $tableTitles)),
            $this->translate('warning.title'),
            ContextualFeedbackSeverity::WARNING,
            false
        ));
    }

    private function translate(string $key): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;

        return $languageService instanceof LanguageService ? $languageService->sL(self::LLL . $key) : $key;
    }
}
