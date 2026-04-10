<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskEntryHelper {

    public function updateMetaField(int $entry_id, int $field_id, $value): bool
    {
        $ok = \FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $value);
        if (!$ok) {
            if (method_exists('\FrmEntryMeta', 'delete_entry_meta')) {
                \FrmEntryMeta::delete_entry_meta($entry_id, $field_id);
            }
            \FrmEntryMeta::add_entry_meta($entry_id, $field_id, '', $value);
        }
        return true;
    }

}