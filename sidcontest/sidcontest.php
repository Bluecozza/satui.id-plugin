<?php
/*
Plugin Name: SidContest
Plugin URI: https://example.com/sidcontest
Description: Plugin untuk mengelola kontes dan giveaway.
Version: 1.0.0
Author: Anda
Author URI: https://example.com
License: GPLv2 or later
Text Domain: sidcontest
*/

defined('ABSPATH') or die('No script kiddies please!');

define('SIDCONTEST_VERSION', '1.0.0');
define('SIDCONTEST_PATH', plugin_dir_path(__FILE__));
define('SIDCONTEST_URL', plugin_dir_url(__FILE__));

// Inisialisasi plugin
class SidContest {
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }

    public function activate() {
        $this->create_tables();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabel kontes
        $contests_table = $wpdb->prefix . 'sidcontests';
        $sql = "CREATE TABLE $contests_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            code varchar(100) NOT NULL UNIQUE,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Tabel peserta
        $participants_table = $wpdb->prefix . 'sidparticipants';
        $sql .= "CREATE TABLE $participants_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            contest_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            contact varchar(255) NOT NULL,
            answer text,
            participant_hash varchar(64) NOT NULL UNIQUE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            FOREIGN KEY (contest_id) REFERENCES $contests_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function init_plugin() {
        // Load dependencies
        require_once SIDCONTEST_PATH . 'includes/functions.php';
        require_once SIDCONTEST_PATH . 'includes/ajax-handler.php';
        require_once SIDCONTEST_PATH . 'includes/updater.php';
        
        // Load modules
        $this->load_modules();
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Shortcode
        add_shortcode('sidcontest_search', array($this, 'search_form_shortcode'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'admin_menu'));
    }

    public function load_modules() {
        $modules_dir = SIDCONTEST_PATH . 'modules/';
        $active_modules = get_option('sidcontest_active_modules', array('random-giveaway'));
        
        foreach(glob($modules_dir . '*', GLOB_ONLYDIR) as $module_dir) {
            $module_name = basename($module_dir);
            $module_file = $module_dir . '/module.php';
            
            if(file_exists($module_file) && in_array($module_name, $active_modules)) {
                require_once $module_file;
            }
        }
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('sidcontest-frontend', SIDCONTEST_URL . 'assets/css/frontend.css');
        wp_enqueue_script('sidcontest-frontend', SIDCONTEST_URL . 'assets/js/frontend.js', array('jquery'), SIDCONTEST_VERSION, true);
        
        wp_localize_script('sidcontest-frontend', 'sidcontest_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sidcontest_nonce')
        ));
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_style('sidcontest-admin', SIDCONTEST_URL . 'assets/css/admin.css');
        wp_enqueue_script('sidcontest-admin', SIDCONTEST_URL . 'assets/js/admin.js', array('jquery'), SIDCONTEST_VERSION, true);
    }

    public function search_form_shortcode() {
        ob_start();
        include SIDCONTEST_PATH . 'templates/form-search.php';
        return ob_get_clean();
    }

    public function admin_menu() {
        add_menu_page(
            'SidContest',
            'SidContest',
            'manage_options',
            'sidcontest',
            array($this, 'admin_dashboard'),
            'dashicons-awards'
        );
        
        add_submenu_page(
            'sidcontest',
            'Module Manager',
            'Module Manager',
            'manage_options',
            'sidcontest-modules',
            array($this, 'module_manager')
        );
    }

    public function admin_dashboard() {
        include SIDCONTEST_PATH . 'admin/dashboard.php';
    }

    public function module_manager() {
        include SIDCONTEST_PATH . 'admin/module-manager.php';
    }
}

new SidContest();