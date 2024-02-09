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

        $decoded = json_decode(str_replace(['loc_block_id.address_id.', 'Copy of '], '', $response), true);

        $events = $decoded['values'];

        $this->ids = $this->get_ids();

        foreach ($events as $key => $event) {
            $events[$key] = $this->event_filter($event, self::$plugin->data['civi_debug']);
        }

        return array_values($events);
    }

    private function save_events($events)
    {
        $current_ids = $this->ids;
        $new_ids = [];
        $saved = 0;
        $trashed = 0;
        foreach ($events as $event) {
            if ((\array_key_exists($event['id'], $current_ids)) && ($current_ids[$event['id']]['md2'] !== 'dirty')) {
                $new_ids[$event['id']] = $current_ids[$event['id']];
                continue;
            }
            $new_ids[$event['id']] = $this->save_event($event);
            $saved++;
        }


        foreach ($current_ids as $i => $current_id) {
            if (!\array_key_exists($i, $new_ids)) {
                \wp_delete_post($current_id['wp_id'], true);
                $trashed++;
            }
        }
        \update_option('civicrm_event_ids', $new_ids);
        \delete_transient('civi_events');
        \update_option('civicrm_events_saved', $saved);
        \update_option('civicrm_events_trashed', $trashed);
        \update_option('civicrm_events_total', count($events));
    }


    public function save_single_event($event)
    {
        $id = $this->save_event($event);

        $this->ids[$event['id']] = $id;
        \update_option('civicrm_event_ids', $this->ids);
    }

    private function get_civicrm_events()
    {

        if ($_ENV['USE_CACHE'] && (false !== ($cev = \get_transient('civicrm_events')))) {
            return $cev;
        };
        $ch = \curl_init();

        if ($_ENV['USE_AUTH']) {
            $auth = $_ENV['DEV_USER'] . ':' . $_ENV['DEV_PASS'];
        }
        $user_key = $_ENV['CIVICRM_USER_KEY'];
        $site_key = $_ENV['CIVICRM_SITE_KEY'];

        $site = $_ENV['CIVICRM_URL'];
        $endpoint = '/sites/all/modules/civicrm/extern/rest.php';
        $data = [];
        $f = new DateTime();
        $data['from'] = $f->format("Y-m-d");
        $t = $f->add(new DateInterval("P3M"));
        $data['to'] = $t->format("Y-m-d");
        $data['fields'] = 'id,title,summary,description,start_date,end_date,loc_block_id.id,loc_block_id.address_id.street_address,loc_block_id.address_id.supplemental_address_1,loc_block_id.address_id.supplemental_address_2,loc_block_id.address_id.supplemental_address_3,loc_block_id.address_id.city,loc_block_id.address_id.postal_code,loc_block_id.address_id.geo_code_1,loc_block_id.address_id.geo_code_2,is_map,event_type_id';

        $tpl = self::$plugin->m->loadTemplate('api_call');
        $json = $tpl->render($data);

        $query = '?entity=Event&action=get&json=' . urlencode($json) . '&api_key=' . $user_key . '&key=' . $site_key;

        $url = $site . $endpoint . $query;

        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
        if ($_ENV['USE_AUTH']) {
            \curl_setopt($ch, \CURLOPT_USERPWD, $auth);
        }
        \curl_setopt($ch, \CURLOPT_URL, $url);

        $response = \curl_exec($ch);

        if (\curl_errno($ch)) {
            throw new \Exception(\curl_error($ch));
        }
        if ($_ENV['USE_CACHE']) {
            \delete_transient('civicrm_events');
        }
        if ($_ENV['USE_CACHE']) {
            \set_transient('civicrm_events', $response, 300);
        }
        return $response;
    }

    private function event_filter($event, $debug)
    {
        $this->check_hash($event, $debug);
        return $event;
    }

    private function check_hash(&$event, $debug)
    {
        if ((\array_key_exists($event['id'], $this->ids)) && (\hash("md2", serialize($event)) !== $this->ids[$event['id']]['md2'])) {
            $this->ids[$event['id']]['md2'] = 'dirty';
            if ($debug) {
                echo $event['id'] . ": " . $this->ids[$event['id']]['md2'] . " ------ wp-id: " . $this->ids[$event['id']]['wp_id']  . "<br/>";
            }
        };

        if ($debug) {
            echo $event['id'] . " " . \hash("md2", serialize($event)) . " ______ " . $this->ids[$event['id']]['md2'] . " ------ wp-id: " . $this->ids[$event['id']]['wp_id'] . "<br/>";
        }

        return true;
    }

    private function get_ids()
    {
        return \get_option('civicrm_event_ids');
    }

    private function save_event($event)
    {

        $post = [
            'post_id' => false,
            'post_type' => self::$plugin::$post_type,
            'post_title' => $event['title'],
            'post_content' => '',
            'post_status' => 'publish'
        ];

        if (\array_key_exists('description', $event)) {
            $post['post_content'] = $event['description'];
        }

        //If the event ID is already known, and the event hasn't been modified, there's nothing to do.
        if (\array_key_exists($event['id'], $this->ids)) {
            if ($this->ids[$event['id']]['md2'] !== 'dirty') {
                return;
            }
            // If the event has been modified we update
            $post['ID'] = $this->ids[$event['id']]['wp_id'];
        }

        $wp_post_id = \wp_insert_post($post, true);

        // Gavin's previous meta stuff. Leave commented for reference.
        //
        // self::try_update_meta($wp_post_id, 'event_from', $event, 'start_date', true);
        // self::try_update_meta($wp_post_id, 'event_to', $event, 'end_date', true);
        // self::try_update_meta($wp_post_id, 'event_loc_street', $event, 'street_address');
        // self::try_update_meta($wp_post_id, 'event_loc_extra', $event, 'supplemental_address_1');
        // self::try_update_meta($wp_post_id, 'event_loc_town', $event, 'city');
        // self::try_update_meta($wp_post_id, 'event_loc_postcode', $event, 'postal_code');
        // self::try_update_meta($wp_post_id, 'latitude', $event, 'geo_code_1');
        // self::try_update_meta($wp_post_id, 'longitude', $event, 'geo_code_2');
        // \update_post_meta($wp_post_id, 'show_map', $event, 'show_map');
        // \update_post_meta($wp_post_id, 'event_civicrm_id', $event['id']);
        // \update_post_meta($wp_post_id, 'event_multiday', self::is_multiday($event));

        // Get event data
        $civi_crm_id = $event['id'] ?? false;
        $start_date = $event['start_date'] ?? false; // Format: yyyy-mm-dd hh:mm:ss
        $end_date = $event['end_date'] ?? false; // Format: yyyy-mm-dd hh:mm:ss
        $latitude = $event['geo_code_1'] ?? false;
        $longitude = $event['geo_code_2'] ?? false;
        $description = $event['description'] ?? false;
        $summary = $event['summary'] ?? false;
        $location_name = $event['street_address'] ?? false;
        $start_time = false; // Get from start date
        $end_time = false; // Get from end date
        $event_type = $event['event_type_id'] ?? false;
        $address = [
            'street' => $event['street_address'] ?? false,
            'extra' => $event['supplemental_address_1'] ?? false,
            'city' => $event['city'] ?? false,
            'postcode' => $event['postal_code'] ?? false,
        ];
        $address_final = '';

        // Adult and young carer categories
        // These are set on Options page.
        $adult_carer_category_object = get_field('options__event_options__adult_carer_category', 'options');
        $young_carer_category_object = get_field('options__event_options__young_carer_category', 'options');

        // Process address components and construct final address
        foreach ($address as $key => $value) {
            if ($value && !empty($value)) {
                $address_final .= "$value, ";
            }
        }

        // Add final address as post meta
        if (!empty($address_final)) {
            // Remove space and comma from the end of the address
            $address_final = rtrim($address_final, ', ');

            // Capitalise first letter of address
            $address_final = ucfirst($address_final);

            // Insert as post meta
            update_post_meta($wp_post_id, 'location_address', $address_final);
        }

        // Process start and end dates to get times
        if ($start_date) {
            $start_date_object = DateTime::createFromFormat('Y-m-d H:i:s', $start_date);

            if ($start_date_object) {
                $start_time = self::get_event_time_from_date($start_date_object);

                // Add start date and time meta
                update_post_meta($wp_post_id, 'start_date', $start_date_object->format('Ymd'));
                update_post_meta($wp_post_id, 'start_time', $start_time);
            }
        }

        if ($end_date) {
            $end_date_object = DateTime::createFromFormat('Y-m-d H:i:s', $end_date);

            if ($end_date_object) {
                $end_time = self::get_event_time_from_date($end_date_object);

                // Add end date and time meta
                update_post_meta($wp_post_id, 'end_date', $end_date_object->format('Ymd'));
                update_post_meta($wp_post_id, 'end_time', $end_time);
            }
        }

        // Add other event meta
        if ($civi_crm_id) {
            update_post_meta($wp_post_id, 'event_civicrm_id', $civi_crm_id);
        }

        if ($latitude) {
            update_post_meta($wp_post_id, 'latitude', $latitude);
        }

        if ($longitude) {
            update_post_meta($wp_post_id, 'longitude', $longitude);
        }

        if ($description) {
            update_post_meta($wp_post_id, 'event_description', $description);
        }

        if ($location_name) {
            update_post_meta($wp_post_id, 'location_name', ucwords($location_name));
        }

        // Set event audience category for event
        if ($event_type && is_numeric($event_type)) {
            $event_type = intval($event_type);

            if ($event_type === 1 && $adult_carer_category_object && $adult_carer_category_object instanceof \WP_Term) {
                wp_set_post_terms($wp_post_id, $adult_carer_category_object->term_id, $adult_carer_category_object->taxonomy);
            } else if ($event_type === 2 && $young_carer_category_object && $young_carer_category_object instanceof \WP_Term) {
                wp_set_post_terms($wp_post_id, $young_carer_category_object->term_id, $young_carer_category_object->taxonomy);
            }
        } else {
            // If no event type is set/found, then assume it's an adult carer event
            wp_set_post_terms($wp_post_id, $adult_carer_category_object->term_id, $adult_carer_category_object->taxonomy);
        }

        $id = [];
        $id['wp_id'] = $wp_post_id;
        $id['md2'] = \hash("md2", serialize($event));

        return $id;
    }

    private static function is_multiday($event)
    {
        if ((date('Ymd', strtotime($event['start_date']))) !== date('Ymd', strtotime($event['end_date']))) {
            return true;
        }
        return false;
    }

    private static function try_update_meta($id, $meta_key, $array, $key, $datetime = false)
    {
        if (array_key_exists($key, $array)) {
            if ($datetime) {
                \update_post_meta($id, $meta_key, strtotime($array[$key]));
            } else {
                \update_post_meta($id, $meta_key, $array[$key]);
            }
        };
    }

    /**
     * Generates an appropriate time value from a given event date.
     * 
     * @param \DateTime $date
     * @param string $format
     * @return string|false
     */
    private static function get_event_time_from_date($date_object, $time_format = 'H:i')
    {
        $time = false;

        if ($date_object instanceof DateTime) {
            $time = $date_object->format($time_format);
        }

        return $time;
    }
}
