<?php

namespace CarersResource\CiviEvents;

use Mustache_Engine;
use Dotenv\Dotenv;

class Plugin
{
    public $m; // mustache engine
    public $dotenv; // get secrets from .env
    public $data;
    public static $adapter; // does all the work
    public static $admin; // back end
    public static $events;
    public static $plugin;
    public static $post_type = 'cr-civi-events';

    public function __construct()
    {
    }

    public static function register($fp)
    {
        self::$plugin = new self();
        \add_option('civicrm_event_ids', []);
        \add_option('civicrm_last_sync');
        \add_option('civicrm_events_total');
        \add_option('civicrm_events_saved');
        \add_option('civicrm_events_trashed');
        \add_option('civicrm_events_yp_type_ids', ['2']);
        self::$plugin->add_adapter()->add_dotenv()->add_mustache()->add_admin();

        self::$plugin->data = [
            'title' => 'CiviCRM Events Adapter',
            'menu_title' => 'CiviCRM Events',
            'menu_slug' => 'civi-events',
            'user_key' =>  isset($_ENV['CIVICRM_USER_KEY']) ? $_ENV['CIVICRM_USER_KEY'] : \getenv('CIVICRM_USER_KEY'),
            'site_key' => isset($_ENV['CIVICRM_SITE_KEY']) ? $_ENV['CIVICRM_SITE_KEY'] : \getenv('CIVICRM_SITE_KEY'),
            'civi_user' => isset($_ENV['CIVICRM_USER']) ? $_ENV['CIVICRM_USER'] : \getenv('CIVICRM_USER'),
            'civi_debug' => isset($_ENV['CIVICRM_DEBUG']) ? $_ENV['CIVICRM_DEBUG'] : true,
        ];

        \register_activation_hook($fp, [\get_called_class(), 'activate']);
        \register_deactivation_hook($fp, [\get_called_class(), 'deactivate']);
        \add_action('civicrm_sync', [\get_called_class(), 'scheduled_sync']);
        \add_action('init', [\get_called_class(), 'add_custom_post_type']);
        \add_action('init', [\get_called_class(), 'add_meta']);
    }

    public static function activate()
    {
        if (!wp_next_scheduled('civicrm_sync')) {
            wp_schedule_event(time(), 'hourly', 'civicrm_sync');
        }
    }

    public static function deactivate()
    {
        \delete_option('civicrm_last_sync');
        \delete_option('civicrm_event_ids');
        \delete_option('civicrm_events_total');
        \delete_option('civicrm_events_updated');
        \delete_option('civicrm_events_trashed');
        $timestamp = wp_next_scheduled('civicrm_sync');
        wp_unschedule_event($timestamp, 'civicrm_sync');
    }

    public static function scheduled_sync()
    {
        self::$plugin::$adapter->sync();
        \update_option('civicrm_last_sync', \current_time('Y-m-d H:i:s') . ' scheduled');
    }

    private function add_adapter()
    {
        Adapter::register($this);
        return $this;
    }

    private function add_dotenv()
    {
        $this->dotenv = Dotenv::createImmutable(ABSPATH . '../');
        $this->dotenv->safeLoad();
        $this->dotenv->ifPresent('USE_CACHE')->isBoolean();
        $this->dotenv->ifPresent('USE_AUTH')->isBoolean();
        $this->dotenv->ifPresent('CIVICRM_DEBUG')->isBoolean();
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

    public static function add_custom_post_type()
    {
        \register_post_type(
            self::$post_type,
            [
                'label' => 'Groups and Events',
                'public' => true,
                'exclude_from_search' => false,
                'publicly_queryable' => true,
                'show_in_rest' => true,
                'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
                'taxonomies' => ['tags'],
                'rewrite' => ['slug' => 'groups-and-events'],
                'has_archive' => 'groups-and-events',
            ]
        );
    }

    public static function add_meta()
    {
        $meta = [
            ['key' => 'event_from', 'type' => 'integer'],
            ['key' => 'event_to', 'type' => 'integer'],
            ['key' => 'event_loc_street', 'type' => 'string'],
            ['key' => 'event_loc_extra', 'type' => 'string'],
            ['key' => 'event_loc_town', 'type' => 'string'],
            ['key' => 'event_loc_postcode', 'type' => 'string'],
            ['key' => 'latitude', 'type' => 'string'],
            ['key' => 'longitude', 'type' => 'string'],
            ['key' => 'show_map', 'type' => 'boolean'],
            ['key' => 'event_civicrm_id', 'type' => 'integer',],
            ['key' => 'event_multiday', 'type' => 'boolean']
        ];
        $reg = function ($item) {
            register_post_meta(self::$post_type, $item['key'], [
                'type' => $item['type'],
                'single' => true,
                'show_in_rest' => true
            ]);
        };
        array_map($reg, $meta);
    }
}
