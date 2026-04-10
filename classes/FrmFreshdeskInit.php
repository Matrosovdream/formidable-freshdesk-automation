<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmFreshdeskInit {

    public function __construct() {

        // Helpers
        $this->include_helpers();

        // Webhook processors
        $this->include_webhook_processors();

        // Loggers
        $this->include_loggers();

        // Webhooks
        $this->include_webhooks();

        // Admin settings
        $this->include_admin_settings();

        

        // API class
        /*
        $this->include_api();

        // Shortcodes
        $this->include_shortcodes();

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

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
        //if( isset( $_GET['ttt'] ) ) {
            require_once FFDA_PLUGIN_DIR . '/webhooks/FrmFreshdeskWebhookCreate.php';
        //}
        
    }

    private function include_webhook_processors(): void {

        // Ticket Created Processor
        require_once FFDA_PLUGIN_DIR . '/classes/webhook_processors/FrmFreshdeskTicketCreateProcessor.php';
        
    }

    private function include_admin_settings(): void {

        // Admin Settings
        require_once FFDA_PLUGIN_DIR . '/classes/admin/FrmFreshdeskAdminSettings.php';
        
    }

    private function include_helpers(): void {

        // Options Helper
        //require_once FFDA_PLUGIN_DIR . '/classes/helpers/FrmFreshdeskOptionsHelper.php';

        // Entry Helper
        require_once FFDA_PLUGIN_DIR . '/classes/helpers/FrmFreshdeskEntryHelper.php';
        
    }

}

new FrmFreshdeskInit();