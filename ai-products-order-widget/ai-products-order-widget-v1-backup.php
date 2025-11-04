<?php

/**
 * Plugin Name: AI Products Order Widget
 * Plugin URI: https://customer2.ai
 * Description: A comprehensive order widget for AI Calls, AI Emails, and AI Chat with addons and user account creation
 * Version: 1.0.0
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
define('AIPW_VERSION', '1.0.0');
define('AIPW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BASE_DIR', dirname(__FILE__));


// Include Composer autoloader for dompdf
require_once BASE_DIR . 'vendor/autoload.php';

/**
 * Main Plugin Class
 */
class AI_Products_Order_Widget
{

    private static $instance = null;

    // API Endpoints
    const API_SELECT = 'https://n8n.workflows.organizedchaos.cc/webhook/da176ae9-496c-4f08-baf5-6a78a6a42adb';
    const API_CREATE_USER = 'https://n8n.workflows.organizedchaos.cc/webhook/users/create';

    // Product Configuration
    private $products = [
        'ai_calls' => [
            'name' => 'AI Calls (Phone)',
            'slug' => 'ai_calls',
            'has_phone_setup' => true
        ],
        'ai_emails' => [
            'name' => 'AI Emails',
            'slug' => 'ai_emails',
            'has_phone_setup' => false
        ],
        'ai_chat' => [
            'name' => 'AI Chat',
            'slug' => 'ai_chat',
            'has_phone_setup' => false
        ]
    ];

    // Addons Configuration
    private $addons = [
        'qa' => 'QA',
        'avs_match' => 'AVS Match',
        'custom_package' => 'Custom Package',
        'phone_numbers' => 'Phone Numbers',
        'lead_verification' => 'Lead Verification',
        'transcription_recordings' => 'Transcription & Recordings'
    ];

    // Agent Levels
    private $agent_levels = [
        'essential' => 'Essential',
        'responsive' => 'Responsive',
        'conversational' => 'Conversational'
    ];

    // Call Setup Types
    private $call_setup_types = [
        'forwarding' => 'All Numbers To 1 Agent Number (Forwarding)',
        'porting' => 'Port existing numbers to our provider(s)',
        'standard' => 'Individual Numbers to Individual Agent (Standard Setup)',
        'multi_forward' => 'Multi-forward to Multi-agent for Each Phone Number'
    ];

    // Pricing Configuration (defaults from CSV, can be overridden by API)
    private $pricing = null;

    // Default pricing structure (fallback if API fails)
    private $default_pricing = [
        'setup' => [
            '1_service' => ['min' => 0.00, 'default' => 499.00],
            '2_services' => ['min' => 99.00, 'default' => 749.00],
            '3_plus_services' => ['min' => 199.00, 'default' => 999.00]
        ],
        'inbound_calls' => [
            'essential' => ['min' => 0.35, 'default' => 0.45, 'per' => 'minute', 'notes' => 'OSS'],
            'responsive' => ['min' => 0.35, 'default' => 0.55, 'per' => 'minute', 'notes' => 'ChatGPT 4.5'],
            'conversational' => ['min' => 0.45, 'default' => 0.65, 'per' => 'minute', 'notes' => 'Claude 4.0']
        ],
        'outbound_calls' => [
            'essential' => ['min' => 0.35, 'default' => 0.45, 'per' => 'minute', 'notes' => 'OSS'],
            'responsive' => ['min' => 0.35, 'default' => 0.55, 'per' => 'minute', 'notes' => 'ChatGPT 4.5'],
            'conversational' => ['min' => 0.45, 'default' => 0.65, 'per' => 'minute', 'notes' => 'Claude 4.0']
        ],
        'emails' => [
            'basic' => [
                'base_rate' => 25.00,
                'per' => 'week',
                'included' => 250,
                'additional' => 0.10,
                'notes' => 'Individual Responses'
            ]
        ],
        'chatbots' => [
            'basic' => [
                'base_rate' => 25.00,
                'per' => 'week',
                'included' => 200,
                'additional' => 0.10,
                'notes' => 'Chats'
            ]
        ],
        'addons' => [
            'call_recording' => [
                'free_period' => '90 Day Free',
                'rate' => 125.00,
                'per' => 'week'
            ],
            'transcriptions' => [
                'included_with' => 'call_recording'
            ],
            'qa_basic' => [
                'rate' => null, // TBD
                'per' => 'lead'
            ],
            'qa_advanced' => [
                'rate' => null, // TBD
                'per' => 'lead'
            ]
        ]
    ];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'init']);
        add_shortcode('ai_products_widget', [$this, 'render_widget']);
        add_action('wp_ajax_aipw_submit_order', [$this, 'handle_order_submission']);
        add_action('wp_ajax_nopriv_aipw_submit_order', [$this, 'handle_order_submission']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Initialize pricing on construct
        $this->initialize_pricing();
    }

    public function init()
    {
        // Any initialization code
    }

    /**
     * Initialize pricing - fetch from API or use defaults
     */
    private function initialize_pricing()
    {
        // Try to get cached pricing first (cache for 1 hour)
        $cached_pricing = get_transient('aipw_pricing_data');

        if (false !== $cached_pricing) {
            $this->pricing = $cached_pricing;
            return;
        }

        // Fetch pricing from API
        $api_pricing = $this->fetch_pricing_from_api();

        if ($api_pricing && !empty($api_pricing)) {
            $this->pricing = $api_pricing;
            // Cache for 1 hour
            set_transient('aipw_pricing_data', $api_pricing, HOUR_IN_SECONDS);
        } else {
            // Fallback to default pricing
            $this->pricing = $this->default_pricing;
            error_log('AIPW: Using default pricing (API fetch failed)');
        }
    }

    /**
     * Fetch pricing data from n8n API endpoint
     *
     * @return array|false Pricing data or false on failure
     */
    private function fetch_pricing_from_api()
    {
        try {
            // Prepare API request payload
            $payload = [
                [
                    'table_name' => 'pricing',
                    'columns' => [
                        'service_type',
                        'package_name',
                        'tier',
                        'min_rate',
                        'default_rate',
                        'per_unit',
                        'notes',
                        'included_quantity',
                        'additional_rate'
                    ],
                    'filters' => [
                        'active' => true
                    ],
                    'page' => 1,
                    'limit' => 100,
                    'sort' => [
                        'column' => 'service_type',
                        'direction' => 'ASC'
                    ]
                ]
            ];

            // Make API request
            $response = wp_remote_post(self::API_SELECT, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($payload),
                'timeout' => 15
            ]);

            // Check for errors
            if (is_wp_error($response)) {
                error_log('AIPW Pricing API Error: ' . $response->get_error_message());
                return false;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                error_log('AIPW Pricing API returned status: ' . $status_code);
                return false;
            }

            // Parse response
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (empty($body)) {
                error_log('AIPW Pricing API returned empty response');
                return false;
            }

            // Transform API data to our pricing structure
            return $this->transform_api_pricing($body);
        } catch (Exception $e) {
            error_log('AIPW Pricing API Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Transform API pricing data to our internal structure
     *
     * @param array $api_data Raw API response data
     * @return array Transformed pricing structure
     */
    private function transform_api_pricing($api_data)
    {
        // Initialize empty pricing structure
        $pricing = [
            'setup' => [],
            'inbound_calls' => [],
            'outbound_calls' => [],
            'emails' => [],
            'chatbots' => [],
            'addons' => []
        ];

        // If API data is empty or invalid, return default pricing
        if (!is_array($api_data) || empty($api_data)) {
            return $this->default_pricing;
        }

        // Process each pricing record from API
        foreach ($api_data as $record) {
            if (!isset($record['service_type'])) {
                continue;
            }

            $service_type = strtolower(str_replace(' ', '_', $record['service_type']));
            $tier = isset($record['tier']) ? strtolower($record['tier']) : 'essential';

            // Build pricing entry
            $entry = [
                'min' => isset($record['min_rate']) ? floatval($record['min_rate']) : 0,
                'default' => isset($record['default_rate']) ? floatval($record['default_rate']) : 0,
                'per' => isset($record['per_unit']) ? $record['per_unit'] : '',
                'notes' => isset($record['notes']) ? $record['notes'] : ''
            ];

            // Add optional fields
            if (isset($record['included_quantity'])) {
                $entry['included'] = intval($record['included_quantity']);
            }
            if (isset($record['additional_rate'])) {
                $entry['additional'] = floatval($record['additional_rate']);
            }

            // Assign to appropriate category
            if (isset($pricing[$service_type])) {
                $pricing[$service_type][$tier] = $entry;
            }
        }

        // Merge with defaults for any missing data
        return array_merge($this->default_pricing, $pricing);
    }

    /**
     * Get pricing for a specific service and tier
     *
     * @param string $service Service type (e.g., 'inbound_calls', 'emails')
     * @param string $tier Tier level (e.g., 'essential', 'responsive', 'conversational')
     * @return array|null Pricing data or null if not found
     */
    public function get_pricing($service, $tier = 'essential')
    {
        if (isset($this->pricing[$service][$tier])) {
            return $this->pricing[$service][$tier];
        }
        return null;
    }

    /**
     * Get all pricing data
     *
     * @return array Complete pricing structure
     */
    public function get_all_pricing()
    {
        return $this->pricing;
    }

    /**
     * Render the widget shortcode
     */
    public function render_widget($atts)
    {
        ob_start();
?>
        <div id="ai-products-widget" class="aipw-container">
            <form id="aipw-order-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="aipw_submit_order">
                <?php wp_nonce_field('aipw_order_submit', 'aipw_nonce'); ?>

                <!-- User Information Section -->
                <div class="aipw-section" id="user-info-section">
                    <h2>User Information</h2>

                    <div class="aipw-field">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>

                    <div class="aipw-field">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>

                    <div class="aipw-field">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="aipw-field">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="aipw-field">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>

                <!-- Product Selection Section -->
                <div class="aipw-section" id="product-selection-section">
                    <h2>Select Products</h2>

                    <?php foreach ($this->products as $product_key => $product): ?>
                        <div class="aipw-product-option">
                            <label>
                                <input type="checkbox"
                                    name="products[]"
                                    value="<?php echo esc_attr($product_key); ?>"
                                    class="aipw-product-checkbox"
                                    data-product="<?php echo esc_attr($product_key); ?>">
                                <?php echo esc_html($product['name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- AI Calls Specific Configuration -->
                <div class="aipw-section aipw-product-config" id="ai_calls-config" style="display:none;">
                    <h2>AI Calls Configuration</h2>

                    <div class="aipw-field">
                        <label>Inbound/Outbound Setup</label>
                        <select name="ai_calls[inbound_outbound]" id="ai_calls_inbound_outbound">
                            <option value="">Select...</option>
                            <option value="inbound">Inbound Only</option>
                            <option value="outbound">Outbound Only</option>
                            <option value="both">Both Inbound & Outbound</option>
                        </select>
                    </div>

                    <div class="aipw-field">
                        <label>Call Setup Type</label>
                        <select name="ai_calls[setup_type]" id="ai_calls_setup_type">
                            <option value="">Select...</option>
                            <?php foreach ($this->call_setup_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="aipw-field">
                        <label>Agent Level</label>
                        <select name="ai_calls[agent_level]" id="ai_calls_agent_level">
                            <option value="">Select...</option>
                            <?php foreach ($this->agent_levels as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="aipw-field">
                        <label>Are You Porting Numbers?</label>
                        <div class="aipw-radio-group">
                            <label>
                                <input type="radio" name="ai_calls[porting]" value="yes" class="aipw-porting-toggle">
                                Yes
                            </label>
                            <label>
                                <input type="radio" name="ai_calls[porting]" value="no" class="aipw-porting-toggle">
                                No
                            </label>
                        </div>
                    </div>

                    <!-- Porting Yes Options -->
                    <div id="porting-yes-options" class="aipw-conditional" style="display:none;">
                        <h3>Porting Configuration</h3>

                        <div class="aipw-field">
                            <label>Phone Numbers to Port *</label>
                            <div id="port-numbers-container">
                                <!-- Phone number fields will be added dynamically -->
                            </div>
                            <button type="button" id="add-port-number-btn" class="aipw-add-btn">+ Add Another Number</button>
                            <div id="port-numbers-error" class="aipw-error" style="display:none;"></div>
                        </div>

                        <div class="aipw-field">
                            <label for="ai_calls_agent_number">Agent Number *</label>
                            <input type="text" name="ai_calls[agent_number]" id="ai_calls_agent_number">
                        </div>

                        <div class="aipw-field">
                            <label>
                                <input type="checkbox" name="ai_calls[whitelabel_port]" value="1">
                                Whitelabel (PDF Port Cloning - Trello Ticket)
                            </label>
                        </div>
                    </div>

                    <!-- Porting No Options -->
                    <div id="porting-no-options" class="aipw-conditional" style="display:none;">
                        <h3>New Number Purchase</h3>

                        <div class="aipw-field">
                            <label for="ai_calls_new_numbers_count">How Many Numbers to Purchase? *</label>
                            <input type="number" name="ai_calls[new_numbers_count]" id="ai_calls_new_numbers_count" min="1">
                        </div>

                        <div class="aipw-field">
                            <label for="ai_calls_new_agent_number">Agent Number *</label>
                            <input type="text" name="ai_calls[new_agent_number]" id="ai_calls_new_agent_number">
                        </div>
                    </div>
                </div>

                <!-- AI Emails Configuration -->
                <div class="aipw-section aipw-product-config" id="ai_emails-config" style="display:none;">
                    <h2>AI Emails Configuration</h2>

                    <div class="aipw-field">
                        <label for="ai_emails_domain">Domain for Email Service</label>
                        <input type="text" name="ai_emails[domain]" id="ai_emails_domain" placeholder="example.com">
                    </div>

                    <div class="aipw-field">
                        <label for="ai_emails_volume">Expected Email Volume (per month)</label>
                        <input type="number" name="ai_emails[volume]" id="ai_emails_volume" min="0">
                    </div>
                </div>

                <!-- AI Chat Configuration -->
                <div class="aipw-section aipw-product-config" id="ai_chat-config" style="display:none;">
                    <h2>AI Chat Configuration</h2>

                    <div class="aipw-field">
                        <label for="ai_chat_website">Website URL</label>
                        <input type="url" name="ai_chat[website]" id="ai_chat_website" placeholder="https://example.com">
                    </div>

                    <div class="aipw-field">
                        <label for="ai_chat_concurrent">Expected Concurrent Chats</label>
                        <input type="number" name="ai_chat[concurrent]" id="ai_chat_concurrent" min="1">
                    </div>
                </div>

                <!-- Addons Section -->
                <div class="aipw-section" id="addons-section">
                    <h2>Select Addons</h2>

                    <?php foreach ($this->addons as $addon_key => $addon_label): ?>
                        <div class="aipw-addon-option">
                            <label>
                                <input type="checkbox"
                                    name="addons[]"
                                    value="<?php echo esc_attr($addon_key); ?>"
                                    class="aipw-addon-checkbox">
                                <?php echo esc_html($addon_label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Additional Notes -->
                <div class="aipw-section">
                    <h2>Additional Notes</h2>
                    <div class="aipw-field">
                        <label for="additional_notes">Any special requirements or notes?</label>
                        <textarea name="additional_notes" id="additional_notes" rows="5"></textarea>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="aipw-section">
                    <button type="submit" id="aipw-submit-btn">Submit Order</button>
                    <div id="aipw-message" style="display:none;"></div>
                </div>
            </form>
        </div>

        <script>
            // config.js
            export const useCdn = false; // Set to false to switch to local files

            // module-importer.js
            import {
                useCdn
            } from './config.js';

            let libraryPath;
            if (useCdn) {
                libraryPath = 'https://cdn.jsdelivr.net/npm/libphonenumber-js@latest/bundle/libphonenumber-max.js';
            } else {
                // example: libraryPath = './node_modules/library/dist/library.min.js';
                libraryPath = './assets/js/libphonenumber-max.js';
            }

            // Dynamic import (for ES Modules)
            import(libraryPath)
                .then(module => {
                    // Use the imported module
                    console.log('Library loaded:', module);
                    window.libphonenumber = module;
                })
                .catch(error => {
                    console.error('Error loading library:', error);
                });
            (function() {
                // Add inline styles for enhanced phone validation display (matching C2AI dark theme)
                const style = document.createElement('style');
                style.textContent = `
                    .aipw-phone-validation {
                        margin-top: 8px;
                        padding: 10px 12px;
                        border-radius: 4px;
                        font-size: 14px;
                        line-height: 1.6;
                        transition: all 0.3s ease;
                        background: #2e3045;
                        border: 1px solid #545a82;
                    }
                    .aipw-phone-valid {
                        background: linear-gradient(135deg, rgba(0, 131, 135, 0.15), rgba(0, 53, 111, 0.1));
                        border: 1px solid #008387;
                        color: #ffffff;
                        box-shadow: 0 0 10px rgba(0, 131, 135, 0.2);
                    }
                    .aipw-phone-invalid {
                        background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(20, 20, 33, 0.1));
                        border: 1px solid #dc3545;
                        color: #ffffff;
                        box-shadow: 0 0 10px rgba(220, 53, 69, 0.2);
                    }
                    .aipw-phone-input {
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 15px;
                        padding: 10px 14px;
                        background: #2e3045;
                        color: #ffffff;
                        border: 2px solid #545a82;
                        border-radius: 4px;
                        transition: all 0.3s ease;
                        width: 100%;
                    }
                    .aipw-phone-input::placeholder {
                        color: #545a82;
                    }
                    .aipw-phone-input:focus {
                        outline: none;
                        background: #0a1330;
                        border-color: #0467d2;
                        box-shadow: 0 0 0 0.2rem rgba(4, 103, 210, 0.5);
                    }
                    .aipw-phone-input.valid {
                        border-color: #008387;
                        background: #0a1330;
                    }
                    .aipw-phone-input.invalid {
                        border-color: #dc3545;
                        background: rgba(220, 53, 69, 0.05);
                    }
                    .aipw-phone-validation small {
                        display: block;
                        margin-top: 4px;
                        opacity: 0.9;
                    }
                    .aipw-phone-validation span[style*="color: #28a745"] {
                        color: #008387 !important;
                    }
                    .aipw-phone-validation span[style*="color: #333"] {
                        color: #ffffff !important;
                    }
                    .aipw-phone-validation small[style*="color: #666"] {
                        color: #9ca3af !important;
                    }
                    .aipw-phone-validation small[style*="color: #999"] {
                        color: #545a82 !important;
                    }
                    .aipw-phone-validation span[style*="color: #dc3545"] {
                        color: #dc3545 !important;
                    }
                `;
                document.head.appendChild(style);

                // Web Storage Key
                const STORAGE_KEY = 'aipw_order_data';

                // Phone number management
                let phoneNumberIndex = 0;
                const phoneNumbers = [];


                // Load saved data on page load
                document.addEventListener('DOMContentLoaded', function() {
                    loadFormData();
                    initializeEventListeners();
                    initializePhoneNumbers();
                });

                // Initialize phone number fields
                function initializePhoneNumbers() {
                    // Add initial empty field
                    addPhoneNumberField();
                }

                // Add a phone number field
                function addPhoneNumberField(number = '', provider = '') {
                    const container = document.getElementById('port-numbers-container');
                    const index = phoneNumberIndex++;

                    const fieldDiv = document.createElement('div');
                    fieldDiv.className = 'aipw-phone-number-field';
                    fieldDiv.dataset.index = index;

                    fieldDiv.innerHTML = `
                    <div class="aipw-phone-row">
                        <div class="aipw-phone-input-wrapper">
                            <input type="tel" 
                                   class="aipw-phone-input" 
                                   data-index="${index}"
                                   placeholder="Enter phone number"
                                   value="${number}">
                            <input type="hidden" 
                                   name="ai_calls[port_numbers][${index}][number]" 
                                   class="aipw-phone-hidden"
                                   data-index="${index}">
                            <div class="aipw-phone-validation" data-index="${index}"></div>
                        </div>
                        <div class="aipw-provider-wrapper">
                            <input type="text" 
                                   name="ai_calls[port_numbers][${index}][provider]" 
                                   class="aipw-provider-input"
                                   placeholder="Current provider"
                                   value="${provider}">
                        </div>
                        <button type="button" class="aipw-remove-phone" data-index="${index}">×</button>
                    </div>
                `;

                    container.appendChild(fieldDiv);

                    // Add event listeners
                    const phoneInput = fieldDiv.querySelector('.aipw-phone-input');
                    const removeBtn = fieldDiv.querySelector('.aipw-remove-phone');

                    phoneInput.addEventListener('input', function() {
                        handlePhoneInput(index);
                    });

                    phoneInput.addEventListener('blur', function() {
                        validatePhoneNumber(index);
                    });

                    removeBtn.addEventListener('click', function() {
                        removePhoneNumberField(index);
                    });

                    phoneNumbers[index] = {
                        number: number,
                        provider: provider
                    };

                    return fieldDiv;
                }

                // Handle phone input changes
                function handlePhoneInput(index) {
                    const phoneInput = document.querySelector(`.aipw-phone-input[data-index="${index}"]`);
                    const value = phoneInput.value.trim();

                    phoneNumbers[index] = phoneNumbers[index] || {};
                    phoneNumbers[index].number = value;

                    // Auto-add new field if this is the last field and has content
                    const allFields = document.querySelectorAll('.aipw-phone-number-field');
                    const lastField = allFields[allFields.length - 1];
                    const isLastField = lastField.dataset.index == index;

                    if (isLastField && value.length > 0) {
                        addPhoneNumberField();
                    }

                    saveFormData();
                }

                // Validate phone number for any country with enhanced formatting
                function validatePhoneNumber(index) {
                    const phoneInput = document.querySelector(`.aipw-phone-input[data-index="${index}"]`);
                    const hiddenInput = document.querySelector(`.aipw-phone-hidden[data-index="${index}"]`);
                    const validationDiv = document.querySelector(`.aipw-phone-validation[data-index="${index}"]`);

                    const value = phoneInput.value.trim();

                    if (!value) {
                        // Empty is okay (field might not be used)
                        validationDiv.textContent = '';
                        validationDiv.className = 'aipw-phone-validation';
                        phoneInput.classList.remove('valid', 'invalid');
                        hiddenInput.value = '';
                        return true;
                    }

                    try {
                        // Try parsing without country code first (auto-detect)
                        const phoneNumber = libphonenumber.parsePhoneNumber(value);

                        if (phoneNumber.isValid()) {
                            // Convert to E.164 format for storage
                            const e164 = phoneNumber.format('E.164');
                            hiddenInput.value = e164;

                            // Format in national format for better readability
                            const nationalFormat = phoneNumber.formatNational();

                            // Get country name mapping
                            // Full ISO 3166-1 alpha-2 country name mapping
                            const countryNames = {
                                "AF": "Afghanistan",
                                "AX": "Åland Islands",
                                "AL": "Albania",
                                "DZ": "Algeria",
                                "AS": "American Samoa",
                                "AD": "Andorra",
                                "AO": "Angola",
                                "AI": "Anguilla",
                                "AQ": "Antarctica",
                                "AG": "Antigua and Barbuda",
                                "AR": "Argentina",
                                "AM": "Armenia",
                                "AW": "Aruba",
                                "AU": "Australia",
                                "AT": "Austria",
                                "AZ": "Azerbaijan",
                                "BS": "Bahamas",
                                "BH": "Bahrain",
                                "BD": "Bangladesh",
                                "BB": "Barbados",
                                "BY": "Belarus",
                                "BE": "Belgium",
                                "BZ": "Belize",
                                "BJ": "Benin",
                                "BM": "Bermuda",
                                "BT": "Bhutan",
                                "BO": "Bolivia",
                                "BQ": "Bonaire, Sint Eustatius and Saba",
                                "BA": "Bosnia and Herzegovina",
                                "BW": "Botswana",
                                "BV": "Bouvet Island",
                                "BR": "Brazil",
                                "IO": "British Indian Ocean Territory",
                                "BN": "Brunei Darussalam",
                                "BG": "Bulgaria",
                                "BF": "Burkina Faso",
                                "BI": "Burundi",
                                "CV": "Cabo Verde",
                                "KH": "Cambodia",
                                "CM": "Cameroon",
                                "CA": "Canada",
                                "KY": "Cayman Islands",
                                "CF": "Central African Republic",
                                "TD": "Chad",
                                "CL": "Chile",
                                "CN": "China",
                                "CX": "Christmas Island",
                                "CC": "Cocos (Keeling) Islands",
                                "CO": "Colombia",
                                "KM": "Comoros",
                                "CG": "Congo",
                                "CD": "Congo (Democratic Republic of the)",
                                "CK": "Cook Islands",
                                "CR": "Costa Rica",
                                "CI": "Côte d’Ivoire",
                                "HR": "Croatia",
                                "CU": "Cuba",
                                "CW": "Curaçao",
                                "CY": "Cyprus",
                                "CZ": "Czechia",
                                "DK": "Denmark",
                                "DJ": "Djibouti",
                                "DM": "Dominica",
                                "DO": "Dominican Republic",
                                "EC": "Ecuador",
                                "EG": "Egypt",
                                "SV": "El Salvador",
                                "GQ": "Equatorial Guinea",
                                "ER": "Eritrea",
                                "EE": "Estonia",
                                "SZ": "Eswatini",
                                "ET": "Ethiopia",
                                "FK": "Falkland Islands (Malvinas)",
                                "FO": "Faroe Islands",
                                "FJ": "Fiji",
                                "FI": "Finland",
                                "FR": "France",
                                "GF": "French Guiana",
                                "PF": "French Polynesia",
                                "TF": "French Southern Territories",
                                "GA": "Gabon",
                                "GM": "Gambia",
                                "GE": "Georgia",
                                "DE": "Germany",
                                "GH": "Ghana",
                                "GI": "Gibraltar",
                                "GR": "Greece",
                                "GL": "Greenland",
                                "GD": "Grenada",
                                "GP": "Guadeloupe",
                                "GU": "Guam",
                                "GT": "Guatemala",
                                "GG": "Guernsey",
                                "GN": "Guinea",
                                "GW": "Guinea-Bissau",
                                "GY": "Guyana",
                                "HT": "Haiti",
                                "HM": "Heard Island and McDonald Islands",
                                "VA": "Holy See",
                                "HN": "Honduras",
                                "HK": "Hong Kong",
                                "HU": "Hungary",
                                "IS": "Iceland",
                                "IN": "India",
                                "ID": "Indonesia",
                                "IR": "Iran",
                                "IQ": "Iraq",
                                "IE": "Ireland",
                                "IM": "Isle of Man",
                                "IL": "Israel",
                                "IT": "Italy",
                                "JM": "Jamaica",
                                "JP": "Japan",
                                "JE": "Jersey",
                                "JO": "Jordan",
                                "KZ": "Kazakhstan",
                                "KE": "Kenya",
                                "KI": "Kiribati",
                                "KP": "Korea (North)",
                                "KR": "Korea (South)",
                                "KW": "Kuwait",
                                "KG": "Kyrgyzstan",
                                "LA": "Lao People’s Democratic Republic",
                                "LV": "Latvia",
                                "LB": "Lebanon",
                                "LS": "Lesotho",
                                "LR": "Liberia",
                                "LY": "Libya",
                                "LI": "Liechtenstein",
                                "LT": "Lithuania",
                                "LU": "Luxembourg",
                                "MO": "Macao",
                                "MG": "Madagascar",
                                "MW": "Malawi",
                                "MY": "Malaysia",
                                "MV": "Maldives",
                                "ML": "Mali",
                                "MT": "Malta",
                                "MH": "Marshall Islands",
                                "MQ": "Martinique",
                                "MR": "Mauritania",
                                "MU": "Mauritius",
                                "YT": "Mayotte",
                                "MX": "Mexico",
                                "FM": "Micronesia (Federated States of)",
                                "MD": "Moldova",
                                "MC": "Monaco",
                                "MN": "Mongolia",
                                "ME": "Montenegro",
                                "MS": "Montserrat",
                                "MA": "Morocco",
                                "MZ": "Mozambique",
                                "MM": "Myanmar",
                                "NA": "Namibia",
                                "NR": "Nauru",
                                "NP": "Nepal",
                                "NL": "Netherlands",
                                "NC": "New Caledonia",
                                "NZ": "New Zealand",
                                "NI": "Nicaragua",
                                "NE": "Niger",
                                "NG": "Nigeria",
                                "NU": "Niue",
                                "NF": "Norfolk Island",
                                "MK": "North Macedonia",
                                "MP": "Northern Mariana Islands",
                                "NO": "Norway",
                                "OM": "Oman",
                                "PK": "Pakistan",
                                "PW": "Palau",
                                "PS": "Palestine, State of",
                                "PA": "Panama",
                                "PG": "Papua New Guinea",
                                "PY": "Paraguay",
                                "PE": "Peru",
                                "PH": "Philippines",
                                "PN": "Pitcairn",
                                "PL": "Poland",
                                "PT": "Portugal",
                                "PR": "Puerto Rico",
                                "QA": "Qatar",
                                "RE": "Réunion",
                                "RO": "Romania",
                                "RU": "Russian Federation",
                                "RW": "Rwanda",
                                "BL": "Saint Barthélemy",
                                "SH": "Saint Helena, Ascension and Tristan da Cunha",
                                "KN": "Saint Kitts and Nevis",
                                "LC": "Saint Lucia",
                                "MF": "Saint Martin (French part)",
                                "PM": "Saint Pierre and Miquelon",
                                "VC": "Saint Vincent and the Grenadines",
                                "WS": "Samoa",
                                "SM": "San Marino",
                                "ST": "Sao Tome and Principe",
                                "SA": "Saudi Arabia",
                                "SN": "Senegal",
                                "RS": "Serbia",
                                "SC": "Seychelles",
                                "SL": "Sierra Leone",
                                "SG": "Singapore",
                                "SX": "Sint Maarten (Dutch part)",
                                "SK": "Slovakia",
                                "SI": "Slovenia",
                                "SB": "Solomon Islands",
                                "SO": "Somalia",
                                "ZA": "South Africa",
                                "GS": "South Georgia and the South Sandwich Islands",
                                "SS": "South Sudan",
                                "ES": "Spain",
                                "LK": "Sri Lanka",
                                "SD": "Sudan",
                                "SR": "Suriname",
                                "SJ": "Svalbard and Jan Mayen",
                                "SE": "Sweden",
                                "CH": "Switzerland",
                                "SY": "Syrian Arab Republic",
                                "TW": "Taiwan",
                                "TJ": "Tajikistan",
                                "TZ": "Tanzania, United Republic of",
                                "TH": "Thailand",
                                "TL": "Timor-Leste",
                                "TG": "Togo",
                                "TK": "Tokelau",
                                "TO": "Tonga",
                                "TT": "Trinidad and Tobago",
                                "TN": "Tunisia",
                                "TR": "Türkiye",
                                "TM": "Turkmenistan",
                                "TC": "Turks and Caicos Islands",
                                "TV": "Tuvalu",
                                "UG": "Uganda",
                                "UA": "Ukraine",
                                "AE": "United Arab Emirates",
                                "GB": "United Kingdom",
                                "US": "United States",
                                "UM": "United States Minor Outlying Islands",
                                "UY": "Uruguay",
                                "UZ": "Uzbekistan",
                                "VU": "Vanuatu",
                                "VE": "Venezuela",
                                "VN": "Viet Nam",
                                "VG": "Virgin Islands (British)",
                                "VI": "Virgin Islands (U.S.)",
                                "WF": "Wallis and Futuna",
                                "EH": "Western Sahara",
                                "YE": "Yemen",
                                "ZM": "Zambia",
                                "ZW": "Zimbabwe"
                            };


                            const countryName = countryNames[phoneNumber.country] || phoneNumber.country;

                            // Display formatted validation with both formats
                            validationDiv.innerHTML = `
                                <span style="color: #28a745; font-weight: bold;">✓</span>
                                <span style="color: #333;">${countryName} (${phoneNumber.country})</span><br>
                                <small style="color: #666;">National: ${nationalFormat}</small><br>
                                <small style="color: #999;">E.164: ${e164}</small>
                            `;
                            validationDiv.className = 'aipw-phone-validation aipw-phone-valid';

                            // Add visual feedback to input field
                            phoneInput.classList.remove('invalid');
                            phoneInput.classList.add('valid');

                            // Store validation data
                            phoneNumbers[index] = phoneNumbers[index] || {};
                            phoneNumbers[index].number = e164;
                            phoneNumbers[index].country = phoneNumber.country;
                            phoneNumbers[index].nationalFormat = nationalFormat;

                            return true;
                        } else {
                            throw new Error('Invalid phone number');
                        }
                    } catch (error) {
                        // Provide helpful error message
                        let errorMessage = '✗ Invalid phone number format';
                        if (!value.startsWith('+') && value.length > 0) {
                            errorMessage += '<br><small>Tip: Include country code (e.g., +1 for US/Canada)</small>';
                        }
                        validationDiv.innerHTML = `<span style="color: #dc3545;">${errorMessage}</span>`;
                        validationDiv.className = 'aipw-phone-validation aipw-phone-invalid';

                        // Add visual feedback to input field
                        phoneInput.classList.remove('valid');
                        phoneInput.classList.add('invalid');

                        hiddenInput.value = '';
                        return false;
                    }
                }

                // Remove phone number field
                function removePhoneNumberField(index) {
                    const field = document.querySelector(`.aipw-phone-number-field[data-index="${index}"]`);
                    if (field) {
                        field.remove();
                        delete phoneNumbers[index];

                        // Ensure at least one field exists
                        const remainingFields = document.querySelectorAll('.aipw-phone-number-field');
                        if (remainingFields.length === 0) {
                            addPhoneNumberField();
                        }

                        saveFormData();
                    }
                }

                // Validate all phone numbers before submission
                function validateAllPhoneNumbers() {
                    const allFields = document.querySelectorAll('.aipw-phone-input');
                    let isValid = true;
                    let hasAtLeastOne = false;

                    allFields.forEach(function(input) {
                        const index = input.dataset.index;
                        const value = input.value.trim();

                        if (value) {
                            hasAtLeastOne = true;
                            if (!validatePhoneNumber(index)) {
                                isValid = false;
                            }
                        }
                    });

                    const errorDiv = document.getElementById('port-numbers-error');

                    // Check if porting is selected
                    const portingYes = document.querySelector('input[name="ai_calls[porting]"][value="yes"]');
                    if (portingYes && portingYes.checked) {
                        if (!hasAtLeastOne) {
                            errorDiv.textContent = 'Please add at least one phone number to port.';
                            errorDiv.style.display = 'block';
                            return false;
                        }
                    }

                    if (!isValid) {
                        errorDiv.textContent = 'Please fix invalid phone numbers before submitting.';
                        errorDiv.style.display = 'block';
                        return false;
                    }

                    errorDiv.style.display = 'none';
                    return true;
                }

                // Initialize all event listeners
                function initializeEventListeners() {
                    // Product checkbox toggles
                    document.querySelectorAll('.aipw-product-checkbox').forEach(function(checkbox) {
                        checkbox.addEventListener('change', function() {
                            toggleProductConfig(this.dataset.product, this.checked);
                            saveFormData();
                        });
                    });

                    // Porting radio toggles
                    document.querySelectorAll('.aipw-porting-toggle').forEach(function(radio) {
                        radio.addEventListener('change', function() {
                            togglePortingOptions(this.value);
                            saveFormData();
                        });
                    });

                    // Add phone number button
                    const addPhoneBtn = document.getElementById('add-port-number-btn');
                    if (addPhoneBtn) {
                        addPhoneBtn.addEventListener('click', function() {
                            addPhoneNumberField();
                        });
                    }

                    // Save data on any form change
                    document.getElementById('aipw-order-form').addEventListener('change', saveFormData);
                    document.getElementById('aipw-order-form').addEventListener('input', saveFormData);

                    // Form submission
                    document.getElementById('aipw-order-form').addEventListener('submit', handleFormSubmit);
                }

                // Toggle product configuration sections
                function toggleProductConfig(product, show) {
                    const configSection = document.getElementById(product + '-config');
                    if (configSection) {
                        configSection.style.display = show ? 'block' : 'none';
                    }
                }

                // Toggle porting options
                function togglePortingOptions(value) {
                    document.getElementById('porting-yes-options').style.display = value === 'yes' ? 'block' : 'none';
                    document.getElementById('porting-no-options').style.display = value === 'no' ? 'block' : 'none';
                }

                // Save form data to localStorage
                function saveFormData() {
                    const formData = {};
                    const form = document.getElementById('aipw-order-form');
                    const formElements = form.elements;

                    for (let i = 0; i < formElements.length; i++) {
                        const element = formElements[i];

                        if (!element.name || element.name === 'action' || element.name === 'aipw_nonce') {
                            continue;
                        }

                        // Skip phone hidden inputs (we'll save the visible inputs)
                        if (element.classList.contains('aipw-phone-hidden')) {
                            continue;
                        }

                        if (element.type === 'checkbox') {
                            if (element.name.includes('[]')) {
                                // Handle checkbox arrays
                                const arrayName = element.name.replace('[]', '');
                                if (!formData[arrayName]) {
                                    formData[arrayName] = [];
                                }
                                if (element.checked) {
                                    formData[arrayName].push(element.value);
                                }
                            } else {
                                formData[element.name] = element.checked;
                            }
                        } else if (element.type === 'radio') {
                            if (element.checked) {
                                formData[element.name] = element.value;
                            }
                        } else {
                            formData[element.name] = element.value;
                        }
                    }

                    // Save phone numbers separately
                    formData['port_numbers'] = [];
                    document.querySelectorAll('.aipw-phone-number-field').forEach(function(field) {
                        const index = field.dataset.index;
                        const phoneInput = field.querySelector('.aipw-phone-input');
                        const providerInput = field.querySelector('.aipw-provider-input');

                        if (phoneInput.value.trim() || providerInput.value.trim()) {
                            formData['port_numbers'].push({
                                number: phoneInput.value.trim(),
                                provider: providerInput.value.trim()
                            });
                        }
                    });

                    try {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
                    } catch (e) {
                        console.error('Error saving to localStorage:', e);
                    }
                }

                // Load form data from localStorage
                function loadFormData() {
                    try {
                        const savedData = localStorage.getItem(STORAGE_KEY);
                        if (!savedData) return;

                        const formData = JSON.parse(savedData);
                        const form = document.getElementById('aipw-order-form');

                        Object.keys(formData).forEach(function(key) {
                            const value = formData[key];

                            // Handle phone numbers separately
                            if (key === 'port_numbers') {
                                return;
                            }

                            // Handle arrays (checkboxes)
                            if (Array.isArray(value)) {
                                value.forEach(function(val) {
                                    const checkbox = form.querySelector(`input[name="${key}[]"][value="${val}"]`);
                                    if (checkbox) {
                                        checkbox.checked = true;
                                        // Trigger change for product checkboxes
                                        if (checkbox.classList.contains('aipw-product-checkbox')) {
                                            toggleProductConfig(checkbox.dataset.product, true);
                                        }
                                    }
                                });
                            } else {
                                const element = form.elements[key];
                                if (element) {
                                    if (element.type === 'checkbox') {
                                        element.checked = value;
                                    } else if (element.type === 'radio') {
                                        const radio = form.querySelector(`input[name="${key}"][value="${value}"]`);
                                        if (radio) {
                                            radio.checked = true;
                                            // Trigger change for porting radios
                                            if (radio.classList.contains('aipw-porting-toggle')) {
                                                togglePortingOptions(value);
                                            }
                                        }
                                    } else {
                                        element.value = value;
                                    }
                                }
                            }
                        });

                        // Load saved phone numbers
                        if (formData['port_numbers'] && formData['port_numbers'].length > 0) {
                            // Clear existing fields first
                            document.getElementById('port-numbers-container').innerHTML = '';
                            phoneNumberIndex = 0;

                            formData['port_numbers'].forEach(function(phone) {
                                addPhoneNumberField(phone.number, phone.provider);
                            });

                            // Add one empty field at the end
                            addPhoneNumberField();
                        }

                    } catch (e) {
                        console.error('Error loading from localStorage:', e);
                    }
                }

                // Handle form submission
                function handleFormSubmit(e) {
                    e.preventDefault();

                    // Validate phone numbers if porting is selected
                    const portingYes = document.querySelector('input[name="ai_calls[porting]"][value="yes"]');
                    if (portingYes && portingYes.checked) {
                        if (!validateAllPhoneNumbers()) {
                            return;
                        }
                    }

                    const form = e.target;
                    const submitBtn = document.getElementById('aipw-submit-btn');
                    const messageDiv = document.getElementById('aipw-message');

                    // Disable submit button
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Processing...';

                    // Prepare form data
                    const formData = new FormData(form);

                    // Submit via AJAX
                    fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                messageDiv.textContent = data.data.message || 'Order submitted successfully!';
                                messageDiv.style.color = 'green';
                                messageDiv.style.display = 'block';

                                // Clear localStorage on success
                                localStorage.removeItem(STORAGE_KEY);

                                // Reset form
                                form.reset();

                                // Hide all product configs
                                document.querySelectorAll('.aipw-product-config').forEach(function(el) {
                                    el.style.display = 'none';
                                });

                                // Provide PDF download link if available
                                if (data.data.pdf_url) {
                                    const pdfLink = document.createElement('a');
                                    pdfLink.href = data.data.pdf_url;
                                    pdfLink.textContent = 'Download PDF Receipt';
                                    pdfLink.style.display = 'block';
                                    pdfLink.style.marginTop = '10px';
                                    messageDiv.appendChild(pdfLink);
                                }

                            } else {
                                messageDiv.textContent = data.data.message || 'Error submitting order. Please try again.';
                                messageDiv.style.color = 'red';
                                messageDiv.style.display = 'block';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            messageDiv.textContent = 'Network error. Please try again.';
                            messageDiv.style.color = 'red';
                            messageDiv.style.display = 'block';
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Submit Order';
                        });
                }
            })();
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Handle order submission
     */
    public function handle_order_submission()
    {
        // Verify nonce
        if (!isset($_POST['aipw_nonce']) || !wp_verify_nonce($_POST['aipw_nonce'], 'aipw_order_submit')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'username', 'password'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(['message' => "Field '{$field}' is required"]);
                return;
            }
        }

        // Sanitize user data
        $user_data = [
            'username' => sanitize_text_field($_POST['username']),
            'email' => sanitize_email($_POST['email']),
            'password' => $_POST['password'], // Don't sanitize password
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'role' => 'user',
            'status' => 'active',
            'account_status' => 'active'
        ];

        // Create user via API
        $user_result = $this->create_user($user_data);

        if (!$user_result['success']) {
            wp_send_json_error(['message' => 'Failed to create user: ' . $user_result['message']]);
            return;
        }

        $user_id = $user_result['user_id'];

        // Process and validate phone numbers
        $port_numbers = [];
        $validation_errors = [];

        if (isset($_POST['ai_calls']['port_numbers']) && is_array($_POST['ai_calls']['port_numbers'])) {
            foreach ($_POST['ai_calls']['port_numbers'] as $index => $phone_data) {
                // Only process if number is not empty
                if (!empty($phone_data['number'])) {
                    $phone_number = sanitize_text_field($phone_data['number']);

                    // Validate phone number on server-side
                    $validation = $this->validate_phone_number_server($phone_number);

                    if (!$validation['valid']) {
                        $validation_errors[] = sprintf(
                            'Phone number #%d is invalid: %s (provided: %s)',
                            $index + 1,
                            $validation['error'],
                            $phone_number
                        );
                        error_log('AIPW Phone Validation Error: ' . $validation['error'] . ' for number: ' . $phone_number);
                    } else {
                        // Store the validated E.164 format
                        $port_numbers[] = [
                            'number' => $validation['e164'], // Validated and converted to E.164
                            'provider' => sanitize_text_field($phone_data['provider'] ?? ''),
                            'country' => $validation['country']
                        ];
                    }
                }
            }
        }

        // If there are validation errors, return them to the user
        if (!empty($validation_errors)) {
            wp_send_json_error([
                'message' => 'Phone number validation failed',
                'errors' => $validation_errors
            ]);
        }

        // Collect order data
        $order_data = [
            'user_id' => $user_id,
            'products' => isset($_POST['products']) ? $_POST['products'] : [],
            'addons' => isset($_POST['addons']) ? $_POST['addons'] : [],
            'ai_calls' => isset($_POST['ai_calls']) ? $_POST['ai_calls'] : [],
            'ai_emails' => isset($_POST['ai_emails']) ? $_POST['ai_emails'] : [],
            'ai_chat' => isset($_POST['ai_chat']) ? $_POST['ai_chat'] : [],
            'additional_notes' => sanitize_textarea_field($_POST['additional_notes'] ?? ''),
            'order_date' => current_time('mysql')
        ];

        // Replace port_numbers in order data with sanitized version
        if (!empty($port_numbers)) {
            $order_data['ai_calls']['port_numbers'] = $port_numbers;
        }

        // Generate PDF
        $pdf_path = $this->generate_order_pdf($order_data, $user_data);

        // Store order in database (you can implement this)
        // $this->save_order($order_data);

        wp_send_json_success([
            'message' => 'Order submitted successfully!',
            'user_id' => $user_id,
            'pdf_url' => $pdf_path ? WP_CONTENT_URL . '/uploads/ai-orders/' . basename($pdf_path) : null
        ]);
    }

    /**
     * Create user via API
     */
    private function create_user($user_data)
    {
        $response = wp_remote_post(self::API_CREATE_USER, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($user_data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return [
                'success' => false,
                'message' => $body['message'] ?? 'Unknown error'
            ];
        }

        return [
            'success' => true,
            'user_id' => $body['user_id'] ?? $body['id'] ?? null
        ];
    }

    /**
     * Validate phone number on server-side and convert to E.164 format
     *
     * @param string $phone_number The phone number to validate
     * @param string $default_country Optional default country code (ISO 3166-1 alpha-2)
     * @return array ['valid' => bool, 'e164' => string|null, 'country' => string|null, 'error' => string|null]
     */
    private function validate_phone_number_server($phone_number, $default_country = null)
    {
        // Return valid for empty numbers (optional fields)
        if (empty($phone_number)) {
            return [
                'valid' => true,
                'e164' => null,
                'country' => null,
                'error' => null
            ];
        }

        try {
            // Use giggsey/libphonenumber-for-php library
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();

            // Try to parse the phone number
            // If default country is provided, use it; otherwise try to auto-detect
            if ($default_country) {
                $numberProto = $phoneUtil->parse($phone_number, $default_country);
            } else {
                // If number starts with +, parse without country
                if (substr($phone_number, 0, 1) === '+') {
                    $numberProto = $phoneUtil->parse($phone_number, null);
                } else {
                    // Default to US if no country code provided
                    $numberProto = $phoneUtil->parse($phone_number, 'US');
                }
            }

            // Check if the number is valid
            if (!$phoneUtil->isValidNumber($numberProto)) {
                return [
                    'valid' => false,
                    'e164' => null,
                    'country' => null,
                    'error' => 'Invalid phone number format'
                ];
            }

            // Get E.164 format
            $e164 = $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);

            // Get country code
            $country = $phoneUtil->getRegionCodeForNumber($numberProto);

            return [
                'valid' => true,
                'e164' => $e164,
                'country' => $country,
                'error' => null
            ];
        } catch (\libphonenumber\NumberParseException $e) {
            return [
                'valid' => false,
                'e164' => null,
                'country' => null,
                'error' => 'Could not parse phone number: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'e164' => null,
                'country' => null,
                'error' => 'Validation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate PDF for order
     */
    private function generate_order_pdf($order_data, $user_data)
    {
        //require_once AIPW_PLUGIN_DIR . 'vendor/autoload.php';

        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/ai-orders';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        // Generate HTML content
        $html = $this->generate_pdf_html($order_data, $user_data);

        // Initialize dompdf
        /** @var \Dompdf\Options $options */
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF
        $filename = 'order_' . $order_data['user_id'] . '_' . time() . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }

    /**
     * Generate HTML content for PDF
     */
    private function generate_pdf_html($order_data, $user_data)
    {
        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="utf-8">
            <title>Order Receipt</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                }

                h1 {
                    color: #333;
                    font-size: 24px;
                }

                h2 {
                    color: #666;
                    font-size: 18px;
                    margin-top: 20px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                }

                th,
                td {
                    padding: 8px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }

                th {
                    background-color: #f4f4f4;
                }

                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .section {
                    margin: 20px 0;
                }
            </style>
        </head>

        <body>
            <div class="header">
                <h1>AI Products Order Receipt</h1>
                <p>Order Date: <?php echo date('F j, Y g:i A'); ?></p>
            </div>

            <div class="section">
                <h2>Customer Information</h2>
                <table>
                    <tr>
                        <th>Name</th>
                        <td><?php echo esc_html($user_data['first_name'] . ' ' . $user_data['last_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo esc_html($user_data['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Username</th>
                        <td><?php echo esc_html($user_data['username']); ?></td>
                    </tr>
                    <tr>
                        <th>User ID</th>
                        <td><?php echo esc_html($order_data['user_id']); ?></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h2>Products Ordered</h2>
                <ul>
                    <?php foreach ($order_data['products'] as $product): ?>
                        <li><strong><?php echo esc_html($this->products[$product]['name']); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!empty($order_data['ai_calls'])): ?>
                <div class="section">
                    <h2>AI Calls Configuration</h2>
                    <table>
                        <?php foreach ($order_data['ai_calls'] as $key => $value): ?>
                            <?php if (!empty($value)): ?>
                                <tr>
                                    <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                    <td><?php echo esc_html(is_array($value) ? implode(', ', $value) : $value); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </table>

                    <?php if (!empty($order_data['ai_calls']['port_numbers'])): ?>
                        <h3>Phone Numbers to Port</h3>
                        <table>
                            <tr>
                                <th>Phone Number</th>
                                <th>Current Provider</th>
                            </tr>
                            <?php foreach ($order_data['ai_calls']['port_numbers'] as $phone): ?>
                                <tr>
                                    <td><?php echo esc_html($phone['number']); ?></td>
                                    <td><?php echo esc_html($phone['provider']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <?php if (!empty($order_data['addons'])): ?>
                <div class="section">
                    <h2>Addons Selected</h2>
                    <ul>
                        <?php foreach ($order_data['addons'] as $addon): ?>
                            <li><?php echo esc_html($this->addons[$addon]); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($order_data['additional_notes'])): ?>
                <div class="section">
                    <h2>Additional Notes</h2>
                    <p><?php echo nl2br(esc_html($order_data['additional_notes'])); ?></p>
                </div>
            <?php endif; ?>
        </body>

        </html>
    <?php
        return ob_get_clean();
    }

    private function generate_loa_pdf($order_data, $user_data)
    {
        require_once AIPW_PLUGIN_DIR . 'vendor/autoload.php';

        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/ai-orders';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        // Generate HTML content for LOA
        $html = $this->generate_loa_html($order_data, $user_data);

        // Initialize dompdf
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF
        $filename = 'LOA_' . $order_data['user_id'] . '_' . time() . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }
    /**
     * Generate HTML for LOA PDF
     */
    private function generate_loa_html($order_data, $user_data)
    {
        // Extract phone numbers from order data
        $phone_numbers = [];

        // Get phone numbers and service provider from AI Calls config
        if (!empty($order_data['ai_calls'])) {
            $ai_calls = $order_data['ai_calls'];

            // If porting, get the numbers being ported
            if (!empty($ai_calls['porting']) && $ai_calls['porting'] === 'yes') {
                // Check if we have specific phone numbers listed
                if (!empty($ai_calls['port_numbers'])) {
                    $phone_numbers = is_array($ai_calls['port_numbers']) ? $ai_calls['port_numbers'] : [$ai_calls['port_numbers']];
                }

                // Get count if specified
                $port_count = !empty($ai_calls['port_numbers_count']) ? intval($ai_calls['port_numbers_count']) : count($phone_numbers);

                // Ensure we have at least the specified count of slots
                if ($port_count > count($phone_numbers)) {
                    for ($i = count($phone_numbers); $i < $port_count; $i++) {
                        $phone_numbers[] = ['number' => '', 'provider' => ''];
                    }
                }
            }

            // Get service provider if specified
            $service_provider = !empty($ai_calls['current_provider']) ? $ai_calls['current_provider'] : '';
        }

        // Ensure we have at least 4 rows
        while (count($phone_numbers) < 1) {
            $phone_numbers[] = ['number' => '', 'provider' => ''];
        }

        // Get service address from order data or user data
        $service_address = [
            'address' => !empty($order_data['service_address']) ? $order_data['service_address'] : '',
            'city' => !empty($order_data['service_city']) ? $order_data['service_city'] : '',
            'state' => !empty($order_data['service_state']) ? $order_data['service_state'] : '',
            'zip' => !empty($order_data['service_zip']) ? $order_data['service_zip'] : ''
        ];

        // Get business name if provided
        $business_name = !empty($user_data['business_name']) ? $user_data['business_name'] : '';

        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="utf-8">
            <title>Porting Letter of Authorization (LOA)</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: Arial, sans-serif;
                    font-size: 11pt;
                    line-height: 1.4;
                    padding: 40px 50px;
                    color: #000;
                }

                .logo-container {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .logo-text {
                    font-size: 24pt;
                    font-weight: bold;
                    color: #00A3E0;
                    letter-spacing: 2px;
                }

                .logo-tagline {
                    font-size: 8pt;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-top: 5px;
                }

                .divider {
                    border-top: 3px solid #000;
                    margin: 20px 0;
                }

                h1 {
                    text-align: center;
                    font-size: 16pt;
                    font-weight: bold;
                    letter-spacing: 3px;
                    margin: 30px 0;
                    text-transform: uppercase;
                }

                .section {
                    margin-bottom: 25px;
                }

                .section-label {
                    font-size: 10pt;
                    font-weight: bold;
                    margin-bottom: 8px;
                }

                .field-label {
                    font-size: 9pt;
                    margin-bottom: 3px;
                    color: #333;
                }

                .field-container {
                    margin-bottom: 15px;
                }

                .input-box {
                    border: 1px solid #000;
                    padding: 8px;
                    min-height: 30px;
                    background: #fff;
                }

                .input-row {
                    display: table;
                    width: 100%;
                    margin-bottom: 15px;
                }

                .input-col {
                    display: table-cell;
                    padding-right: 10px;
                }

                .input-col:last-child {
                    padding-right: 0;
                }

                .input-col-33 {
                    width: 33.33%;
                }

                .input-col-50 {
                    width: 50%;
                }

                .phone-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }

                .phone-table th {
                    font-size: 9pt;
                    text-align: left;
                    padding-bottom: 5px;
                }

                .phone-table td {
                    border: 1px solid #000;
                    padding: 8px;
                    height: 35px;
                }

                .note {
                    font-size: 8pt;
                    font-style: italic;
                    margin-top: 5px;
                    color: #666;
                }

                .authorization-text {
                    font-size: 9pt;
                    line-height: 1.5;
                    margin: 20px 0;
                    text-align: justify;
                }

                .signature-section {
                    margin-top: 30px;
                }

                .signature-row {
                    display: table;
                    width: 100%;
                }

                .signature-col {
                    display: table-cell;
                    width: 33.33%;
                    padding-right: 10px;
                }

                .signature-col:last-child {
                    padding-right: 0;
                }

                .signature-line {
                    border-bottom: 1px solid #000;
                    min-height: 35px;
                    margin-bottom: 5px;
                }

                .signature-label {
                    font-size: 9pt;
                    color: #333;
                }

                .footer-note {
                    margin-top: 30px;
                    padding: 15px;
                    background: #f5f5f5;
                    border: 1px solid #ccc;
                    font-size: 9pt;
                    font-weight: bold;
                }
            </style>
        </head>

        <body>
            <!-- Logo/Header -->
            <div class="logo-container">
                <div class="logo-text">CUSTOMER2AI</div>
                <div class="logo-tagline">AI-DRIVEN. HUMAN-FOCUSED.</div>
            </div>

            <div class="divider"></div>

            <!-- Title -->
            <h1>PORTING LETTER OF AUTHORIZATION (LOA)</h1>

            <!-- Section 1: Customer Name -->
            <div class="section">
                <div class="section-label">1. Customer Name (your name should appear exactly as it does on your telephone bill):</div>

                <div class="input-row">
                    <div class="input-col input-col-50">
                        <div class="field-label">First Name</div>
                        <div class="input-box"><?php echo esc_html($user_data['first_name']); ?></div>
                    </div>
                    <div class="input-col input-col-50">
                        <div class="field-label">Last Name</div>
                        <div class="input-box"><?php echo esc_html($user_data['last_name']); ?></div>
                    </div>
                </div>

                <div class="field-container">
                    <div class="field-label">Business Name <span style="font-style: italic;">(if the service is in your company's name)</span></div>
                    <div class="input-box"><?php echo esc_html($business_name); ?></div>
                </div>
            </div>

            <!-- Section 2: Service Address -->
            <div class="section">
                <div class="section-label">2. Service Address on file with your current carrier</div>
                <div class="note">(Please note, this must be a physical location and cannot be a PO Box):</div>

                <div class="field-container">
                    <div class="field-label">Address</div>
                    <div class="input-box"><?php echo esc_html($service_address['address']); ?></div>
                </div>

                <div class="input-row">
                    <div class="input-col input-col-33">
                        <div class="field-label">City</div>
                        <div class="input-box"><?php echo esc_html($service_address['city']); ?></div>
                    </div>
                    <div class="input-col input-col-33">
                        <div class="field-label">State</div>
                        <div class="input-box"><?php echo esc_html($service_address['state']); ?></div>
                    </div>
                    <div class="input-col input-col-33">
                        <div class="field-label">Zip/Postal Code</div>
                        <div class="input-box"><?php echo esc_html($service_address['zip']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Phone Numbers -->
            <div class="section">
                <div class="section-label">3. List all the Telephone Number(s) which you authorize to change from your current phone service provider to the Company or its designated agent.</div>

                <table class="phone-table">
                    <tr>
                        <th style="width: 50%;">Phone Number*</th>
                        <th style="width: 50%;">Service Provider</th>
                    </tr>
                    <?php foreach ($phone_numbers as $phone): ?>
                        <tr>
                            <td><?php echo esc_html(is_array($phone) ? ($phone['number'] ?? '') : $phone); ?></td>
                            <td><?php echo esc_html(is_array($phone) ? ($phone['provider'] ?? $service_provider) : $service_provider); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <div class="note" style="margin-top: 10px;">*If you have more than 4 numbers, please list on an extra page.</div>
            </div>

            <!-- Authorization Text -->
            <div class="authorization-text">
                By signing the below, I verify that I am, or represent (for a business), the above-named service customer,
                authorized to change the primary carrier(s) for the telephone number(s) listed, and am at least 18 years of age.
                The name and address I have provided is the name and address on record with my local telephone company
                for each telephone number listed. I authorize <strong>Customer2AI</strong> (the "Company") or its
                designated agent to act on my behalf and notify my current carrier(s) to change my preferred carrier(s) for the
                listed number(s) and service(s), to obtain any information the Company deems necessary to make the carrier
                change(s), including, for example, an inventory of telephone lines billed to the telephone number(s), carrier or
                customer identifying information, billing addresses, and my credit history.
            </div>

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-row">
                    <div class="signature-col">
                        <div class="signature-line"></div>
                        <div class="signature-label">Authorized Signature</div>
                    </div>
                    <div class="signature-col">
                        <div class="signature-line"></div>
                        <div class="signature-label">Print</div>
                    </div>
                    <div class="signature-col">
                        <div class="signature-line"></div>
                        <div class="signature-label">Date</div>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="footer-note">
                For toll free numbers, please change RespOrg to TWI01. Please do not end service on the
                number for 10 days after RespOrg change.
            </div>
        </body>

        </html>
<?php
        return ob_get_clean(); // Copy from loa-pdf-generation.php
    }

    /**
     * Enqueue plugin styles and scripts
     */
    public function enqueue_assets()
    {
        // Only load on pages with the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ai_products_widget')) {

            $css_file = AIPW_PLUGIN_DIR . 'assets/css/widget-styles.css';
            if (file_exists($css_file)) {  // ← Checks if file exists
                wp_enqueue_style(
                    'aipw-widget-styles',
                    AIPW_PLUGIN_URL . 'assets/css/widget-styles.css',
                    [],
                    filemtime($css_file)  // ← Auto cache busting
                );
            }

            // Enqueue CSS
            /*wp_enqueue_style(
                'aipw-widget-styles',
                AIPW_PLUGIN_URL . 'assets/css/widget-styles.css',
                [],
                AIPW_VERSION
            );*/

            // Enqueue JavaScript (if needed for interactions)
            wp_enqueue_script(
                'aipw-widget-scripts',
                AIPW_PLUGIN_URL . 'assets/js/widget-scripts.js',
                ['jquery'], // dependencies
                AIPW_VERSION,
                true // load in footer
            );

            // Pass data to JavaScript
            wp_localize_script('aipw-widget-scripts', 'aipwData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aipw_ajax_nonce')
            ]);
        }
    }
}


// Initialize the plugin
function aipw_init()
{
    return AI_Products_Order_Widget::get_instance();
}
add_action('plugins_loaded', 'aipw_init');
