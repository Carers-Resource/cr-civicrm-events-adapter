<?php

namespace CarersResource\CiviEvents;

use DateInterval;
use DateTime;
use Mustache_Engine;
use Dotenv\Dotenv;
use WP_Error;

class Plugin
{
    public $m; // mustache engine
    public $dotenv; // get secrets from .env
    public $data;
    public $adapter; // does all the work
    public $admin; // back end
    public $events;

    public function __construct()
    {
    }

    public static function register()
    {
        $plugin = new self();
        add_option('civicrm_event_ids', []);
        $plugin->add_adapter()->add_dotenv()->add_mustache()->add_admin();

        $plugin->data = [
            'title' => 'CiviCRM Events Adapter',
            'menu_title' => 'CiviCRM Events',
            'menu_slug' => 'civi_events',
            'user_key' => $_ENV['CIVICRM_USER_KEY'],
            'site_key' => $_ENV['CIVICRM_SITE_KEY'],
            'civi_user' => $_ENV['CIVICRM_USER'],
        ];
        # $plugin->data['response'] = $plugin->adapter->get_civicrm_events();
    }


    private function add_adapter()
    {
        Adapter::register($this);
        return $this;
    }

    private function add_dotenv()
    {
        $this->dotenv = Dotenv::createImmutable(\plugin_dir_path(__DIR__));
        $this->dotenv->load();
        return $this;
    }

    private function add_mustache()
    {
        $this->m = new Mustache_Engine([
            'loader' => new \Mustache_Loader_FilesystemLoader((\plugin_dir_path(__DIR__)) . 'views'),
        ]);
        return $this;
    }

    private function add_admin()
    {
        Admin::register($this);
        return $this;
    }
}

    
# f0ce4bf6056eb531a5b4ad55242da548

# 7ff10492c8cc1433a8d8e5352b47cf48

# f1dd368f914ac71e7fc9baa86b243036