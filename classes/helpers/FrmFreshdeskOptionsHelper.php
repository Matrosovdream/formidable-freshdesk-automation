<?php

if ( ! defined('ABSPATH') ) { exit; }

return ;

/**
 * Options helper: all get_option / update_option logic lives here.
 */
final class FrmFreshdeskOptionsHelper {

    /** Option key in wp_options */
    private const OPTION_KEY = 'frm_freshdesk';

    /** Default options shape */
    public static function defaults(): array {
        return [
            'api_key'        => '',
            'entry_statuses' => [
                // placeholder for later
                // 'Open' => 'Verified',
            ],
        ];
    }

    /** Get the full options array (merged with defaults) */
    public static function get_options(): array {
        $stored = get_option(self::OPTION_KEY, []);
        if ( ! is_array($stored) ) {
            $stored = [];
        }
        return array_replace_recursive(self::defaults(), $stored);
    }

    /** Update the full options array */
    public static function set_options(array $options): bool {
        $merged = array_replace_recursive(self::get_options(), $options);
        return (bool) update_option(self::OPTION_KEY, $merged, false);
    }

    /** Get a single option by key with default fallback */
    public static function get(string $key, $default = null) {
        $opts = self::get_options();
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    /** Set a single option by key */
    public static function set(string $key, $value): bool {
        $opts = self::get_options();
        $opts[$key] = $value;
        return (bool) update_option(self::OPTION_KEY, $opts, false);
    }

    /** API key convenience methods */
    public static function get_api_key(): string {
        $val = self::get('api_key', '');
        return is_string($val) ? $val : '';
    }

    public static function set_api_key(string $api_key): bool {
        $api_key = trim($api_key);
        return self::set('api_key', $api_key);
    }

    /** Entry statuses convenience methods (placeholder for later expansion) */
    public static function get_entry_statuses(): array {
        $val = self::get('entry_statuses', []);
        return is_array($val) ? $val : [];
    }

    public static function set_entry_statuses(array $map): bool {
        return self::set('entry_statuses', $map);
    }
}