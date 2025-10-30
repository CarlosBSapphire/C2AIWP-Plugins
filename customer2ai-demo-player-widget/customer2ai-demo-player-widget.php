<?php
/**
 * Plugin Name: Customer2AI Demo Player Widget for Elementor
 * Description: Custom audio demo player widget with transcripts and features
 * Version: 1.0.0
 * Author: Ian Fry
 * Text Domain: customer2ai-demo-player-widget
 */

if (!defined('ABSPATH')) exit;

final class Customer2AI_Demo_Player_Widget {
    
    const VERSION = '1.0.0';
    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';
    const MINIMUM_PHP_VERSION = '7.0';

    private static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'add_cors_headers']);
    }

    public function init() {
        // Check if Elementor installed and activated
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_main_plugin']);
            return;
        }

        // Check for required Elementor version
        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_elementor_version']);
            return;
        }

        // Check for required PHP version
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_php_version']);
            return;
        }

        // Register Widget
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        
        // Register Widget Scripts
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_widget_scripts']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_widget_styles']);
    }

    public function admin_notice_missing_main_plugin() {
        if (isset($_GET['activate'])) unset($_GET['activate']);
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'customer2ai-demo-player-widget'),
            '<strong>' . esc_html__('Customer2AI Demo Player Widget', 'customer2ai-demo-player-widget') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'customer2ai-demo-player-widget') . '</strong>'
        );
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function admin_notice_minimum_elementor_version() {
        if (isset($_GET['activate'])) unset($_GET['activate']);
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'customer2ai-demo-player-widget'),
            '<strong>' . esc_html__('Customer2AI Demo Player Widget', 'customer2ai-demo-player-widget') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'customer2ai-demo-player-widget') . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function admin_notice_minimum_php_version() {
        if (isset($_GET['activate'])) unset($_GET['activate']);
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'customer2ai-demo-player-widget'),
            '<strong>' . esc_html__('Customer2AI Demo Player Widget', 'customer2ai-demo-player-widget') . '</strong>',
            '<strong>' . esc_html__('PHP', 'customer2ai-demo-player-widget') . '</strong>',
            self::MINIMUM_PHP_VERSION
        );
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function register_widgets($widgets_manager) {
        require_once(__DIR__ . '/widgets/customer2ai-demo-player.php');
        $widgets_manager->register(new \Customer2AI_Demo_Player_Widget\Widget());
    }

    public function register_widget_scripts() {
        wp_register_script(
            'customer2ai-demo-player-widget',
            plugins_url('assets/js/demo-player.js', __FILE__),
            ['jquery'],
            self::VERSION,
            true
        );
    }

    public function register_widget_styles() {
        wp_register_style(
            'customer2ai-demo-player-widget',
            plugins_url('assets/css/demo-player.css', __FILE__),
            [],
            self::VERSION
        );
    }

    //add cors headers for audio files
    public function add_cors_headers() {
        if (strpos($_SERVER['REQUEST_URI'], '/wp-content/uploads/') !== false) {
            $allowed_extensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
            $file_extension = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Range');
                
                // Handle preflight requests
                if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    header('Access-Control-Max-Age: 86400');
                    exit(0);
                }
            }
        }
    }

Customer2AI_Demo_Player_Widget::instance();