<?php

namespace CarersResource\CiviEvents;

use DateInterval;
use DateTime;

class Adapter
{
    private static $plugin;
    private $ids;

    public function __construct()
    {
    }

    public static function register($plugin)
    {
        if ($plugin::$adapter) {
            return $plugin;
        }
        $plugin::$adapter = new self();
        self::$plugin = $plugin;
        return $plugin;
    }

    public function get_events()
    {
        return $this->process_events();
    }

    public function sync()
    {
        $this->save_events($this->process_events());
    }

    private function process_events()
    {
        $response = $this->get_civicrm_events();

        $decoded = json_decode(str_replace('loc_block_id.address_id.', '', $response), true);

        $events = $decoded['values'];

        $this->ids = $this->get_ids();

        return array_values((array_filter($events, [$this, 'event_filter'])));
    }

    private function save_events($events)
    {
        $new_ids = [];
        foreach ($events as $event) {
            $new_ids[] = $this->save_event($event);
        }
        $current_ids = $this->ids;

        foreach ($current_ids as $current_id) {
            if (!\key_exists($current_id, $new_ids)) {
                echo (\wp_trash_post($current_id['wp_id']));
            }
        }
        \update_option('civicrm_event_ids', $new_ids);
        \delete_transient('civi_events');
    }


    public function save_single_event($event)
    {
        $id = $this->save_event($event);

        $this->ids[$event['id']] = $id;
        \update_option('civicrm_event_ids', $this->ids);
    }

    private function get_civicrm_events()
    {

        /*         if (self::$plugin->data['use_cache'] && (false !== ($cev = \get_transient('civicrm_events')))) {
            return $cev;
        }; */
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

        $tpl = self::$plugin->m->loadTemplate('api_call');
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
        if (!self::$plugin->data['use_cache']) {
            \delete_transient('civicrm_events');
        }
        if (self::$plugin->data['use_cache']) {
            \set_transient('civicrm_events', $response, 300);
        }
        return $response;
    }

    // Function event_filter currently runs the hash check on each event
    // but the event list is not modified. Other filters can be added here.
    private function event_filter($event)
    {
        return ($this->check_hash($event));
    }

    // check_hash_no_match returns true if the md2 hash of a given event
    // is different to the one we have stored. If different it means the
    // CiviCRM record has changed. 
    private function check_hash($event)
    {
        if (\hash("md2", serialize($event)) !== $this->ids[$event['id']]['md2']) {
            $this->ids[$event['id']]['md2'] = 'dirty';
        };

        return true;
    }

    private function get_ids()
    {
        return \get_option('civicrm_event_ids');
    }

    private function save_event($e)
    {

        $post = [
            'post_id' => false,
            'post_type' => self::$plugin::$post_type,
            'post_title' => $e['title'],
            'post_content' => \wp_strip_all_tags($e['description']),
            'post_status' => 'publish'
        ];

        //If the event ID is already known, and the event hasn't been modified, there's nothing to do.
        if (\array_key_exists($e['id'], $this->ids)) {
            if ($this->ids[$e['id']]['md2'] !== 'dirty') {
                return;
            }
            // If the event has been modified we update
            $post['ID'] = $e['id']['wp_id'];
        }

        $wp_post_id = \wp_insert_post($post);
        self::try_update_meta($wp_post_id, 'event_from', $e, 'start_date', true);
        self::try_update_meta($wp_post_id, 'event_to', $e, 'end_date', true);
        self::try_update_meta($wp_post_id, 'event_loc_street', $e, 'street_address');
        self::try_update_meta($wp_post_id, 'event_loc_extra', $e, 'supplemental_address_1');
        self::try_update_meta($wp_post_id, 'event_loc_town', $e, 'city');
        self::try_update_meta($wp_post_id, 'event_loc_postcode', $e, 'postal_code');
        \update_post_meta($wp_post_id, 'event_civicrm_id', $e['id']);
        \update_post_meta($wp_post_id, 'event_multiday', self::is_multiday($e));


        $id = [];
        $id['wp_id'] = $wp_post_id;
        $id['md2'] = \hash("md2", serialize($e));

        return $id;
    }

    private static function is_multiday($e)
    {
        if ((date('Ymd', $e['start_date'])) !== date('Ymd', $e['end_date'])) {
            return true;
        }
        return false;
    }

    private static function try_update_meta($id, $meta_key, $a, $key, $datetime = false)
    {
        if (array_key_exists($key, $a)) {
            if ($datetime) {
                \update_post_meta($id, $meta_key, strtotime($a[$key]));
            } else {
                \update_post_meta($id, $meta_key, $a[$key]);
            }
        };
    }
}
