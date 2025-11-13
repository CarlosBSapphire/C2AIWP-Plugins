<?php

namespace AIPW\Services;

use AIPW\Core\SecurityValidator;
use AIPW\Services\HelperFunctions;

/**
 * n8n API Client
 *
 * Platform-agnostic client for communicating with n8n webhook endpoints.
 * Handles all API requests with built-in security validation.
 *
 * @package AIPW\Services
 * @version 1.0.0
 */
class N8nClient
{
    /**
     * n8n webhook endpoints
     */
    private bool $isTest = true;


    public bool $isSelfHosted = false;
    public string $C2AI_N8N_BASE_URL = 'https://workflows.customer2.ai/webhook/';
    public string $C2AI_N8N_BASE_URL_TEST = 'https://workflows.customer2.ai/webhook-test/';
    public string $N8N_BASE_URL = 'https://n8n.workflows.organizedchaos.cc/webhook/';
    



    /* Twilio Porting Endpoints */
    public string $TWILIO_CHECK_PORTABILITY_ENDPOINT;
    public string $TWILIO_UPLOAD_UTILITY_BILL;
    public string $TWILIO_GET_PORT_IN_REQUESTS;
    public string $TWILIO_REQUEST_PORT;

    /* Gebneral n8n Webhook Endpoints */
    public string $ENDPOINT_SELECT;
    public string $ENDPOINT_CHARGE_CUSTOMER;
    public string $ENDPOINT_WEBSITE_PAYLOAD_PURCHASE;
    public string $ENDPOINT_SUBMIT_PORTING_LOA;
    public string $TICKET_GENERATOR_EMAIL_ADDRESS;
    public string $TWILIO_CANCEL_PORT_IN_REQUEST;
    public string $CREATE_WEBSITE_PRICING_RECORDS;
    public string $DECREMENT_AVAILABLE_USES;
    public string $CREATE_PORTING_LOA_RECORD;

    public string $DEFAULT_PRICING_ID;


    /**
     * HTTP client adapter
     *
     * @var callable
     */
    private $httpClient;

    /**
     * Logger function
     *
     * @var callable
     */
    private $logger;

    /**
     * Cache adapter
     *
     * @var object|null
     */
    private $cache;

    /**
     * n8n API client
     *
     * @var HelperFunctions
     */
    private $helperFunctions;

    /**
     * Constructor
     *
     * @param callable $httpClient HTTP client function: function($url, $method, $data, $headers)
     * @param callable|null $logger Logger function: function($message, $level, $context)
     * @param object|null $cache Cache adapter with get/set/has methods
     */
    public function __construct(callable $httpClient, callable $logger = null, $cache = null, HelperFunctions $helperFunctions = null)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->helperFunctions = $helperFunctions ?? new HelperFunctions;

        if(!$this->isSelfHosted){
            $this->N8N_BASE_URL = $this->N8N_BASE_URL;
        }else{
            $this->N8N_BASE_URL = $this->C2AI_N8N_BASE_URL;
        }
         

        /* Dynamic Select Endpoint for selecting table data */
        $this->ENDPOINT_SELECT = $this->N8N_BASE_URL . 'da176ae9-496c-4f08-baf5-6a78a6a42adb';

        /* Payment Charge Endpoint - Test or Live */
        $this->ENDPOINT_CHARGE_CUSTOMER = $this->isTest
            ? $this->N8N_BASE_URL . 'charge-test'
            : $this->N8N_BASE_URL . 'charge-customer';

        /**
         * Data object is orderComplete payload
         * To Do: give example here
         */
        $this->ENDPOINT_WEBSITE_PAYLOAD_PURCHASE = $this->N8N_BASE_URL . 'website-payload-purchase';

        /**
         * Data object example:
         * {
         *   "sender_name": 
         *   "Customer2 AI System",
         *   "recipient_email": 
         *   "dev@sapphiremediallc.com",
         *   "subject": 
         *   "Daily Cost Calculation Report",
         *   "messagebody":"",
         *   "attachment":""
         *  }
         */
        $this->ENDPOINT_SUBMIT_PORTING_LOA = $this->N8N_BASE_URL . '59bc28f3-2fc6-42cd-8bc8-a8add1b5f6c4';


        /* Trello Ticket Generator Email Address */
        $this->TICKET_GENERATOR_EMAIL_ADDRESS = $this->isTest ? 'ianf@sapphiremediallc.com' : 'dev@sapphiremediallc.com';

        $this->TWILIO_CHECK_PORTABILITY_ENDPOINT = $this->N8N_BASE_URL . '';

        $this->TWILIO_REQUEST_PORT = $this->N8N_BASE_URL . '';

        $this->TWILIO_UPLOAD_UTILITY_BILL = $this->N8N_BASE_URL . '';

        $this->TWILIO_GET_PORT_IN_REQUESTS = $this->N8N_BASE_URL . '';

        $this->TWILIO_CANCEL_PORT_IN_REQUEST = $this->N8N_BASE_URL . '';
        $this->CREATE_WEBSITE_PRICING_RECORDS = $this->C2AI_N8N_BASE_URL . 'c5c2b2a1-5d81-43a5-9de4-7216e7c29da6';
        $this->DECREMENT_AVAILABLE_USES = $this->C2AI_N8N_BASE_URL . '46b85497-3be7-4ba2-990e-2a074252559a';
        $this->CREATE_PORTING_LOA_RECORD = $this->C2AI_N8N_BASE_URL . 'e9fd63be-35f8-4f23-808f-f75103f0c7a3';
        $this->DEFAULT_PRICING_ID = '4c26d41a-6c83-4e44-9b17-7a243b2aeb17';
    }

    /**
     * Execute a safe select query with security validation
     *
     * @param string $table_name
     * @param array $columns
     * @param array $filters
     * @param array $options ['page' => int, 'limit' => int, 'sort' => array]
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function select($table_name, $columns, $filters = [], $or_statement = [], $options = [])
    {
        // Validate field access, aka they cannot request passwords
        $validation = SecurityValidator::validateFieldAccess($table_name, $columns);

        if (!$validation['valid']) {
            $this->log('security', 'error', [
                'action' => 'blocked_field_access',
                'table' => $table_name,
                'field' => $validation['blocked_field'],
                'error' => $validation['error']
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $validation['error'],
                'error_code' => 'BLOCKED_FIELD'
            ];
        }

        // Build payload
        $payload =
            [
                'table_name' => $table_name,
                'columns' => $columns,
                'filters' => $filters,
                '$or' => $or_statement,
                'page' => $options['page'] ?? 1,
                'limit' => $options['limit'] ?? 50,
                'sort' => $options['sort'] ?? []
            ];

        // Execute request
        return $this->request($this->ENDPOINT_SELECT, 'POST', $payload);
    }

    /* Check Required Fields & Sanitize Input
     * @param array $items
     * @return bool
    */

    private function checkRequiredFields(array $items, array $requiredFields): bool
    {
        foreach ($requiredFields as $field) {
            if (!isset($items[$field]) || empty($items[$field])) {
                return false;
            }
        }
        return true;
    }



    /**
     * Charge customer via Stripe
     *
     * @param array $chargeData
     * @return array
     */
    public function chargeCustomer($chargeData)
    {
        $this->log('[chargeCustomer] Starting charge', 'info', [
            'amount' => $chargeData['total_to_charge'] ?? null,
            'email' => $chargeData['email'] ?? null
        ]);

        // Validate required fields for charge-customer webhook
        $required = ['card_token', 'email', 'first_name', 'last_name', 'total_to_charge'];
        if ($this->checkRequiredFields($chargeData, $required) === false) {
            $this->log('[chargeCustomer] Missing required fields', 'error', [
                'required_fields' => $required,
                'provided_fields' => array_keys($chargeData)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for charging customer'
            ];
        }

        // Sanitize input
        foreach ($chargeData as $key => $value) {
            if (is_string($value) && ($key == 'email' || $key == 'first_name' || $key == 'last_name' || $key == 'card_token' || $key == 'stripe_token' || $key == 'address_line1' || $key == 'address_line2' || $key == 'city' || $key == 'state' || $key == 'country' || $key == 'zip_code')) {
                $validateData = $this->validateDataObjects([[
                    'data_type' => 'string',
                    'value' => $value,
                    'key' => $key
                ]]);

                if (!$validateData) {
                    $this->log('[chargeCustomer] Validation failed', 'error', [
                        'field' => $key,
                        'value' => $value
                    ]);
                    return [
                        'success' => false,
                        'data' => null,
                        'error' => "Invalid data for field: {$key}"
                    ];
                }
            }

            if (is_numeric($value) && ($key == 'total_to_charge')) {
                $validateData = $this->validateDataObjects([[
                    'data_type' => 'number',
                    'value' => $value,
                    'key' => $key
                ]]);

                if (!$validateData) {
                    $this->log('[chargeCustomer] Validation failed', 'error', [
                        'field' => $key,
                        'value' => $value
                    ]);
                    return [
                        'success' => false,
                        'data' => null,
                        'error' => "Invalid data for field: {$key}"
                    ];
                }
            }
        }


        $this->log('[chargeCustomer] Sending request to charge-customer webhook', 'info', [
            'endpoint' => $this->ENDPOINT_CHARGE_CUSTOMER
        ]);

        $result = $this->request($this->ENDPOINT_CHARGE_CUSTOMER, 'POST', $chargeData);

        $this->log('[chargeCustomer] Response received', 'info', [
            'success' => $result['success'] ?? false,
            'error' => $result['error'] ?? null
        ]);

        // If charge successful and not default pricing, decrement available_uses
        if ($result['success'] && isset($chargeData['sales_generated_id'])) {
            $salesGeneratedId = $chargeData['sales_generated_id'];


            // Skip decrement for default pricing
            if ($salesGeneratedId !== $this->DEFAULT_PRICING_ID) {
                $this->log('[chargeCustomer] Decrementing available uses for custom pricing', 'info', [
                    'sales_generated_id' => $salesGeneratedId
                ]);

                $decrementResult = $this->decrementAvailableUses($salesGeneratedId);

                if (!$decrementResult['success']) {
                    $this->log('[chargeCustomer] Failed to decrement available uses', 'warning', [
                        'sales_generated_id' => $salesGeneratedId,
                        'error' => $decrementResult['error'] ?? 'Unknown error'
                    ]);
                    // Note: We don't fail the charge if decrement fails, just log it
                }
            } else {
                $this->log('[chargeCustomer] Skipping decrement for default pricing', 'info');
            }
        }

        return $result;
    }

    /**
     * Decrement available uses for a pricing record
     *
     * @param string $sales_generated_id
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function decrementAvailableUses($sales_generated_id)
    {
        $this->log('[decrementAvailableUses] Decrementing uses', 'info', [
            'sales_generated_id' => $sales_generated_id
        ]);


        $payload = [
            'sales_generated_id' => $sales_generated_id,
            'decrement_by' => 1
        ];

        // TODO: Uncomment when endpoint is ready
        $result = $this->request($this->DECREMENT_AVAILABLE_USES, 'POST', $payload);

        if($result['success'] == false)
        $result = [
            'success' => false,
            'data' => ['message' => $result['error']],
            'error' => null
        ];

        $this->log('[decrementAvailableUses] Response', 'info', [
            'success' => $result['success'] ?? false,
            'error' => $result['error'] ?? null
        ]);

        return $result;
    }

    /**
     * Get pricing data with caching
     *
     * @param array data
     * @return array
     */
    public function getPricing($data)
    {

        // Fetch from API
        $result = $this->select(
            'Website_Pricing',
            [
                'cost_json',
                'Active', // 1
                'coupon_code',
                'available_uses'
            ],
            [
                'Active' => 1,
                'sales_generated_id' => (isset($data['sales_generated_id']) && !empty($data['sales_generated_id'])) ? $data['sales_generated_id'] : $this->DEFAULT_PRICING_ID,
            ],
            [
                [
                    'expiration_date_after' => date('c')
                ],
                [
                    'expiration_date_isnull' => true
                ]
            ],
            [
                'page' => 1,
                'limit' => 1,
                'sort' => ['column' => 'id', 'direction' => 'DESC']
            ]
        );

        if ($result['success'] && !empty($result['data'])) {
            // Check available_uses: must be null OR greater than 0
            if (
                isset($result['data'][0]['available_uses']) &&
                $result['data'][0]['available_uses'] !== null &&
                $result['data'][0]['available_uses'] <= 0
            ) {
                // This pricing record has no available uses left
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'This pricing code has no remaining uses'
                ];
            }
        }

        return $result;
    }

    /**
     * Get pricing data with caching
     *
     * @param array data
     * @return array
     */
    public function getAllPricingOptions()
    {



        // Fetch from API
        $result = $this->select(
            'Website_Pricing',
            [
                'cost_json',
                'Active',
                'name',
                'sales_generated_id',
                'date_created',
                'coupon_code'
            ],
            [],
            [
                'page' => 1,
                'limit' => 100,
                'sort' => ['column' => 'date_created', 'direction' => 'DESC']
            ]
        );

        if ($result['success'] && !empty($result['data'])) {
            // Cache the result
            /*if ($this->cache) {
                $this->cache->set($cacheKey, $result['data'], $cacheTtl);
            }*/

            return [
                'success' => true,
                'data' => $result['data'],
                'cached' => false
            ];
        }

        return $result;
    }

    /**
     * Get pricing data with caching
     *
     * @param data 
     * @return array
     */
    public function validateCoupon($data)
    {

        // Fetch from API
        $result = $this->select(
            'Website_Pricing',
            [
                'sales_generated_id',
                'coupon_code',
                'Active', // 1
                'available_uses'
            ],
            [
                'Active' => 1,
                'coupon_code' => $data['coupon_code'],
            ],
            [
                    [
                        'expiration_date_after' => date('c')
                    ],
                    [
                        'expiration_date_isnull' => true
                    ]
            ],
            [
                'page' => 1,
                'limit' => 100,
                'sort' => ['column' => 'id', 'direction' => 'DESC']
            ]
        );

        if ($result['success'] && !empty($result['data'])) {
            $pricingData = $result['data'][0];

            $salesGeneratedId = $pricingData['sales_generated_id'] ?? null;

            // Check if this is default pricing (skip available_uses check)
            if ($salesGeneratedId === $this->DEFAULT_PRICING_ID) {
                $this->log('[validateCoupon] Default pricing - skipping available_uses check', 'info');

                return [
                    'success' => true,
                    'data' => $result['data'],
                    'cached' => false
                ];
            }

            // For custom pricing, check available_uses
            $availableUses = $pricingData['available_uses'] ?? null;

            // If available_uses is NOT null and is <= 0, reject the coupon
            if ($availableUses !== null && $availableUses <= 0) {
                $this->log('[validateCoupon] Coupon has no remaining uses', 'warning', [
                    'sales_generated_id' => $salesGeneratedId,
                    'available_uses' => $availableUses
                ]);

                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'This coupon has no remaining uses'
                ];
            }

            // Coupon is valid (either unlimited uses or has remaining uses)
            $this->log('[validateCoupon] Coupon validated successfully', 'info', [
                'sales_generated_id' => $salesGeneratedId,
                'available_uses' => $availableUses ?? 'unlimited'
            ]);

            return [
                'success' => true,
                'data' => $result['data'],
                'cached' => false
            ];
        } else {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid coupon code'
            ];
        }
    }

    /**
     * Submit complete order to website-payload-purchase webhook
     *
     * @param array $orderData Complete order payload
     * @return array
     */
    public function submitOrder($orderData)
    {
        $this->log('[submitOrder] Starting order submission', 'info', [
            'products' => $orderData['products'] ?? [],
            'addons' => $orderData['addons'] ?? [],
            'setup_total' => $orderData['setup_total'] ?? 0
        ]);

        $this->log('[submitOrder] Sending to webhook', 'info', [
            'endpoint' => $this->ENDPOINT_WEBSITE_PAYLOAD_PURCHASE
        ]);

        if ($this->checkRequiredFields($orderData, ['products', 'setup_total']) === false) {
            $this->log('[submitOrder] Missing required fields', 'error', [
                'required_fields' => ['products', 'setup_total'],
                'provided_fields' => array_keys($orderData)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for submitting order'
            ];
        }

        // Check if BYO setup type and create porting LOA record
        if (isset($orderData['call_setup']['setup_type']) &&
            $orderData['call_setup']['setup_type'] === 'byo' &&
            !empty($orderData['call_setup']['numbers_to_port'])) {

            $this->log('[submitOrder] BYO setup detected, creating porting LOA record', 'info', [
                'phone_count' => count($orderData['call_setup']['numbers_to_port'])
            ]);

            $loaData = [
                'user_id' => $orderData['payment']['user_id'] ?? null,
                'client_user_id' => $orderData['payment']['user_id'] ?? null,
                'numbers_to_port' => $orderData['call_setup']['numbers_to_port'],
                'signed' => false  // This is called from "Do Later" button, so LOA is not signed yet
            ];

            $loaResult = $this->createPortingLoaRecord($loaData);

            if (!$loaResult['success']) {
                $this->log('[submitOrder] Failed to create porting LOA record', 'error', [
                    'error' => $loaResult['error']
                ]);
                // Continue with order submission even if LOA record creation fails
                // This is not a critical failure
            } else {
                $this->log('[submitOrder] Porting LOA record created successfully', 'info', [
                    'uuid' => $loaResult['data']['uuid'] ?? null
                ]);
            }
        }

        $response = $this->request(
            $this->ENDPOINT_WEBSITE_PAYLOAD_PURCHASE,
            'POST',
            $orderData
        );

        $this->log('[submitOrder] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'has_data' => !empty($response['data']),
            'error' => $response['error'] ?? null,
            'full_response' => $response
        ]);

        if (isset($response['success']) && $response['success']) {
            $this->log('[submitOrder] Order submitted successfully', 'info', [
                'order_id' => $response['data']['order_id'] ?? null
            ]);
            return [
                'success' => true,
                'data' => $response['data'] ?? [],
                'error' => null
            ];
        }

        $this->log('[submitOrder] Order submission failed', 'error', [
            'error' => $response['error'] ?? 'Failed to submit order',
            'response' => $response
        ]);

        return [
            'success' => false,
            'data' => null,
            'error' => $response['error'] ?? 'Failed to submit order'
        ];
    }

    /**
     * Submit porting LOA form to database
     *
     * @param array $loaData LOA form data including user_id, base64 PDF, phone numbers
     * @return array
     */
    public function submitPortingLOA($loaData)
    {
        $this->log('[submitPortingLOA] Starting LOA submission', 'info', [
            'user_id' => $loaData['userId'] ?? null,
            'phone_count' => isset($loaData['numbers_to_port']) ? count(json_decode($loaData['numbers_to_port'], true)) : 0
        ]);

        if ($this->checkRequiredFields($loaData, ['messagebody', 'attachment']) === false) {
            $this->log('[submitPortingLOA] Missing required fields', 'error', [
                'required_fields' => ['messagebody', 'attachment'],
                'provided_fields' => array_keys($loaData)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for submitting porting LOA'
            ];
        }

        $response = $this->request(
            $this->ENDPOINT_SUBMIT_PORTING_LOA,
            'POST',
            $loaData
        );

        $this->log('[submitPortingLOA] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'error' => $response['error'] ?? null
        ]);

        if (isset($response['success']) && $response['success']) {
            return [
                'success' => true,
                'data' => $response['data'] ?? [],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'data' => null,
            'error' => $response['error'] ?? 'Failed to submit porting LOA'
        ];
    }

    /**
     * Create a porting LOA record for deferred or completed submission
     *
     * @param array $data Data containing user_id, numbers_to_port, and signed status
     * @return array
     */
    public function createPortingLoaRecord($data)
    {
        $this->log('[createPortingLoaRecord] Creating LOA record', 'info', [
            'user_id' => $data['user_id'] ?? null,
            'client_user_id' => $data['client_user_id'] ?? null,
            'phone_count' => isset($data['numbers_to_port']) ? count($data['numbers_to_port']) : 0,
            'signed' => $data['signed'] ?? false
        ]);

        if ($this->checkRequiredFields($data, ['user_id', 'client_user_id', 'numbers_to_port']) === false) {
            $this->log('[createPortingLoaRecord] Missing required fields', 'error', [
                'required_fields' => ['user_id', 'client_user_id', 'numbers_to_port'],
                'provided_fields' => array_keys($data)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for creating porting LOA record (user_id, client_user_id, numbers_to_port required)'
            ];
        }

        // Generate UUID for tracking
        $uuid = $this->helperFunctions->generateUUID();

        $this->log('[createPortingLoaRecord] Generated UUID', 'info', [
            'uuid' => $uuid
        ]);

        // Prepare payload for porting_loas table
        $payload = [
            'title' => 'Porting LOA - User ' . ($data['user_id'] ?? 'Unknown'),
            'uuid' => $uuid,
            'client_user_id' => $data['client_user_id'] ?? null,
            'phone_numbers_and_providers' => json_encode($data['numbers_to_port']),
            'signed' => $data['signed'] ?? false
        ];

        $this->log('[createPortingLoaRecord] Sending to webhook', 'info', [
            'endpoint' => $this->CREATE_PORTING_LOA_RECORD,
            'phone_count' => count($data['numbers_to_port'])
        ]);

        $response = $this->request(
            $this->CREATE_PORTING_LOA_RECORD,
            'POST',
            $payload
        );

        $this->log('[createPortingLoaRecord] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'error' => $response['error'] ?? null
        ]);

        if (isset($response['success']) && $response['success']) {
            return [
                'success' => true,
                'data' => $response['data'],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'data' => null,
            'error' => $response['error'] ?? 'Failed to create porting LOA record'
        ];
    }

    /**
     * Execute HTTP request via adapter
     *
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $headers
     * @return array
     */
    private function request($url, $method = 'POST', $data = [], $headers = [])
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $headers = array_merge($defaultHeaders, $headers);

        try {
            // Call the injected HTTP client
            $response = call_user_func($this->httpClient, $url, $method, $data, $headers);

            // Log successful request
            $this->log('api_request', 'info', [
                'url' => $url,
                'method' => $method,
                'status' => $response['status'] ?? 'unknown'
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->log('api_error', 'error', [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log message via adapter
     *
     * @param string $message
     * @param string $level
     * @param array $context
     */
    private function log($message, $level = 'info', $context = [])
    {
        if ($this->logger) {
            call_user_func($this->logger, $message, $level, $context);
        }
    }

    /**
     * Validates an array of objects with data_type, value, and key properties
     * 
     * @param array $items Array of objects to validate
     * @return array Returns ['valid' => bool, 'errors' => array, 'sanitized' => array]
     */
    public function validateDataObjects(array $items): array
    {
        $errors = [];
        $sanitized = [];

        // Valid data types
        $validDataTypes = ['string', 'number', 'integer', 'float', 'boolean', 'email', 'url'];

        foreach ($items as $index => $item) {
            $itemErrors = [];

            // Check if item is an array/object
            if (!is_array($item)) {
                $errors[] = "Item at index $index is not an array/object";
                continue;
            }

            // Check required fields exist
            if (!isset($item['data_type']) || !isset($item['value']) || !isset($item['key'])) {
                $errors[] = "Item at index $index is missing required fields (data_type, value, key)";
                continue;
            }

            // Validate and sanitize key
            $key = $item['key'];
            if (!is_string($key)) {
                $itemErrors[] = "Key must be a string";
            } elseif (empty(trim($key))) {
                $itemErrors[] = "Key cannot be empty";
            } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                $itemErrors[] = "Key contains invalid characters. Only alphanumeric and underscores allowed, must start with letter or underscore";
            } elseif (strlen($key) > 64) {
                $itemErrors[] = "Key exceeds maximum length of 64 characters";
            }
            $sanitizedKey = htmlspecialchars(trim($key), ENT_QUOTES, 'UTF-8');

            // Validate data_type
            $dataType = strtolower(trim($item['data_type']));
            if (!in_array($dataType, $validDataTypes, true)) {
                $itemErrors[] = "Invalid data_type. Allowed: " . implode(', ', $validDataTypes);
            }

            // Validate value based on data_type
            $value = $item['value'];
            $sanitizedValue = null;

            switch ($dataType) {
                case 'string':
                    if (!is_string($value)) {
                        $itemErrors[] = "Value must be a string";
                    } else {
                        // Sanitize string - remove potential XSS
                        $sanitizedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                        // Optional: limit string length
                        if (strlen($sanitizedValue) > 10000) {
                            $itemErrors[] = "String value exceeds maximum length of 10000 characters";
                        }
                    }
                    break;

                case 'number':
                case 'integer':
                    if (!is_numeric($value)) {
                        $itemErrors[] = "Value must be numeric";
                    } else {
                        $sanitizedValue = filter_var($value, FILTER_VALIDATE_INT);
                        if ($sanitizedValue === false) {
                            $sanitizedValue = (int)$value;
                        }
                    }
                    break;

                case 'float':
                    if (!is_numeric($value)) {
                        $itemErrors[] = "Value must be a float";
                    } else {
                        $sanitizedValue = filter_var($value, FILTER_VALIDATE_FLOAT);
                        if ($sanitizedValue === false) {
                            $sanitizedValue = (float)$value;
                        }
                    }
                    break;

                case 'boolean':
                    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                        $itemErrors[] = "Value must be boolean or boolean-like (0, 1, 'true', 'false')";
                    } else {
                        $sanitizedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($sanitizedValue === null) {
                            $itemErrors[] = "Could not convert value to boolean";
                        }
                    }
                    break;

                case 'email':
                    if (!is_string($value)) {
                        $itemErrors[] = "Email value must be a string";
                    } else {
                        $sanitizedValue = filter_var($value, FILTER_VALIDATE_EMAIL);
                        if ($sanitizedValue === false) {
                            $itemErrors[] = "Invalid email format";
                        }
                    }
                    break;

                case 'url':
                    if (!is_string($value)) {
                        $itemErrors[] = "URL value must be a string";
                    } else {
                        $sanitizedValue = filter_var($value, FILTER_VALIDATE_URL);
                        if ($sanitizedValue === false) {
                            $itemErrors[] = "Invalid URL format";
                        }
                    }
                    break;
            }

            // Add errors for this item
            if (!empty($itemErrors)) {
                $errors[] = "Item at index $index (key: '$key'): " . implode('; ', $itemErrors);
            } else {
                // Add sanitized item
                $sanitized[] = [
                    'data_type' => $dataType,
                    'value' => $sanitizedValue,
                    'key' => $sanitizedKey
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $sanitized
        ];
    }

    /**
     * Create new Website_Pricing entry
     *
     * @param array ['cost_json'=> json, 'title' => string, 'active' => int_bool, 'coupon_code' => string]
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */

    public function createNewPricingRecord($data)
    {

        $this->log('[createNewPricingRecord] Create new pricing record', 'info', [
            'cost_json' => $data['cost_json'] ?? null,
            'title' => $data['title'] ?? null,
            'Active' => $data['active'] ?? null,
            'coupon_code' => $data['coupon_code'] ?? null
        ]);

        if ($this->checkRequiredFields($data, ['cost_json', 'title', 'active']) === false) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for adding a sales website pricing record'
            ];
        }

        //generate uuid
        $sales_generated_id = $this->helperFunctions->generateUUID();

        if (!isset($data['cost_json']) || empty($data['cost_json'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Pricing not set'
            ];
        }

        //check valid json
        $decoded_json = json_decode($data['cost_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid cost_json: ' . json_last_error_msg()
            ];
        }

        // Define required pricing rules
        $required_pricing_rules = [
            'has_single_service_charge' => [
                'name' => 'One Time Charge',
                'type' => '1 Service',
                'required_fields' => ['cost']
            ],
            'has_two_service_charge' => [
                'name' => 'One Time Charge',
                'type' => '2 Services',
                'required_fields' => ['cost']
            ],
            'has_three_plus_service_charge' => [
                'name' => 'One Time Charge',
                'type' => '3+ Services',
                'required_fields' => ['cost']
            ],
            'has_quick_inbound_calls_charge' => [
                'name' => 'Inbound Calls',
                'type' => 'Quick',
                'required_fields' => ['phone_per_minute']
            ],
            'has_advanced_inbound_calls_charge' => [
                'name' => 'Inbound Calls',
                'type' => 'Advanced',
                'required_fields' => ['phone_per_minute']
            ],
            'has_conversational_inbound_calls_charge' => [
                'name' => 'Inbound Calls',
                'type' => 'Conversational',
                'required_fields' => ['phone_per_minute']
            ],
            'has_email_agents_charge' => [
                'name' => 'Email Agents',
                'type' => 'Basic',
                'required_fields' => ['cost', 'email_threshold', 'email_cost_overage']
            ],
            'has_chat_agents_charge' => [
                'name' => 'Chat Agents',
                'type' => 'Basic',
                'required_fields' => ['cost', 'chat_threshold', 'chat_cost_overage']
            ],
            'has_transcriptions_and_recordings_charge' => [
                'name' => 'Transcription & Call Recordings',
                'type' => 'Addons',
                'required_fields' => ['cost']
            ],
            'has_basic_qa_charge' => [
                'name' => 'QA',
                'type' => 'Basic',
                'required_fields' => ['cost']
            ],
            'has_advanced_qa_charge' => [
                'name' => 'QA',
                'type' => 'Advanced',
                'required_fields' => ['cost']
            ],
            'has_phone_number_charge' => [
                'name' => 'Phone Number',
                'type' => 'Price Per Number',
                'required_fields' => ['cost']
            ]
        ];

        // Initialize all checks as false
        $charge_checks = array_fill_keys(array_keys($required_pricing_rules), false);

        // Validate each price object
        foreach ($decoded_json as $price_obj) {
            // Check for missing frequency
            if (empty($price_obj['frequency'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Missing Pricing Frequency ' . $price_obj['name'] . ' ' . $price_obj['type']
                ];
            }

            // Match against required rules
            foreach ($required_pricing_rules as $check_key => $rule) {
                if ($price_obj['name'] === $rule['name'] && $price_obj['type'] === $rule['type']) {
                    // Check if all required fields are present and not empty
                    $all_fields_valid = true;
                    foreach ($rule['required_fields'] as $field) {
                        if (!isset($price_obj[$field]) || empty($price_obj[$field])) {
                            $all_fields_valid = false;
                            break;
                        }
                    }

                    if ($all_fields_valid) {
                        $charge_checks[$check_key] = true;
                    }
                }
            }
        }

        // Verify all required checks passed
        foreach ($charge_checks as $key => $passed) {
            if (!$passed) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Failed Pricing Check: ' . $key
                ];
            }
        }



        $payload = [
            'Active' => $data['active'],
            'sales_generated_id' => $sales_generated_id,
            'cost_json' => $data['cost_json'],
            'title' => $data['title'],
            'coupon_code' => $data['coupon_code']
        ];

        $response = $this->request(
            $this->CREATE_WEBSITE_PRICING_RECORDS,
            'POST',
            $payload
        );

        return $response;
    }

    /**
     * Check if a phone number is portable to Twilio
     *
     * @param array $data: Phone number in E.164 format & userId (ID of customer)
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function checkPortability($data)
    {
        $this->log('[checkPortability] Checking portability', 'info', [
            'phone_number' => $data['phone_number'] ?? null,
            'userId' => $data['userId'] ?? null
        ]);

        if ($this->checkRequiredFields($data, ['phone_number', 'userId']) === false) {
            $this->log('[checkPortability] Missing required fields', 'error', [
                'required_fields' => ['phone_number', 'userId'],
                'provided_fields' => array_keys($data)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for checking portability'
            ];
        }

        $payload = [
            'phone_number' => $data['phone_number'],
            'userId' => $data['userId']
        ];

        $response = $this->request(
            $this->TWILIO_CHECK_PORTABILITY_ENDPOINT,
            'POST',
            $payload
        );

        $this->log('[checkPortability] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'portable' => $response['data']['portable'] ?? null,
            'error' => $response['error'] ?? null
        ]);

        return $response;
    }

    /**
     * Upload utility bill document to Twilio
     *
     * @param array $documentData ['document_name' => string, 'file_base64' => string, 'mime_type' => string]
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function uploadUtilityBill($documentData)
    {
        $this->log('[uploadUtilityBill] Uploading utility bill', 'info', [
            'document_name' => $documentData['document_name'] ?? null,
            'mime_type' => $documentData['mime_type'] ?? null,
            'userId' => $documentData['userId'] ?? null
        ]);

        // Validate required fields
        if ($this->checkRequiredFields($documentData, ['document_name', 'file_base64', 'mime_type', 'userId']) === false) {
            $this->log('[uploadUtilityBill] Missing required fields', 'error', [
                'required_fields' => ['document_name', 'file_base64', 'mime_type', 'userId'],
                'provided_fields' => array_keys($documentData)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for uploading utility bill'
            ];
        }

        $payload = [
            'document_type' => 'utility_bill',
            'friendly_name' => $documentData['document_name'],
            'file_base64' => $documentData['file_base64'],
            'mime_type' => $documentData['mime_type'],
            'userId' => $documentData['userId']
        ];

        $response = $this->request(
            $this->TWILIO_UPLOAD_UTILITY_BILL,
            'POST',
            $payload
        );

        $this->log('[uploadUtilityBill] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'document_sid' => $response['data']['sid'] ?? null,
            'error' => $response['error'] ?? null
        ]);

        return $response;
    }

    /**
     * Create a port-in request with Twilio
     *
     * @param array $data Port-in request data
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function createPortInRequest($data)
    {
        $this->log('[createPortInRequest] Creating port-in request', 'info', [
            'phone_count' => isset($data['phone_numbers']) ? count($data['phone_numbers']) : 0,
            'phone_numbers' => isset($data['phone_numbers']) ? $data['phone_numbers'] : [],
            'userId' => $data['userId'] ?? null,
            'customer_name' => $data['losing_carrier_information']['customer_name'] ?? null
        ]);

        // Validate phone_numbers array
        if (!is_array($data['phone_numbers']) || empty($data['phone_numbers'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'phone_numbers must be a non-empty array'
            ];
        }

        // Validate required fields
        if ($this->checkRequiredFields($data, ['losing_carrier_information', 'phone_numbers', 'userId', 'notification_emails']) === false) {
            $this->log('[createPortInRequest] Missing required fields', 'error', [
                'required_fields' => ['losing_carrier_information', 'phone_numbers', 'userId'],
                'provided_fields' => array_keys($data)
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing required fields for creating port-in request'
            ];
        }

        $data['losing_carrier_information']['customer_type'] =  empty($data['losing_carrier_information']['customer_type']) ? 'business' : $data['losing_carrier_information']['customer_type'];
        $data['losing_carrier_information']['account_telephone_number'] = empty($data['losing_carrier_information']['account_telephone_number']) ? $data['phone_numbers'][0] : $data['losing_carrier_information']['account_telephone_number'];
        $data['notification_emails'] = empty($data['notification_emails']) ? $data['notification_emails'] : [$this->TICKET_GENERATOR_EMAIL_ADDRESS, $data['losing_carrier_information']['authorized_representative_email']];

        /* First and Last Name are required in authorized_representative */
        if (!isset($data['losing_carrier_information']['authorized_representative']) || empty($data['losing_carrier_information']['authorized_representative'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'losing_carrier_information.customer_name is required'
            ];
        }

        /* First and Last Name are required in authorized_representative */
        if (!isset($data['losing_carrier_information']['authorized_representative_email']) || empty($data['losing_carrier_information']['authorized_representative_email'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'losing_carrier_information.customer_name is required'
            ];
        } else {
            if ($this->validateDataObjects([[
                'data_type' => 'email',
                'value' => $data['losing_carrier_information']['authorized_representative_email'],
                'key' => 'authorized_representative_email'
            ]])['valid'] === false) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'losing_carrier_information.authorized_representative_email is not a valid email address'
                ];
            }
        }

        $data['notification_emails'] = empty($data['notification_emails']) ? $data['notification_emails'] : [$this->TICKET_GENERATOR_EMAIL_ADDRESS, $data['losing_carrier_information']['authorized_representative_email']];

        // Validate phone_numbers count (max 1000)
        if (count($data['phone_numbers']) >= 1000) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'phone_numbers: size must be between 1 and 1000'
            ];
        }

        $response = $this->request(
            $this->TWILIO_REQUEST_PORT,
            'POST',
            $data
        );

        $this->log('[createPortInRequest] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'port_in_request_sid' => $response['data']['port_in_request_sid'] ?? null,
            'error' => $response['error'] ?? null
        ]);

        return $response;
    }

    /**
     * Get port-in requests from Twilio
     *
     * @param array $filters ['size' => int, 'port_in_request_sid' => string, 'status' => string, 'created_before' => string, 'created_after' => string]
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function getPortInRequests($filters = [])
    {
        $this->log('[getPortInRequests] Fetching port-in requests', 'info', [
            'filters' => $filters
        ]);

        $payload = array_merge([
            'size' => 20
        ], $filters);

        $response = $this->request(
            $this->TWILIO_GET_PORT_IN_REQUESTS,
            'POST',
            $payload
        );

        $this->log('[getPortInRequests] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'count' => isset($response['data']) ? count($response['data']) : 0,
            'error' => $response['error'] ?? null
        ]);

        return $response;
    }

    public function cancelPortInRequest($portInRequestSid)
    {
        $this->log('[cancelPortInRequest] Cancelling port-in request', 'info', [
            'port_in_request_sid' => $portInRequestSid
        ]);

        if (empty($portInRequestSid)) {
            $this->log('[cancelPortInRequest] Missing port_in_request_sid', 'error', []);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing port_in_request_sid for cancelling port-in request'
            ];
        }

        $payload = [
            'port_in_request_sid' => $portInRequestSid
        ];

        $response = $this->request(
            $this->TWILIO_CANCEL_PORT_IN_REQUEST,
            'POST',
            $payload
        );

        $this->log('[cancelPortInRequest] Response received', 'info', [
            'success' => $response['success'] ?? false,
            'error' => $response['error'] ?? null
        ]);

        return $response;
    }
}
