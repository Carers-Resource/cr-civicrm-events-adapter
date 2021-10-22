<?php

namespace CarersResource\CiviEvents;

class Admin
{
    private static $plugin;

    public static function register($plugin)
    {
        if ($plugin->admin) {
            return $plugin;
        }
        $plugin->admin = new self();
        $plugin->admin::$plugin = $plugin;
        add_action('admin_menu', array($plugin->admin, 'admin_menu'));
        add_action('admin_post_civi_events_erase_ids', array($plugin->admin, 'civi_events_erase_ids'));
        return $plugin;
    }

    public function admin_menu()
    {
        add_menu_page(
            self::$plugin->data['title'],
            self::$plugin->data['menu_title'],
            'edit_posts',
            self::$plugin->data['menu_slug'],
            [$this, 'civi_events_admin_page']
        );
    }

    public function civi_events_admin_page()
    {
        #$tpl = $this->plugin->m->loadTemplate('admin'); // loads __DIR__.'/views/admin.mustache';
        self::$plugin->data['clear_nonce'] = wp_nonce_field('civi_events_erase_ids', '_wpnonce', true, false);

        self::$plugin->data['stored_ids'] = serialize(\get_option('civicrm_event_ids'));

        #$events_json = $this->plugin->data['response'];
        $t = self::$plugin->m->loadTemplate('admin');


        echo $t->render(self::$plugin->data);
        self::$plugin->adapter->process_events();
        $this->civi_events_list();

        self::$plugin->adapter->save_first_event();
    }

    public static function civi_events_erase_ids()
    {
        check_admin_referer('civi_events_erase_ids', '_wpnonce');
        update_option('civicrm_event_ids', []);
        wp_redirect(admin_url('admin.php?page=civi_events'));
        exit;
    }


    public function civi_events_list()
    {
        $t = self::$plugin->m->loadTemplate('events_list');
        $data['events'] = self::$plugin->events;
        echo $t->render($data);
    }
}
