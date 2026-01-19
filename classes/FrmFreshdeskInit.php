<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmFreshdeskInit {

    public function __construct() {

        // Loggers
        $this->include_loggers();

        // Webhooks
        $this->include_webhooks();

        // API class
        /*
        $this->include_api();

        // Shortcodes
        $this->include_shortcodes();

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Helpers
        $this->include_helpers();

        // CRON
        $this->include_cron();

        // Hooks
        $this->include_hooks();

        // Actions CRON
        $this->include_actions_cron();

        // Filters
        $this->include_filters();

        // Ajax actions
        $this->include_ajax();

        // Formidable Addons
        $this->include_frm_addons();

        // Routes
        $this->include_routes();
        */

    }

    private function include_loggers(): void {

        // Logger
        require_once FFDA_PLUGIN_DIR . '/classes/loggers/FrmFreshdeskLogger.php';
        
    }

    private function include_webhooks(): void {

        // Ticket Created Webhook
        require_once FFDA_PLUGIN_DIR . '/webhooks/FrmFreshdeskWebhookTicketCreate.php';
        
    }

}

new FrmFreshdeskInit();