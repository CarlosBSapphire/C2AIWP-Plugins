<?php

/**
 * Plugin Name: AI Products Order Widget
 * Plugin URI: https://customer2.ai
 * Description: A comprehensive order widget for AI Calls, AI Emails, and AI Chat with addons and user account creation
 * Version: 2.0.0
 * Author: Ian Fry
 * Author URI: https://customer2.ai
 * License: GPL v2 or later
 * Text Domain: ai-products-widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIPW_VERSION', '2.0.0');
define('AIPW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPW_BASE_DIR', dirname(__FILE__));

// Include Composer autoloader for third-party dependencies (dompdf, libphonenumber)
require_once '/home/customer2/htdocs/customer2.ai/wp-content/mu-plugins/ai-products-order-widget/vendor/autoload.php';

// Include our custom autoloader for AIPW classes
require_once '/home/customer2/htdocs/customer2.ai/wp-content/mu-plugins/ai-products-order-widget/src/autoload.php';

use AIPW\Core\OrderProcessor;
//use AIPW\Core\SecurityValidator;
use AIPW\Core\ApiProxy;
use AIPW\Services\N8nClient;
use AIPW\Services\PhoneValidator;
use AIPW\Adapters\WordPressHttpClient;
use AIPW\Adapters\WordPressCache;
use AIPW\Adapters\WordPressLogger;

/**
 * Main WordPress Plugin Class
 *
 * This class handles WordPress-specific integration (hooks, shortcodes, AJAX).
 * Business logic is delegated to platform-agnostic classes in src/
 */
class AI_Products_Order_Widget
{
    private static $instance = null;

    /**
     * Platform-agnostic order processor
     *
     * @var OrderProcessor
     */
    private $orderProcessor;

    /**
     * n8n API client
     *
     * @var N8nClient
     */
    private $n8nClient;

    /**
     * Phone validator
     *
     * @var PhoneValidator
     */
    private $phoneValidator;

    /**
     * Logger
     *
     * @var WordPressLogger
     */
    private $logger;

    /**
     * Cache adapter
     *
     * @var WordPressCache
     */
    private $cache;

    /**
     * API Proxy
     *
     * @var ApiProxy
     */
    private $apiProxy;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (Singleton pattern)
     */
    private function __construct()
    {
        $this->init_dependencies();
        $this->init_hooks();
    }

    /**
     * Initialize dependencies (Dependency Injection)
     */
    private function init_dependencies()
    {
        // Create WordPress adapters
        $this->logger = new WordPressLogger('AIPW');
        $this->cache = new WordPressCache();

        // Create HTTP client callable
        $httpClient = function ($url, $method, $data, $headers) {
            return WordPressHttpClient::request($url, $method, $data, $headers);
        };

        // Create logger callable
        $loggerCallable = function ($message, $level, $context) {
            $this->logger->log($message, $level, $context);
        };

        // Create HelpFunctions instance
        $helperFunctions = new \AIPW\Services\HelperFunctions();

        // Create n8n client with HelpFunctions
        $this->n8nClient = new N8nClient($httpClient, $loggerCallable, $this->cache, $helperFunctions);

        // Create phone validator
        $this->phoneValidator = new PhoneValidator();

        // Create order processor
        $this->orderProcessor = new OrderProcessor(
            $this->n8nClient,
            $this->phoneValidator,
            $loggerCallable
        );

        // Create API proxy
        $this->apiProxy = new ApiProxy($this->n8nClient, $loggerCallable);
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        add_action('init', [$this, 'init']);
        add_shortcode('ai_products_widget', [$this, 'render_widget']);
        add_shortcode('ai_porting_loa', [$this, 'render_porting_loa']);

        // Legacy AJAX handler (for old form-based widget)
        add_action('wp_ajax_aipw_submit_order', [$this, 'handle_order_submission']);
        add_action('wp_ajax_nopriv_aipw_submit_order', [$this, 'handle_order_submission']);

        // New API proxy handlers (for modal widget)
        add_action('wp_ajax_aipw_api_proxy', [$this, 'handle_api_proxy']);
        add_action('wp_ajax_nopriv_aipw_api_proxy', [$this, 'handle_api_proxy']);

        // Porting LOA API handlers
        add_action('wp_ajax_aipw_loa_proxy', [$this, 'handle_loa_proxy']);
        add_action('wp_ajax_nopriv_aipw_loa_proxy', [$this, 'handle_loa_proxy']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * WordPress init hook
     */
    public function init()
    {
        // Any WordPress-specific initialization
    }

    /**
     * Handle API proxy requests (for modal widget)
     */
    public function handle_api_proxy()
    {
        // Get request data from JSON body
        $input = file_get_contents('php://input');
        $request = json_decode($input, true);

        $nonce = $request['nonce'] ?? '';

        // Verify nonce manually since it's in JSON body
        if (!wp_verify_nonce($nonce, 'aipw_api_proxy')) {
            error_log('[AIPW] Nonce verification failed: ' . $nonce);
            wp_send_json_error([
                'message' => 'Security verification failed',
                'error_code' => 'INVALID_NONCE'
            ]);
            return;
        }

        $action = $request['action'] ?? '';
        $data = $request['data'] ?? [];

        error_log('[AIPW] API proxy request: ' . $action);

        // Remove 'aipw_' prefix if present
        $action = str_replace('aipw_', '', $action);

        // Proxy the request
        $result = $this->apiProxy->handle($action, $data);

        // Return JSON response
        wp_send_json($result);
    }

    /**
     * Handle LOA-specific API proxy requests
     */
    public function handle_loa_proxy()
    {
        // Get request data from JSON body
        $input = file_get_contents('php://input');
        $request = json_decode($input, true);

        $nonce = $request['nonce'] ?? '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'aipw_loa_proxy')) {
            error_log('[AIPW] LOA Proxy nonce verification failed');
            wp_send_json_error([
                'message' => 'Security verification failed',
                'error_code' => 'INVALID_NONCE'
            ]);
            return;
        }

        $action = $request['action'] ?? '';
        $data = $request['data'] ?? [];

        error_log('[AIPW] LOA Proxy action: ' . $action);

        try {
            switch ($action) {
                case 'aipw_get_loa_by_uuid':
                    $uuid = $data['uuid'] ?? '';

                    if (empty($uuid)) {
                        wp_send_json_error([
                            'message' => 'Missing required field: uuid',
                            'error_code' => 'MISSING_UUID'
                        ]);
                        return;
                    }

                    $result = $this->n8nClient->getLoaByUuid($uuid);

                    if ($result['success']) {
                        wp_send_json_success($result['data']);
                    } else {
                        wp_send_json_error([
                            'message' => $result['error'],
                            'error_code' => 'FETCH_FAILED'
                        ]);
                    }
                    break;

                case 'aipw_update_loa_signature':
                    $uuid = $data['uuid'] ?? '';
                    $loaHtml = $data['loa_html'] ?? '';
                    $utilityBillBase64 = $data['utility_bill_base64'] ?? null;
                    $utilityBillFilename = $data['utility_bill_filename'] ?? null;
                    $utilityBillMimeType = $data['utility_bill_mime_type'] ?? null;
                    $utilityBillExtension = $data['utility_bill_extension'] ?? null;

                    if (empty($uuid) || empty($loaHtml)) {
                        wp_send_json_error([
                            'message' => 'Missing required fields: uuid and loa_html',
                            'error_code' => 'MISSING_FIELDS'
                        ]);
                        return;
                    }

                    $result = $this->n8nClient->updatePortingLoaSignature(
                        $uuid,
                        $loaHtml,
                        $utilityBillBase64,
                        $utilityBillFilename,
                        $utilityBillMimeType,
                        $utilityBillExtension
                    );

                    if ($result['success']) {
                        wp_send_json_success($result['data']);
                    } else {
                        wp_send_json_error([
                            'message' => $result['error'],
                            'error_code' => 'UPDATE_FAILED'
                        ]);
                    }
                    break;

                default:
                    wp_send_json_error([
                        'message' => 'Unknown action: ' . $action,
                        'error_code' => 'UNKNOWN_ACTION'
                    ]);
            }
        } catch (Exception $e) {
            error_log('[AIPW] LOA Proxy exception: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Server error: ' . $e->getMessage(),
                'error_code' => 'SERVER_ERROR'
            ]);
        }
    }

    /**
     * Handle AJAX order submission (legacy handler)
     */
    public function handle_order_submission()
    {
        // Verify nonce
        if (!check_ajax_referer('aipw_order_submit', 'aipw_nonce', false)) {
            wp_send_json_error([
                'message' => 'Security verification failed'
            ]);
        }

        // Get form data
        $formData = $_POST;

        // Remove WordPress-specific fields
        unset($formData['action'], $formData['aipw_nonce']);

        // Process order using platform-agnostic processor
        $result = $this->orderProcessor->processOrder($formData);

        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Order submitted successfully',
                'data' => $result['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Order submission failed',
                'errors' => $result['errors']
            ]);
        }
    }

    /**
     * Render widget shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_widget($atts)
    {
        // Get configuration from order processor
        $products = $this->orderProcessor->getProducts();
        $addons = $this->orderProcessor->getAddons();
        $agent_levels = $this->orderProcessor->getAgentLevels();

        // Get pricing data
        //$pricing_result = $this->orderProcessor->getPricing();
        $pricing = $pricing_result['data'] ?? [];

        // Include template file (simple trigger button for modal)
        ob_start();
        include AIPW_PLUGIN_DIR . 'templates/widget-trigger.php';
        return ob_get_clean();
    }

    /**
     * Render standalone porting LOA form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_porting_loa($atts)
    {
        // Only show for logged-in users
        if (!is_user_logged_in()) {
            return '<p>Please log in to access your porting forms.</p>';
        }

        // Include template file
        ob_start();
        include AIPW_PLUGIN_DIR . 'templates/porting-loa-standalone.php';
        return ob_get_clean();
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets()
    {
        // Only enqueue on pages that use the shortcodes
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_product_widget = has_shortcode($post->post_content, 'ai_products_widget');
        $has_loa_widget = has_shortcode($post->post_content, 'ai_porting_loa');

        if (!$has_product_widget && !$has_loa_widget) {
            return;
        }

        // Enqueue Stripe.js v3
        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            [],
            '3.0',
            true
        );

        // Enqueue libphonenumber-js for client-side validation
        wp_enqueue_script(
            'libphonenumber',
            AIPW_PLUGIN_URL . 'assets/js/libphonenumber-max.js',
            [],
            '1.10.14',
            true
        );

        // Enqueue modal widget scripts
        wp_enqueue_script(
            'aipw-modal-widget',
            AIPW_PLUGIN_URL . 'assets/js/modal-widget.js',
            ['stripe-js', 'libphonenumber'],
            AIPW_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('aipw-modal-widget', 'aipwConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aipw_api_proxy'),
            'apiProxy' => admin_url('admin-ajax.php') . '?action=aipw_api_proxy',
            'stripePublicKey' => 'pk_test_51RO1TFI6Mo3ACLGTuEJTA0vmAS6XovFb3ym9oTp9kPW6OO7s9IZI9DTsxQfLaAdzLQqBB4bzQeFfDu6Ux4YpB2hw002QJW8iRr',
            'version' => AIPW_VERSION
        ]);

        // Enqueue modal widget styles
        wp_enqueue_style(
            'aipw-modal-widget-styles',
            AIPW_PLUGIN_URL . 'assets/css/modal-widget.css',
            [],
            AIPW_VERSION
        );

        // Enqueue LOA standalone widget scripts (if LOA shortcode is present)
        if ($has_loa_widget) {
            // Enqueue CSS
            wp_enqueue_style(
                'aipw-loa-standalone',
                AIPW_PLUGIN_URL . 'assets/css/loa-standalone.css',
                [],
                AIPW_VERSION
            );

            // Enqueue JS
            wp_enqueue_script(
                'aipw-loa-standalone',
                AIPW_PLUGIN_URL . 'assets/js/loa-standalone.js',
                ['jquery'],
                AIPW_VERSION,
                true
            );

            wp_localize_script('aipw-loa-standalone', 'aipwLoaConfig', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aipw_loa_proxy'),
                'userId' => get_current_user_id()
            ]);
        }
    }

    /**
     * Public API: Get pricing data
     *
     * @return array
     */
    public function get_pricing($data)
    {
        return $this->orderProcessor->getPricing($data);
    }

    /**
     * Public API: Validate phone number
     *
     * @param string $phone
     * @param string|null $country
     * @return array
     */
    public function validate_phone($phone, $country = null)
    {
        return $this->phoneValidator->validate($phone, $country);
    }
}

/**
 * Initialize the plugin
 */
function aipw_init()
{
    return AI_Products_Order_Widget::get_instance();
}

// Initialize plugin
add_action('plugins_loaded', 'aipw_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function () {
    // Clear any cached data on activation
    $cache = new WordPressCache();
    $cache->clearByPrefix('aipw_');

    // Log activation
    error_log('[AIPW] Plugin activated - Version ' . AIPW_VERSION);
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    // Clear cached data on deactivation
    $cache = new WordPressCache();
    $cache->clearByPrefix('aipw_');

    // Log deactivation
    error_log('[AIPW] Plugin deactivated');
});
