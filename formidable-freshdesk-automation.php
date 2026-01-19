<?php
/*
Plugin Name: Formidable Freshdesk Automation
Description: 
Version: 1.0
Plugin URI: 
Author URI: 
Author: Stanislav Matrosov
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables
define('FFDA_PLUGIN_DIR', __DIR__);
define('FFDA_PLUGIN_URL', plugin_dir_url(__FILE__));

define('FFDA_LOG_FOLDER', FFDA_PLUGIN_DIR . '/logs');


// Initialize core
require_once 'classes/FrmFreshdeskInit.php';


//require_once 'webhook.php';




add_action('init', 'init123');
function init123() {
    
    if( isset( $_GET['sync'] ) ) {
        
    }


}

