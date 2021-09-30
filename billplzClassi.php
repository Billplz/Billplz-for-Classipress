<?php

/**
 * Plugin Name: Billplz for ClassiPress
 * Description:  Billplz Payment Gateway
 * Author: Wanzul Hosting Enterprise
 * Version: 3.00
 * License: GPLv3
 * Text Domain: classibillplz
 * Domain Path: /languages/
 */
require_once( __DIR__ . '/includes/billplz.php' );
require_once( __DIR__ . '/includes/APP_Billplz_IPN_Listener.php');

add_action('init', 'billplz_plugin_classi', 0);

function billplz_plugin_classi() {
    if (class_exists('APP_Gateway')) {
        include __DIR__ . '/includes/class_Billplz_Gateway.php';
        /**
         * To detect callback signal
         */
        $listener = new APP_Billplz_IPN_Listener();
    }
}
