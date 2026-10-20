# The "itemsProcessors" added in TCA make the core schema generation treat this field
# as a comma separated integer list (varchar 255), which would truncate the stored fields.
CREATE TABLE be_groups (
    non_exclude_fields longtext
);
