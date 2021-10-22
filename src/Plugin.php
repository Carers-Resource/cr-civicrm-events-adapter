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
    public static $post_type = 'cr_civi_events';

    public function __construct()
    {
    }

    public static function register()
    {
        self::$plugin = new self();
        add_option('civicrm_event_ids', []);
        self::$plugin->add_adapter()->add_dotenv()->add_mustache()->add_admin();

        self::$plugin->data = [
            'title' => 'CiviCRM Events Adapter',
            'menu_title' => 'CiviCRM Events',
            'menu_slug' => 'civi_events',
            'user_key' => $_ENV['CIVICRM_USER_KEY'],
            'site_key' => $_ENV['CIVICRM_SITE_KEY'],
            'civi_user' => $_ENV['CIVICRM_USER'],
        ];

        \add_action('init', [self::$plugin, 'add_custom_post_type']);
        \add_action('init', [self::$plugin, 'add_meta']);
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

    public function add_custom_post_type()
    {
        \register_post_type(
            self::$post_type,
            [
                'label' => 'CiviCRM Groups and Events',
                'public' => true,
                'exclude_from_search' => false,
                'publicly_queryable' => true,
                'show_in_rest' => true,
                'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
                'taxonomies' => ['categories', 'tags'],
                'has_archive' => 'cr-groups-events',
                'rewrite' => [
                    'slug' => 'cr-groups-events'
                ]
            ]
        );
    }

    public function add_meta()
    {
        $meta = [
            ['key' => 'event_from', 'type' => 'string',],
            ['key' => 'event_to', 'type' => 'string',],
            ['key' => 'event_loc_street', 'type' => 'string',],
            ['key' => 'event_loc_extra', 'type' => 'string',],
            ['key' => 'event_loc_town', 'type' => 'string',],
            ['key' => 'event_loc_postcode', 'type' => 'string',],
            ['key' => 'event_civicrm_id', 'type' => 'integer',],
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
