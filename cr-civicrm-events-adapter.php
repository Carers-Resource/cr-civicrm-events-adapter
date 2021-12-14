<?php

/**
 * Plugin Name: Carers' Resource CiviCRM Events Adapter
 * Plugin URI:  https://www.carersresource.org
 * Description: Pull in Events from a CiviCRM site using its API and convert them to WordPress posts.
 * Version:     dev
 * Author:      Carers' Resource (Gavin Massingham)
 * Author URI:  https://www.carersresource.org
 * Text Domain: cr-civicrm-events-adapter
 * Domain Path: /lang
 */

namespace CarersResource\CiviEvents;

/**
 * Abort if we're being called directly.
 */
if (!defined('ABSPATH')) {
    exit;
}


if (!function_exists('plugin_dir_path')) {
    require_once(dirname(__FILE__) . '/../../../wp-admin/includes/file.php');
}

require_once(\plugin_dir_path(__FILE__) . 'vendor/autoload.php');

Plugin::register(__FILE__);
