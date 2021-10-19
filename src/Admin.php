<?php

namespace CarersResource\CiviEvents;

use DateInterval;
use DateTime;
use Mustache_Engine;
use Dotenv\Dotenv;
use WP_Error;

class Admin
{
    private $m;
    protected $dotenv;
    private $data;

    public function __construct()
    {
        add_option('civicrm_event_ids', []);
    }

    public static function register()
    {
        $plugin = new self();
        $plugin->dotenv = Dotenv::createImmutable(\plugin_dir_path(__DIR__));
        $plugin->dotenv->load();
        $plugin->m = new Mustache_Engine([
            'loader' => new \Mustache_Loader_FilesystemLoader((\plugin_dir_path(__DIR__)) . 'views'),
        ]);
        $plugin->data = [
            'title' => 'CiviCRM Events Adapter',
            'menu_title' => 'CiviCRM Events',
            'menu_slug' => 'civi_events',
            'user_key' => $_ENV['CIVICRM_USER_KEY'],
            'site_key' => $_ENV['CIVICRM_SITE_KEY'],
            'civi_user' => $_ENV['CIVICRM_USER']
        ];
        $plugin->data['response'] = $plugin->get_civicrm_events();
        add_action('admin_menu', array($plugin, 'admin_menu'));
    }

    public function admin_menu()
    {
        add_menu_page(
            $this->data['title'],
            $this->data['menu_title'],
            'edit_posts',
            $this->data['menu_slug'],
            [$this, 'civi_events_admin_page']
        );
    }

    public function civi_events_admin_page()
    {
        $tpl = $this->m->loadTemplate('admin'); // loads __DIR__.'/views/admin.mustache';

        $events_json = $this->data['response'];
        $this->save_an_event($events_json);
    }

    private function get_civicrm_events()
    {
        $ch = \curl_init();

        $auth = $_ENV['DEV_USER'] . ':' . $_ENV['DEV_PASS'];
        $user_key = $_ENV['CIVICRM_USER_KEY'];
        $site_key = $_ENV['CIVICRM_SITE_KEY'];

        $site = $_ENV['CIVICRM_URL'];
        $endpoint = '/sites/all/modules/civicrm/extern/rest.php';
        $data = [];
        $f = new DateTime();
        $data['from'] = $f->format("Y-m-d");
        $t = $f->add(new DateInterval("P3M"));
        $data['to'] = $t->format("Y-m-d");
        $data['fields'] = 'id,title,summary,description,start_date,end_date,loc_block_id.id,loc_block_id.address_id.street_address,loc_block_id.address_id.supplemental_address_1,loc_block_id.address_id.supplemental_address_2,loc_block_id.address_id.supplemental_address_3,loc_block_id.address_id.city,loc_block_id.address_id.postal_code';


        $tpl = $this->m->loadTemplate('api_call');
        $json = $tpl->render($data);

        $query = '?entity=Event&action=get&json=' . urlencode($json) . '&api_key=' . $user_key . '&key=' . $site_key;

        $url = $site . $endpoint . $query;

        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($ch, \CURLOPT_USERPWD, $auth);
        \curl_setopt($ch, \CURLOPT_URL, $url);

        $response = \curl_exec($ch);

        if (\curl_errno($ch)) {
            //If an error occured, throw an Exception.
            throw new \Exception(\curl_error($ch));
        }
        return $response;
    }

    private function save_an_event($events_json)
    {

        //update_option('civicrm_event_ids', []);

        $decoded  = \json_decode($events_json, true);
        $e0 = $decoded['values'][0];
        $e0['street'] = $e0['loc_block_id.address_id.street_address'];
        $e0['town'] = $e0['loc_block_id.address_id.city'];
        $e0['postcode'] = $e0['loc_block_id.address_id.postal_code'];

        $tpl = $this->m->loadTemplate('event');

        echo $tpl->render($e0);

        echo ("<br/><br/>");

        print_r(\serialize($e0));

        $md2 = \hash("md2", \serialize(($e0)));

        echo ("<br/><br/>");

        \print_r($md2);

        $known_ids = \get_option('civicrm_event_ids');
        $modified = false;

        if (\array_key_exists($e0['id'], $known_ids)) {
            echo '<p>Already added</p>';
            if ($md2 == $known_ids[$e0['id']]['md2']) {
                return;
            }
            echo ("<br/><br/>");
            echo ("hash not equal: post modified");
            echo ("<br><br>");
            $modified = true;
        }

        $post_to_add = [
            'post_title' => $e0['title'],
            'post_content' => $e0['description'],
        ];

        if ($modified) {
            $post_to_add['post_id'] = $known_ids[$e0['id']]['wp_id'];
        }

        $wp_post_id = new WP_Error($code = 'dummy', 'just a placeholder');

        $wp_post_id = wp_insert_post($post_to_add, true);


        if (!\is_wp_error($wp_post_id)) {
            \update_post_meta($wp_post_id, 'event_from', $e0['start_date']);
            \update_post_meta($wp_post_id, 'event_to', $e0['end_date']);
            $known_ids[$e0['id']] = [];
            $known_ids[$e0['id']]['wp_id'] = $wp_post_id;
            $known_ids[$e0['id']]['md2'] = $md2;
            \maybe_serialize($known_ids);
            \update_option('civicrm_event_ids', $known_ids);
        }

        print_r($known_ids);

        return;
    }
}

# f0ce4bf6056eb531a5b4ad55242da548

# 7ff10492c8cc1433a8d8e5352b47cf48

# f1dd368f914ac71e7fc9baa86b243036