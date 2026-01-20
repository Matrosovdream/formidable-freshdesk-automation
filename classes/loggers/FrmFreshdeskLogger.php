<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskLogger {

    /**
     * Absolute base directory for logs
     * Expected to end with trailing slash
     *
     * Example:
     * define('FFDA_LOG_FOLDER', WP_CONTENT_DIR . '/freshdesk-logs/');
     */
    private string $base_dir;

    /**
     * Short log type → filename
     */
    private const LOG_MAP = [
        'ticket_create'            => 'ticket_create_webhook.log',
        'ticket_create_processed' => 'ticket_created_processed.log',
        'processor'                => 'processor.log',
        'errors'                   => 'errors.log',
        'raw'                      => 'raw_payload.log',
    ];

    public function __construct(?string $base_dir = null) {

        $this->base_dir = $base_dir ?: FFDA_LOG_FOLDER;

        // Normalize trailing slash
        if (substr($this->base_dir, -1) !== DIRECTORY_SEPARATOR) {
            $this->base_dir .= DIRECTORY_SEPARATOR;
        }
    }

    /**
     * Write a log entry
     *
     * @param string $log_type Short name from LOG_MAP
     * @param array  $data
     */
    public function log(string $log_type, array $data): void {

        $file = $this->resolve_log_file($log_type);

        // Timestamp like: [2026-01-16 09:13:04-08:00]
        $timestamp = '[' . wp_date('Y-m-d H:i:sP') . '] ';

        $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $json = wp_json_encode([
                'error'       => 'json_encode_failed',
                'received_at' => wp_date('c'),
            ]);
        }

        $line = $timestamp . $json . PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Map log type → full file path
     */
    private function resolve_log_file(string $log_type): string {

        if (!isset(self::LOG_MAP[$log_type])) {
            return $this->base_dir . 'unknown.log';
        }

        return $this->base_dir . self::LOG_MAP[$log_type];
    }
}
