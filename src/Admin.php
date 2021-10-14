<?php

namespace CarersResource\CiviEvents;

use DateInterval;
use DateTime;
use Mustache_Engine;
use Dotenv\Dotenv;

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
        //echo $tpl->render($this->data);

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
        $decoded  = \json_decode($events_json, true);

        $event_0_data = $decoded['values'][0];
        $event_0_data['street'] = $event_0_data['loc_block_id.address_id.street_address'];
        $event_0_data['town'] = $event_0_data['loc_block_id.address_id.city'];
        $event_0_data['postcode'] = $event_0_data['loc_block_id.address_id.postal_code'];

        $tpl = $this->m->loadTemplate('event');

        echo $tpl->render($event_0_data);

        echo ("<br/><br/>");

        print_r($event_0_data);
    }
}
