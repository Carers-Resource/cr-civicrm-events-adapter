<?php

/**
 * Plugin Name: Carers' Resource CiviCRM Events Adapter
 * Plugin URI:  https://www.carersresource.org
 * Description: Pull in Events from CiviCRM to populate the events calendar
 * Version:     0.0.1
 * Author:      Carers' Resource (Gavin Massingham)
 * Author URI:  https://www.carersresource.org
 * Text Domain: cr-civicrm-events-adapter
 * Domain Path: /lang
 */

namespace carersresource\Civi_Events;

/**
 * Abort if we're being called directly.
 */
if (!defined('ABSPATH')) {
    exit;
}


if (!function_exists('get_home_path')) {
    require_once(dirname(__FILE__) . '/../../../wp-admin/includes/file.php');
}

$install_dir = \get_home_path();
require_once($install_dir . 'vendor/autoload.php');
