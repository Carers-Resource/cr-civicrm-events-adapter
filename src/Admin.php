<?php

namespace CarersResource\CiviEvents;

class Admin
{
    private static $plugin;

    public static function register($plugin)
    {
        $plugin::$admin = new self();
        self::$plugin = $plugin;
        add_action('admin_menu', array($plugin::$admin, 'admin_menu'));
        add_action('admin_post_civi_events_erase_ids', array($plugin::$admin, 'civi_events_erase_ids'));
        add_action('admin_post_civi_save_event', [$plugin::$admin, 'civi_save_event']);
        add_action('admin_post_civi_sync_all', [$plugin::$admin, 'civi_sync_all']);
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
        $data = Self::$plugin->data;
        $data['clear_nonce'] = \wp_nonce_field('civi_events_erase_ids', '_wpnonce', true, false);
        $data['save_nonce'] = \wp_nonce_field('civi_save_event', '_wpnonce2', true, false);
        $data['sync_nonce'] = \wp_nonce_field('civi_sync_all', '_wpnonce3', true, false);
        $data['stored_ids'] = serialize(\get_option('civicrm_event_ids'));
        $data['last_sync'] = \get_option('civicrm_last_sync');

        $t = self::$plugin->m->loadTemplate('admin');

        echo $t->render($data);
        $this->civi_events_list();
    }

    public static function civi_events_erase_ids()
    {
        check_admin_referer('civi_events_erase_ids', '_wpnonce');
        update_option('civicrm_event_ids', []);
        delete_transient('civicrm_events');
        wp_redirect(admin_url('admin.php?page=civi-events'));
        exit;
    }

    private function civi_events_list()
    {
        $t = self::$plugin->m->loadTemplate('events_list');
        $data['events'] = self::$plugin::$adapter->get_events();
        echo $t->render($data);
    }

    public function civi_save_event()
    {
        check_admin_referer('civi_save_event', '_wpnonce2');
        $e = self::$plugin::$adapter->get_events();
        self::$plugin::$adapter->save_single_event($e[0]);
        wp_redirect(admin_url('admin.php?page=civi-events'));
        exit;
    }

    public function civi_sync_all()
    {
        check_admin_referer('civi_sync_all', '_wpnonce3');
        self::$plugin::$adapter->sync();
        \update_option('civicrm_last_sync', \current_time('Y-m-d H:i:s') . ' manual');
        wp_redirect(admin_url('admin.php?page=civi-events'));
        exit;
    }
}
