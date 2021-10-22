<?php

namespace CarersResource\CiviEvents;

use DateInterval;
use DateTime;
use Mustache_Engine;
use Dotenv\Dotenv;
use WP_Error;

class Adapter
{
    private $plugin;
    private $response;
    private $ids;

    public function __construct()
    {
    }

    public static function register($plugin)
    {
        $plugin->adapter = new self();
        $plugin->adapter->plugin = $plugin;
        return $plugin;
    }

    public function get_civicrm_events()
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

        $tpl = $this->plugin->m->loadTemplate('api_call');
        $json = $tpl->render($data);

        $query = '?entity=Event&action=get&json=' . urlencode($json) . '&api_key=' . $user_key . '&key=' . $site_key;

        $url = $site . $endpoint . $query;

        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($ch, \CURLOPT_USERPWD, $auth);
        \curl_setopt($ch, \CURLOPT_URL, $url);

        $response = \curl_exec($ch);

        if (\curl_errno($ch)) {
            throw new \Exception(\curl_error($ch));
        }
        return $response;
    }

    public function process_events()
    {
        $response = $this->get_civicrm_events();
        $this->ids = \get_option("civicrm_event_ids");

        $decoded = json_decode(str_replace('loc_block_id.address_id.', '', $response), true);

        $events = $decoded['values'];

        $this->plugin->events = array_values((array_filter($events, [$this, 'event_filter'])));
    }


    public function event_filter($e)
    {
        return ($this->check_id_unknown($e) || $this->check_hash_no_match($e));
    }

    // check_event_in_past checks to see if an event falls in the past
    // and so can be deleted.
    public function check_event_in_past($e)
    {
        $today = date('Y-m-d');
        $event_end = $e['end_date'];
        return ($event_end < $today);
    }

    // check_id_unknown checks to see if the CiviCRM event ID is new to us
    public function check_id_unknown($e)
    {
        return !(\array_key_exists($e['id'], $this->ids));
    }

    // check_hash_no_match returns true if the md2 hash of a given event
    // is different to the one we have stored. If different it means the
    // CiviCRM record has changed. 
    public function check_hash_no_match($e)
    {
        if (\hash("md2", serialize($e)) !== $this->ids[$e['id']]['md2']) {
            $this->ids[$e['id']]['md2'] = 'dirty';
        };
    }

    public function save_first_event()
    {
        $this->save_event($this->plugin->events[0]);
        \maybe_serialize($this->ids);
        \update_option('civicrm_event_ids', $this->ids);
    }

    public function save_event($e)
    {
        $post = [
            'post_title' => $e['title'],
            'post_content' => $e['description'],
        ];

        if (\array_key_exists($e['id'], $this->ids)) {
            if ($this->ids[$e['id']]['md2'] === 'dirty') {
                $post['post_id'] = $e['id']['wp_id'];
            };
        };

        $wp_post_id = \wp_insert_post($post);
        self::try_update_meta($wp_post_id, 'event_from', $e, 'start_date');
        self::try_update_meta($wp_post_id, 'event_to', $e, 'end_date');
        self::try_update_meta($wp_post_id, 'event_loc_street', $e, 'street_address');
        self::try_update_meta($wp_post_id, 'event_loc_extra', $e, 'supplemental_address_1');
        self::try_update_meta($wp_post_id, 'event_loc_town', $e, 'city');
        self::try_update_meta($wp_post_id, 'event_loc_postcode', $e, 'postal_code');
        $this->ids[$e['id']] = [];
        $this->ids[$e['id']]['wp_id'] = $wp_post_id;
        $this->ids[$e['id']]['md2'] = \hash("md2", serialize($e));
    }

    public static function try_update_meta($id, $meta_key, $a, $key)
    {
        if (array_key_exists($key, $a)) {
            \update_post_meta($id, $meta_key, $a[$key]);
        };
    }
}
