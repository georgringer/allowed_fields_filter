<?php

declare(strict_types=1);

namespace GeorgRinger\AllowedFieldsFilter\Tests\Unit\Processor;

use GeorgRinger\AllowedFieldsFilter\Processor\TablePermissionFilterProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\ItemsProcessorContext;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Schema\Struct\SelectItem;
use TYPO3\CMS\Core\Schema\Struct\SelectItemCollection;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

final class TablePermissionFilterProcessorTest extends TestCase
{
    private FlashMessageQueue $queue;

    protected function setUp(): void
    {
        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(static fn(string $key): string => match (substr($key, strrpos($key, ':') + 1)) {
            'group.noTablePermission' => 'no table permission',
            'group.empty' => 'no fields available',
            'warning.title' => 'Fields without table permission',
            'warning.message' => 'Tables without permission: %s',
            default => $key,
        });
        $GLOBALS['LANG'] = $languageService;
        $this->queue = new FlashMessageQueue('test');
    }

    #[Test]
    public function keepsOnlyTablesWithSelectOrModifyPermission(): void
    {
        $result = $this->process(
            ['tables_modify' => 'pages', 'tables_select' => 'tt_content,pages'],
            $this->items()
        );

        self::assertSame(
            ['Pages', 'pages:title', 'pages:nav_title', 'Content', 'tt_content:header'],
            $this->labelsAndValues($result)
        );
        self::assertCount(0, $this->queue->getAllMessages());
    }

    #[Test]
    public function readsTablePermissionsFromTheFormEngineStructure(): void
    {
        $result = $this->process(
            ['tables_modify' => ['modify' => ['pages'], 'select' => ['tt_content']]],
            $this->items()
        );

        self::assertSame(
            ['Pages', 'pages:title', 'pages:nav_title', 'Content', 'tt_content:header'],
            $this->labelsAndValues($result)
        );
    }

    #[Test]
    public function showsOnlyAHintWithoutAnyTablePermission(): void
    {
        $result = $this->process([], $this->items());

        // without any item the form element would not be rendered at all
        self::assertSame(['no fields available'], $this->labelsAndValues($result));
    }

    #[Test]
    public function keepsStoredFieldsOfTablesWithoutPermissionAndWarns(): void
    {
        $result = $this->process(
            [
                'tables_modify' => 'pages',
                'non_exclude_fields' => 'tt_content:header,pages:title',
            ],
            $this->items()
        );

        // only the stored field of tt_content is kept, the header is marked
        self::assertSame(
            [
                'Pages', 'pages:title', 'pages:nav_title',
                'Content – no table permission', 'tt_content:header',
            ],
            $this->labelsAndValues($result)
        );
        $messages = $this->queue->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::WARNING, $messages[0]->getSeverity());
        self::assertStringContainsString('Content', $messages[0]->getMessage());
    }

    #[Test]
    public function acceptsStoredFieldsAsList(): void
    {
        $result = $this->process(
            ['non_exclude_fields' => ['pages:title']],
            $this->items()
        );

        self::assertSame(['Pages – no table permission', 'pages:title'], $this->labelsAndValues($result));
    }

    #[Test]
    public function flexFormGroupsFollowThePermissionOfTheirTable(): void
    {
        $items = new SelectItemCollection();
        $items->add($this->item('Plugin A', '--div--'));
        $items->add($this->item('Field', 'tt_content:pi_flexform;news;settings.x'));

        self::assertSame(
            ['Plugin A', 'tt_content:pi_flexform;news;settings.x'],
            $this->labelsAndValues($this->process(['tables_select' => 'tt_content'], $items))
        );
        self::assertSame(
            ['no fields available'],
            $this->labelsAndValues($this->process(['tables_select' => 'pages'], $items))
        );
    }

    private function process(array $row, SelectItemCollection $items): SelectItemCollection
    {
        $flashMessageService = $this->createMock(FlashMessageService::class);
        $flashMessageService->method('getMessageQueueByIdentifier')->willReturn($this->queue);

        return (new TablePermissionFilterProcessor($flashMessageService))->processItems(
            $items,
            new ItemsProcessorContext(
                table: 'be_groups',
                field: 'non_exclude_fields',
                row: $row,
                fieldConfiguration: [],
                processorParameters: [],
                realPid: 0,
                site: new NullSite(),
            )
        );
    }

    private function items(): SelectItemCollection
    {
        $items = new SelectItemCollection();
        $items->add($this->item('Pages', '--div--'));
        $items->add($this->item('Title', 'pages:title'));
        $items->add($this->item('Navigation title', 'pages:nav_title'));
        $items->add($this->item('Content', '--div--'));
        $items->add($this->item('Header', 'tt_content:header'));
        $items->add($this->item('Users', '--div--'));
        $items->add($this->item('Name', 'fe_users:name'));

        return $items;
    }

    private function item(string $label, string $value): SelectItem
    {
        return new SelectItem('select', $label, $value);
    }

    /**
     * @return string[] labels of the headers and values of the fields
     */
    private function labelsAndValues(SelectItemCollection $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = $item->isDivider() ? $item->getLabel() : (string)$item->getValue();
        }

        return $result;
    }
}
