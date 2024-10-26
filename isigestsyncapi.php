<?php
/**
 * Plugin Name: ISIGest Sync API
 * Description: Plugin per la sincronizzazione dei prodotti tramite API
 * Version: 1.0.0
 * Author: Massimo Caroccia & Claude
 * Author URI: https://www.isigest.it
 */

namespace ISIGestSyncAPI;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private static $instance = null;
    private $router;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        $this->loadDependencies();
        $this->registerHooks();
        $this->registerCustomTables();
    }

    private function loadDependencies() {
        require_once plugin_dir_path(__FILE__) . 'includes/Core/Router.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Core/ApiHandler.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Core/Exceptions.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Core/ConfigHelper.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Core/Utilities.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Services/ProductService.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Services/StockService.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Services/ImageService.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Admin/Settings.php';
        require_once plugin_dir_path(__FILE__) . 'includes/Admin/SettingsHelper.php';

        $this->router = new Core\Router();
    }

    private function registerHooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'handleApiRequest'));
    }

    private function registerCustomTables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();
        
        // Tabelle custom qui...
        
        foreach($sql as $query) {
            dbDelta($query);
        }
    }

    public function activate() {
        $this->registerCustomTables();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function handleApiRequest() {
        if (strpos(trim($_SERVER['REQUEST_URI'], '/'), 'isigestsyncapi') !== false) {
            $this->router->handleRequest();
        }
    }
}

// Inizializza il plugin
ISIGestSyncAPI\Plugin::getInstance();
