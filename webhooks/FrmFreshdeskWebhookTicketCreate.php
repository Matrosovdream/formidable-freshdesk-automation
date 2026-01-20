<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskWebhookTicketCreate {

    private const NS    = 'frm';
    private const ROUTE = 'freshdesk/ticket/created';

    /** @var FrmFreshdeskLogger */
    private $logger;
    private $processor;

    public function __construct(
        $logger, $processor=null
        ) {
        // Logger    
        $this->logger = $logger;

        // Processor
        if( $processor != null ) {
            $this->processor = $processor;
        }
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

            // Process webhook results
            $processRes = $this->processor->process($json);

            // Log received webhook
            $this->logger->log('ticket_create', $json);

        } else {

            // Log invalid JSON error
            $this->logger->log('errors', [
                'type'        => 'invalid_json',
                'received_at' => current_time('mysql'),
                'raw'         => $raw,
            ]);
        }

        return new \WP_REST_Response(['ok' => true, 'result' => $processRes], 200);
    }
}


// Initialize webhook
$webhook = new FrmFreshdeskWebhookTicketCreate( 
    new FrmFreshdeskLogger(),
    new FrmFreshdeskTicketCreateProcessor() 
);
$webhook->init();
