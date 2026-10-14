# TYPO3 extension `allowed_fields_filter`

List only the fields of permitted tables in the "Allowed fields" of backend user groups.

In a backend user group the tab "Record Permissions" contains the field
"Table permissions" (`tables_select`, `tables_modify`) and below it the field
"Allowed fields" (`non_exclude_fields`). The latter lists the exclude fields of
**all** tables, no matter if the group may access the table at all. With many
tables, e.g. with the TYPO3 styleguide, the list gets very long.

This extension reduces the list to the tables which have the table permission
"Read" or "Read & Write".

Supported TYPO3 version: 14.3 LTS

## Installation

```bash
composer require georgringer/allowed_fields_filter
```

The extension is not available on Packagist yet. Until then, add the repository
to your `composer.json` first:

```bash
composer config repositories.allowed_fields_filter vcs https://github.com/georgringer/allowed_fields_filter
composer require georgringer/allowed_fields_filter:dev-main
```

There is nothing to configure and no database change.

## Usage

Edit a backend user group and set the table permissions. Only the tables with
"Read" or "Read & Write" are listed under "Allowed fields".

- **Save the group after changing the table permissions.** The list is built
  when the form is rendered, a table granted just now shows up after saving.
- **Plugin options of `tt_content`** (FlexForm fields) follow the permission of
  `tt_content`.
- **A table must always have a table permission** to get fields listed.

### Groups with fields of tables without table permission

Existing groups may have allowed fields for tables which have no table permission.
Those stay as they are, nothing is removed:

- Only the already allowed fields of such a table are shown, so you can remove
  them, but not add new ones.
- The title of the table is marked with "no table permission" and a warning
  lists the affected tables.

### No fields available

If no table has a permission (e.g. a new group), the field shows a hint instead
of the list. Since TYPO3 v14.2 select fields without items are hidden by default,
therefore the extension sets `showIfEmpty` for the field.

## How it works

An `itemsProcessor` on `be_groups.non_exclude_fields` removes the items after the
core has built the list. There is no XCLASS and no JavaScript.

## Tests

The unit tests in `Tests/Unit` cover the filtering of the items.

## License

GPL-2.0-or-later
