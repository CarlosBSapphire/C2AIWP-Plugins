<?php
/**
 * Plugin Name: Customer2 Custom 404 Page
 * Plugin URI: https://customer2ai.com
 * Description: Custom 404 error page with space-themed design
 * Version: 1.0.0
 * Author: Ian Fry
 * Author URI: https://customer2ai.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: customer2-404
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('c2ai_404_VERSION', '1.0.0');
define('c2ai_404_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('c2ai_404_PLUGIN_URL', plugin_dir_url(__FILE__));

class customer2ai_404_Page {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('template_redirect', array($this, 'custom_404_page'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Enqueue CSS and JS
     */
    public function enqueue_assets() {
        if (is_404()) {
            wp_enqueue_style(
                'customer2ai-404-style',
                c2ai_404_PLUGIN_URL . 'assets/css/404-style.css',
                array(),
                c2ai_404_VERSION
            );
        }
    }
    
    /**
     * Override 404 template
     */
    public function custom_404_page() {
        if (is_404()) {
            include c2ai_404_PLUGIN_DIR . 'templates/404-template.php';
            exit;
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Custom 404 Settings',
            'Custom 404 Page',
            'manage_options',
            'customer2ai-404-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('customer2ai_404_settings', 'c2ai_404_linkedin_url');
        register_setting('customer2ai_404_settings', 'c2ai_404_facebook_url');
        register_setting('customer2ai_404_settings', 'c2ai_404_instagram_url');
        register_setting('customer2ai_404_settings', 'c2ai_404_home_url');
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Custom 404 Page Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('customer2ai_404_settings');
                do_settings_sections('customer2ai_404_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="c2ai_404_home_url">Home Page URL</label></th>
                        <td>
                            <input type="url" id="c2ai_404_home_url" name="c2ai_404_home_url" 
                                   value="<?php echo esc_attr(get_option('c2ai_404_home_url', home_url('/'))); ?>" 
                                   class="regular-text" />
                            <p class="description">URL for the "Return Home" button</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="c2ai_404_linkedin_url">LinkedIn URL</label></th>
                        <td>
                            <input type="url" id="c2ai_404_linkedin_url" name="c2ai_404_linkedin_url" 
                                   value="<?php echo esc_attr(get_option('c2ai_404_linkedin_url', '')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="c2ai_404_facebook_url">Facebook URL</label></th>
                        <td>
                            <input type="url" id="c2ai_404_facebook_url" name="c2ai_404_facebook_url" 
                                   value="<?php echo esc_attr(get_option('c2ai_404_facebook_url', '')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="c2ai_404_instagram_url">Instagram URL</label></th>
                        <td>
                            <input type="url" id="c2ai_404_instagram_url" name="c2ai_404_instagram_url" 
                                   value="<?php echo esc_attr(get_option('c2ai_404_instagram_url', '')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
function customer2ai_404_init() {
    return customer2ai_404_Page::get_instance();
}
add_action('plugins_loaded', 'customer2ai_404_init');

// Activation hook - create assets directory
register_activation_hook(__FILE__, 'customer2ai_404_activate');
function customer2ai_404_activate() {
    $upload_dir = wp_upload_dir();
    $c2ai_404_dir = $upload_dir['basedir'] . '/customer2ai-404';
    
    if (!file_exists($c2ai_404_dir)) {
        wp_mkdir_p($c2ai_404_dir);
    }
}
