<?php
/**
 * Configuration File for AI Products Order Widget
 * 
 * Customize your products, addons, pricing, and settings here
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    
    /**
     * Plugin Settings
     */
    'settings' => [
        'plugin_name' => 'AI Products Order Widget',
        'version' => '1.0.0',
        'text_domain' => 'ai-products-widget',
        'pdf_storage_dir' => 'ai-orders',
        'enable_debug' => false,
        'ajax_timeout' => 30,
    ],
    
    /**
     * API Endpoints
     */
    'api' => [
        'select_endpoint' => 'https://n8n.workflows.organizedchaos.cc/webhook/da176ae9-496c-4f08-baf5-6a78a6a42adb',
        'create_user_endpoint' => 'https://n8n.workflows.organizedchaos.cc/webhook/users/create',
        'dynamic_create_endpoint' => 'https://n8n.workflows.organizedchaos.cc/webhook/dynamic-create',
        'timeout' => 30,
        'verify_ssl' => true,
    ],
    
    /**
     * Products Configuration
     */
    'products' => [
        'ai_calls' => [
            'name' => 'AI Calls (Phone)',
            'slug' => 'ai_calls',
            'description' => 'Intelligent phone call handling with AI agents',
            'has_phone_setup' => true,
            'base_price' => 99.00,
            'enabled' => true,
            'icon' => '📞',
            'features' => [
                'Intelligent call routing',
                'Natural language processing',
                'Call transcription',
                'Real-time analytics'
            ]
        ],
        'ai_emails' => [
            'name' => 'AI Emails',
            'slug' => 'ai_emails',
            'description' => 'Automated email responses powered by AI',
            'has_phone_setup' => false,
            'base_price' => 79.00,
            'enabled' => true,
            'icon' => '📧',
            'features' => [
                'Smart email composition',
                'Sentiment analysis',
                'Priority detection',
                'Automated responses'
            ]
        ],
        'ai_chat' => [
            'name' => 'AI Chat',
            'slug' => 'ai_chat',
            'description' => 'Live chat with AI-powered assistance',
            'has_phone_setup' => false,
            'base_price' => 89.00,
            'enabled' => true,
            'icon' => '💬',
            'features' => [
                'Real-time chat support',
                'Multi-language support',
                'Context-aware responses',
                'Seamless handoff to humans'
            ]
        ]
    ],
    
    /**
     * Addons Configuration
     */
    'addons' => [
        'qa' => [
            'name' => 'QA',
            'description' => 'Quality assurance and call monitoring',
            'price' => 29.00,
            'enabled' => true,
            'icon' => '✓',
            'applicable_to' => ['ai_calls', 'ai_emails', 'ai_chat']
        ],
        'avs_match' => [
            'name' => 'AVS Match',
            'description' => 'Address Verification System matching',
            'price' => 19.00,
            'enabled' => true,
            'icon' => '📍',
            'applicable_to' => ['ai_calls', 'ai_emails']
        ],
        'custom_package' => [
            'name' => 'Custom Package',
            'description' => 'Tailored solution for specific needs',
            'price' => 0.00, // Custom pricing
            'enabled' => true,
            'icon' => '🎁',
            'applicable_to' => ['ai_calls', 'ai_emails', 'ai_chat']
        ],
        'phone_numbers' => [
            'name' => 'Phone Numbers',
            'description' => 'Additional phone number allocation',
            'price' => 15.00, // Per number
            'enabled' => true,
            'icon' => '📱',
            'applicable_to' => ['ai_calls']
        ],
        'lead_verification' => [
            'name' => 'Lead Verification',
            'description' => 'Automated lead validation and scoring',
            'price' => 39.00,
            'enabled' => true,
            'icon' => '🔍',
            'applicable_to' => ['ai_calls', 'ai_emails', 'ai_chat']
        ],
        'transcription_recordings' => [
            'name' => 'Transcription & Recordings',
            'description' => 'Call recording and transcription service',
            'price' => 25.00,
            'enabled' => true,
            'icon' => '🎙️',
            'applicable_to' => ['ai_calls']
        ]
    ],
    
    /**
     * Agent Levels for AI Calls
     */
    'agent_levels' => [
        'basic' => [
            'name' => 'Basic',
            'description' => 'Standard AI capabilities',
            'price_multiplier' => 1.0,
            'features' => [
                'Basic call handling',
                'Standard scripts',
                'Basic reporting'
            ]
        ],
        'advanced' => [
            'name' => 'Advanced',
            'description' => 'Enhanced AI with learning capabilities',
            'price_multiplier' => 1.5,
            'features' => [
                'Advanced natural language',
                'Custom script adaptation',
                'Detailed analytics',
                'Priority support'
            ]
        ],
        'professional' => [
            'name' => 'Professional',
            'description' => 'Enterprise-grade AI solution',
            'price_multiplier' => 2.0,
            'features' => [
                'Full customization',
                'Multi-language support',
                'Advanced integrations',
                'Dedicated account manager',
                'Real-time monitoring'
            ]
        ]
    ],
    
    /**
     * Call Setup Types
     */
    'call_setup_types' => [
        'forwarding' => [
            'name' => 'All Numbers To 1 Agent Number (Forwarding)',
            'description' => 'Simple forwarding setup - all incoming calls route to one number',
            'complexity' => 'simple',
            'setup_fee' => 0.00
        ],
        'standard' => [
            'name' => 'Individual Numbers to Individual Agent (Standard Setup)',
            'description' => 'Each phone number is assigned to a specific agent',
            'complexity' => 'medium',
            'setup_fee' => 25.00
        ],
        'multi_forward' => [
            'name' => 'Multi-forward to Multi-agent for Each Phone Number',
            'description' => 'Advanced routing with multiple agents per number',
            'complexity' => 'complex',
            'setup_fee' => 50.00
        ]
    ],
    
    /**
     * Porting Configuration
     */
    'porting' => [
        'whitelabel_fee' => 100.00,
        'standard_port_fee' => 10.00, // Per number
        'processing_time_days' => 7,
        'trello_integration' => true,
        'trello_board_id' => 'YOUR_TRELLO_BOARD_ID', // Configure this
        'generate_port_letter' => true
    ],
    
    /**
     * Order Status Types
     */
    'order_statuses' => [
        'pending' => [
            'name' => 'Pending',
            'description' => 'Order received, awaiting processing',
            'color' => '#FFA500'
        ],
        'processing' => [
            'name' => 'Processing',
            'description' => 'Order is being set up',
            'color' => '#0073aa'
        ],
        'completed' => [
            'name' => 'Completed',
            'description' => 'Order is live and active',
            'color' => '#008000'
        ],
        'cancelled' => [
            'name' => 'Cancelled',
            'description' => 'Order was cancelled',
            'color' => '#dc3545'
        ],
        'on_hold' => [
            'name' => 'On Hold',
            'description' => 'Order is temporarily paused',
            'color' => '#6c757d'
        ]
    ],
    
    /**
     * Form Validation Rules
     */
    'validation' => [
        'username' => [
            'min_length' => 3,
            'max_length' => 50,
            'pattern' => '/^[a-zA-Z0-9_-]+$/',
            'error_message' => 'Username must be 3-50 characters and contain only letters, numbers, underscores, and hyphens'
        ],
        'password' => [
            'min_length' => 8,
            'max_length' => 100,
            'require_uppercase' => false,
            'require_lowercase' => false,
            'require_number' => true,
            'require_special' => false,
            'error_message' => 'Password must be at least 8 characters and contain at least one number'
        ],
        'email' => [
            'max_length' => 100,
            'error_message' => 'Please enter a valid email address'
        ],
        'phone' => [
            'pattern' => '/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/',
            'error_message' => 'Please enter a valid phone number'
        ]
    ],
    
    /**
     * Email Notifications
     */
    'notifications' => [
        'enable_user_email' => true,
        'enable_admin_email' => true,
        'admin_email' => get_option('admin_email'),
        'from_name' => 'AI Products Order System',
        'from_email' => 'noreply@organizedchaos.cc',
        'templates' => [
            'user_order_confirmation' => [
                'subject' => 'Your AI Products Order Confirmation - {order_number}',
                'enabled' => true
            ],
            'admin_new_order' => [
                'subject' => 'New Order Received - {order_number}',
                'enabled' => true
            ]
        ]
    ],
    
    /**
     * PDF Configuration
     */
    'pdf' => [
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'include_logo' => true,
        'logo_url' => '', // Set your logo URL
        'company_name' => 'Organized Chaos',
        'company_address' => [
            'street' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => 'USA'
        ],
        'footer_text' => '© 2025 Organized Chaos. All rights reserved.',
        'include_terms' => false,
        'terms_text' => ''
    ],
    
    /**
     * Security Settings
     */
    'security' => [
        'enable_recaptcha' => false,
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'rate_limit_enabled' => false,
        'rate_limit_requests' => 5,
        'rate_limit_window' => 300, // seconds
        'sanitize_inputs' => true,
        'verify_nonce' => true
    ],
    
    /**
     * Advanced Features
     */
    'features' => [
        'enable_storage_persistence' => true,
        'enable_auto_save' => true,
        'auto_save_interval' => 5000, // milliseconds
        'enable_analytics' => false,
        'enable_multisite' => false,
        'allow_guest_orders' => false,
        'require_user_account' => true
    ],
    
    /**
     * UI/UX Settings
     */
    'ui' => [
        'show_product_icons' => true,
        'show_product_features' => true,
        'show_pricing' => false, // Set to true to display prices
        'enable_tooltips' => true,
        'enable_progress_indicator' => false,
        'form_style' => 'default', // default, compact, expanded
        'color_scheme' => 'blue', // blue, green, purple, custom
        'animation_enabled' => true
    ],
    
    /**
     * Integration Settings
     */
    'integrations' => [
        'trello' => [
            'enabled' => false,
            'api_key' => '',
            'api_token' => '',
            'board_id' => '',
            'list_id' => ''
        ],
        'slack' => [
            'enabled' => false,
            'webhook_url' => '',
            'channel' => '#orders'
        ],
        'google_analytics' => [
            'enabled' => false,
            'tracking_id' => ''
        ],
        'zapier' => [
            'enabled' => false,
            'webhook_url' => ''
        ]
    ],
    
    /**
     * Custom Fields (Additional data to collect)
     */
    'custom_fields' => [
        // Example custom field
        // 'company_name' => [
        //     'label' => 'Company Name',
        //     'type' => 'text',
        //     'required' => false,
        //     'placeholder' => 'Enter your company name',
        //     'section' => 'user_info'
        // ]
    ],
    
    /**
     * Debug & Development
     */
    'debug' => [
        'log_api_calls' => false,
        'log_file_path' => WP_CONTENT_DIR . '/uploads/ai-orders/debug.log',
        'verbose_errors' => false,
        'test_mode' => false,
        'mock_api_responses' => false
    ]
];