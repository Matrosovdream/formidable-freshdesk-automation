<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskWebhookTicketCreate {

    private const NS    = 'frm';
    private const ROUTE = 'freshdesk/ticket/created';
    private const LOG_FILENAME = 'freshdesk_log.log';

    public function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public function register_routes(): void {
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

    public function handle(\WP_REST_Request $request): \WP_REST_Response {

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

            /*
            self::log([
                'type'        => 'processor_result',
                'received_at' => current_time('mysql'),
                'result'      => $result,
            ]);
            */
        } else {
            self::log([
                'type'        => 'processor_skipped',
                'received_at' => current_time('mysql'),
                'reason'      => 'invalid_json',
            ]);
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }

    public function log(array $data): void {
        $file = self::get_log_file_path();

        $line = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = '{"error":"json_encode_failed","received_at":"' . esc_js(current_time('mysql')) . '"}';
        }
        $line .= PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function get_log_file_path(): string {
        // SAME folder as this plugin file
        return plugin_dir_path(__FILE__) . self::LOG_FILENAME;
    }

    private function get_client_ip(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
            return (string) ($parts[0] ?? '');
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function safe_headers(array $headers): array {
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

// Initialize webhook
$webhook = new FrmFreshdeskWebhookTicketCreate();
$webhook->init();
