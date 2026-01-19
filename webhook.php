<?php
/**
 * Plugin Name: Frm Freshdesk Webhook Logger
 * Description: Webhook endpoint /wp-json/frm/freshdesk/ticket/created that logs incoming JSON payloads and processes Formidable entry status changes.
 * Version: 1.1.0
 * Author: Your Name
 */

if ( ! defined('ABSPATH') ) { exit; }

final class Frm_Freshdesk_Webhook_Logger {

    private const NS    = 'frm';
    private const ROUTE = 'freshdesk/ticket/created';
    private const LOG_FILENAME = 'freshdesk_log.log';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(
            self::NS,
            '/' . self::ROUTE,
            [
                'methods'             => ['POST', 'GET'],
                'callback'            => [__CLASS__, 'handle'],
                'permission_callback' => '__return_true', // no auth
            ]
        );
    }

    public static function handle(\WP_REST_Request $request): \WP_REST_Response {

        $raw = (string) $request->get_body();

        $json = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        // 1) Always log the inbound payload
        self::log($json);

        // 2) Process it (if JSON is valid and contains expected keys)
        if (is_array($json)) {
            $result = Frm_Freshdesk_Webhook_processer::process($json);

            self::log([
                'type'        => 'processor_result',
                'received_at' => current_time('mysql'),
                'result'      => $result,
            ]);
        } else {
            self::log([
                'type'        => 'processor_skipped',
                'received_at' => current_time('mysql'),
                'reason'      => 'invalid_json',
            ]);
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }

    public static function log(array $data): void {
        $file = self::get_log_file_path();

        $line = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = '{"error":"json_encode_failed","received_at":"' . esc_js(current_time('mysql')) . '"}';
        }
        $line .= PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function get_log_file_path(): string {
        // SAME folder as this plugin file
        return plugin_dir_path(__FILE__) . self::LOG_FILENAME;
    }

    private static function get_client_ip(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
            return (string) ($parts[0] ?? '');
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function safe_headers(array $headers): array {
        $blocked = ['authorization', 'cookie'];
        $out = [];

        foreach ($headers as $k => $v) {
            $lk = strtolower((string) $k);
            if (in_array($lk, $blocked, true)) {
                $out[$k] = ['[blocked]'];
                continue;
            }
            $out[$k] = is_array($v) ? $v : [$v];
        }

        return $out;
    }
}

final class Frm_Freshdesk_Webhook_processer {

    /**
     * Field IDs in wp_frm_item_metas
     */
    private const EMAIL_FIELD_ID  = 4;
    private const STATUS_FIELD_ID = 7;

    /**
     * Status values
     */
    private const STATUS_MATCH = 'Verified';
    private const STATUS_SET   = 'Processing-E';

    /**
     * Expected payload path:
     * $payload['freshdesk_webhook']['ticket_contact_email']
     */
    public static function process(array $payload): array {
        global $wpdb;

        $email = self::extract_email($payload);
        if ($email === '') {
            return [
                'ok'     => false,
                'action' => 'none',
                'reason' => 'missing_ticket_contact_email',
            ];
        }

        $table = $wpdb->prefix . 'frm_item_metas';

        // Find item_id(s) that have:
        // - field_id=4 meta_value=email
        // - AND field_id=7 meta_value='verified'
        $sql = "
            SELECT DISTINCT e.item_id
            FROM {$table} e
            INNER JOIN {$table} s
                ON s.item_id = e.item_id
               AND s.field_id = %d
               AND s.meta_value = %s
            WHERE e.field_id = %d
              AND e.meta_value = %s
        ";

        $sqlStr = $wpdb->prepare($sql, self::STATUS_FIELD_ID, self::STATUS_MATCH, self::EMAIL_FIELD_ID, $email);

        echo $sqlStr; exit();

        $item_ids = $wpdb->get_col( $sqlStr );

        if (empty($item_ids)) {
            return [
                'ok'        => true,
                'action'    => 'no_match',
                'email'     => $email,
                'updated'   => 0,
                'item_ids'  => [],
                'status_was'=> self::STATUS_MATCH,
                'status_to' => self::STATUS_SET,
            ];
        }

        // Update status for each matching item_id
        $updated = 0;

        foreach ($item_ids as $item_id) {
            $item_id = (int) $item_id;

            // Update existing status meta row (field 7)
            $res = $wpdb->update(
                $table,
                ['meta_value' => self::STATUS_SET],
                ['item_id' => $item_id, 'field_id' => self::STATUS_FIELD_ID],
                ['%s'],
                ['%d', '%d']
            );

            if ($res !== false) {
                // $res is number of rows updated; can be 0 if already same value
                $updated += (int) $res;
            } else {
                Frm_Freshdesk_Webhook_Logger::log([
                    'type'    => 'processor_error',
                    'email'   => $email,
                    'item_id' => $item_id,
                    'db_error'=> $wpdb->last_error,
                ]);
            }
        }

        return [
            'ok'        => true,
            'action'    => 'updated',
            'email'     => $email,
            'updated'   => $updated,
            'item_ids'  => array_map('intval', $item_ids),
            'status_was'=> self::STATUS_MATCH,
            'status_to' => self::STATUS_SET,
        ];
    }

    private static function extract_email(array $payload): string {
        $email = '';

        if (isset($payload['freshdesk_webhook']) && is_array($payload['freshdesk_webhook'])) {
            $email = (string) ($payload['freshdesk_webhook']['ticket_contact_email'] ?? '');
        }

        $email = trim($email);

        // Light validation (don’t hard-fail if Freshdesk sends weird formatting)
        if ($email !== '' && function_exists('is_email') && !is_email($email)) {
            // still return raw, but log warning
            Frm_Freshdesk_Webhook_Logger::log([
                'type'  => 'processor_warning',
                'issue' => 'invalid_email_format',
                'value' => $email,
            ]);
        }

        return $email;
    }
}

Frm_Freshdesk_Webhook_Logger::init();
