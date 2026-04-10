<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskWebhookTicketCreate {

    private const NS        = 'frm';
    private const ROUTE     = 'freshdesk/ticket/created';
    private const CRON_HOOK = 'frm_freshdesk_ticket_create_process_event';

    /** @var FrmFreshdeskLogger */
    private $logger;

    /** @var FrmFreshdeskTicketCreateProcessor|null */
    private $processor;

    public function __construct( $logger, $processor = null ) {
        $this->logger    = $logger;
        $this->processor = $processor;
    }

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( self::CRON_HOOK, [ $this, 'handleSingleEvent' ], 10, 1 );
    }

    public function register_routes(): void {
        register_rest_route(
            self::NS,
            self::ROUTE,
            [
                'methods'             => [ 'POST' ],
                'callback'            => [ $this, 'handle' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * REST handler — log inbound webhook and queue async processing.
     */
    public function handle( \WP_REST_Request $request ): \WP_REST_Response {

        $raw = (string) $request->get_body();

        $json = null;
        if ( $raw !== '' ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $json = $decoded;
            }
        }

        if ( ! is_array( $json ) ) {
            $this->logger->log( 'errors', [
                'type'        => 'invalid_json',
                'received_at' => current_time( 'mysql' ),
                'raw'         => $raw,
            ] );

            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'invalid_json' ], 200 );
        }

        // Log received webhook
        $this->logger->log( 'ticket_create', $json );

        // Queue for async processing via WP-Cron
        $queued = $this->queueEvent( $json );

        return new \WP_REST_Response( [
            'ok'     => true,
            'queued' => $queued,
        ], 200 );
    }

    /**
     * Schedule a single WP-Cron event to process this webhook payload.
     * Deduplicates identical payloads.
     */
    protected function queueEvent( array $payload ): bool {

        // Avoid scheduling identical payloads twice
        if ( wp_next_scheduled( self::CRON_HOOK, [ $payload ] ) ) {
            $this->logger->log( 'ticket_create_queued', [
                'status'  => 'duplicate',
                'payload' => $payload,
            ] );
            return false;
        }

        $scheduled = wp_schedule_single_event( time(), self::CRON_HOOK, [ $payload ] );

        $this->logger->log( 'ticket_create_queued', [
            'status'  => $scheduled ? 'queued' : 'failed',
            'payload' => $payload,
        ] );

        return (bool) $scheduled;
    }

    /**
     * WP-Cron handler — runs the processor for a queued payload.
     */
    public function handleSingleEvent( $payload ): void {

        if ( ! is_array( $payload ) ) {
            return;
        }

        $processor = $this->processor ?: new FrmFreshdeskTicketCreateProcessor();
        $processor->process( $payload );
    }
}


// Initialize webhook
$webhook = new FrmFreshdeskWebhookTicketCreate(
    new FrmFreshdeskLogger(),
    new FrmFreshdeskTicketCreateProcessor()
);
$webhook->init();
