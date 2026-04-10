<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskWebhookTicketCreate {

    private const NS    = 'frm';
    private const ROUTE = 'freshdesk/ticket/created';

    /** @var FrmFreshdeskLogger */
    private $logger;

    public function __construct($logger) {
        $this->logger = $logger;
    }

    public function init(): void {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes(): void {

        $base = self::NS;
        $route = self::ROUTE;

        register_rest_route(
            $base,
            $route,
            [
                'methods'             => ['POST'],
                'callback'            => [ $this, 'handle' ],
                'permission_callback' => '__return_true',
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

        // Always log inbound data
        if (is_array($json)) {
            $this->logger->log('ticket_create', $json);
        } else {
            $this->logger->log('errors', [
                'type'        => 'invalid_json',
                'received_at' => current_time('mysql'),
                'raw'         => $raw,
            ]);
        }

        if (is_array($json)) {
            //$result = Frm_Freshdesk_Webhook_processer::process($json);

            /*
            $this->logger->log('processor', [
                'received_at' => current_time('mysql'),
                'result'      => $result,
            ]);
            */
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }
}


// Initialize webhook
$webhook = new FrmFreshdeskWebhookTicketCreate( new FrmFreshdeskLogger() );
$webhook->init();
