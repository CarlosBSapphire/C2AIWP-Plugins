<?php

namespace AIPW\Core;

use AIPW\Services\N8nClient;
use AIPW\Services\PhoneValidator;

/**
 * Order Processor
 *
 * Platform-agnostic order processing logic.
 * Handles order validation, user creation, and phone number processing.
 *
 * @package AIPW\Core
 * @version 1.0.0
 */
class OrderProcessor
{
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
     * Logger callable
     *
     * @var callable|null
     */
    private $logger;

    /**
     * Product configuration
     *
     * @var array
     */
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

    /**
     * Addons configuration
     *
     * @var array
     */
    private $addons = [
        'qa' => 'QA',
        'avs_match' => 'AVS Match',
        'custom_package' => 'Custom Package',
        'phone_numbers' => 'Phone Numbers',
        'lead_verification' => 'Lead Verification',
        'transcription_recordings' => 'Transcription & Recordings'
    ];

    /**
     * Agent levels
     *
     * @var array
     */
    private $agent_levels = [
        'essential' => 'Quick',
        'responsive' => 'Advanced',
        'conversational' => 'Conversational'
    ];

    /**
     * Constructor
     *
     * @param N8nClient $n8nClient
     * @param PhoneValidator $phoneValidator
     * @param callable|null $logger
     */
    public function __construct(N8nClient $n8nClient, PhoneValidator $phoneValidator, callable $logger = null)
    {
        $this->n8nClient = $n8nClient;
        $this->phoneValidator = $phoneValidator;
        $this->logger = $logger;
    }

    /**
     * Process order submission
     *
     * @param array $formData
     * @return array ['success' => bool, 'data' => array|null, 'errors' => array]
     */
    public function processOrder($formData)
    {
        $errors = [];

        // Sanitize input
        $formData = SecurityValidator::sanitizeInput($formData);

        // Validate required fields
        $validation = $this->validateOrderData($formData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'data' => null,
                'errors' => $validation['errors']
            ];
        }

        // Process phone numbers if AI Calls is selected
        $phoneData = [];
        if (!empty($formData['selected_products']) && in_array('ai_calls', $formData['selected_products'])) {
            $phoneResult = $this->processPhoneNumbers($formData);
            if (!$phoneResult['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'errors' => $phoneResult['errors']
                ];
            }
            $phoneData = $phoneResult['data'];
        }

        // Create user account
        $userData = $this->buildUserData($formData, $phoneData);
        $userResult = $this->n8nClient->createUser($userData);

        if (!$userResult['success']) {
            $this->log('error', 'Failed to create user', [
                'error' => $userResult['error']
            ]);

            return [
                'success' => false,
                'data' => null,
                'errors' => ['Failed to create user account: ' . $userResult['error']]
            ];
        }

        $this->log('info', 'Order processed successfully', [
            'user_id' => $userResult['data']['id'] ?? 'unknown',
            'email' => $formData['email'] ?? 'unknown'
        ]);

        return [
            'success' => true,
            'data' => [
                'user' => $userResult['data'],
                'phones' => $phoneData,
                'order' => $formData
            ],
            'errors' => []
        ];
    }

    /**
     * Validate order data
     *
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    private function validateOrderData($data)
    {
        $errors = [];

        // Required fields
        if (empty($data['email']) || !SecurityValidator::isValidEmail($data['email'])) {
            $errors[] = 'Valid email address is required';
        }

        if (empty($data['first_name'])) {
            $errors[] = 'First name is required';
        }

        if (empty($data['last_name'])) {
            $errors[] = 'Last name is required';
        }

        if (empty($data['selected_products']) || !is_array($data['selected_products'])) {
            $errors[] = 'At least one product must be selected';
        }

        // Validate selected products
        if (!empty($data['selected_products'])) {
            foreach ($data['selected_products'] as $product) {
                if (!isset($this->products[$product])) {
                    $errors[] = "Invalid product: {$product}";
                }
            }
        }

        // Validate agent level if AI Calls is selected
        if (!empty($data['selected_products']) && in_array('ai_calls', $data['selected_products'])) {
            if (empty($data['agent_level']) || !isset($this->agent_levels[$data['agent_level']])) {
                $errors[] = 'Valid agent level is required for AI Calls';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Process and validate phone numbers
     *
     * @param array $formData
     * @return array ['valid' => bool, 'data' => array, 'errors' => array]
     */
    private function processPhoneNumbers($formData)
    {
        $phoneNumbers = [];
        $errors = [];

        if (isset($formData['ai_calls']['port_numbers']) && is_array($formData['ai_calls']['port_numbers'])) {
            foreach ($formData['ai_calls']['port_numbers'] as $index => $phoneData) {
                if (!empty($phoneData['number'])) {
                    $validation = $this->phoneValidator->validate($phoneData['number']);

                    if (!$validation['valid']) {
                        $errors[] = sprintf(
                            'Phone number #%d is invalid: %s (provided: %s)',
                            $index + 1,
                            $validation['error'],
                            $phoneData['number']
                        );
                    } else {
                        $phoneNumbers[] = [
                            'number' => $validation['e164'],
                            'country' => $validation['country'],
                            'national' => $validation['national'],
                            'provider' => $phoneData['provider'] ?? ''
                        ];
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'data' => $phoneNumbers,
            'errors' => $errors
        ];
    }

    /**
     * Build user data for API submission
     *
     * @param array $formData
     * @param array $phoneData
     * @return array
     */
    private function buildUserData($formData, $phoneData = [])
    {
        return [
            'email' => $formData['email'] ?? '',
            'first_name' => $formData['first_name'] ?? '',
            'last_name' => $formData['last_name'] ?? '',
            'company' => $formData['company'] ?? '',
            'phone_number' => $formData['phone_number'] ?? '',
            'selected_products' => $formData['selected_products'] ?? [],
            'agent_level' => $formData['agent_level'] ?? 'essential',
            'selected_addons' => $formData['selected_addons'] ?? [],
            'call_setup_type' => $formData['ai_calls']['setup_type'] ?? '',
            'port_numbers' => $phoneData,
            'script' => $formData['ai_calls']['script'] ?? '',
            'metadata' => [
                'source' => 'widget',
                'timestamp' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        ];
    }

    /**
     * Get pricing data
     * @param array $data
     * @return array
     */
    public function getPricing($data)
    {
        return $this->n8nClient->getPricing($data);
    }

    /**
     * Get products configuration
     *
     * @return array
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * Get addons configuration
     *
     * @return array
     */
    public function getAddons()
    {
        return $this->addons;
    }

    /**
     * Get agent levels
     *
     * @return array
     */
    public function getAgentLevels()
    {
        return $this->agent_levels;
    }

    /**
     * Log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log($level, $message, $context = [])
    {
        if ($this->logger) {
            call_user_func($this->logger, $message, $level, $context);
        }
    }
}
