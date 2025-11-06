<?php

namespace AIPW\Services;

use AIPW\Core\SecurityValidator;

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

    public string $ENDPOINT_SELECT;
    public string $ENDPOINT_CHARGE_CUSTOMER;
    public string $ENDPOINT_WEBSITE_PAYLOAD_PURCHASE;
    public string $ENDPOINT_SUBMIT_PORTING_LOA;
    public string $TICKET_GENERATOR_EMAIL_ADDRESS;
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
     * Constructor
     *
     * @param callable $httpClient HTTP client function: function($url, $method, $data, $headers)
     * @param callable|null $logger Logger function: function($message, $level, $context)
     * @param object|null $cache Cache adapter with get/set/has methods
     */
    public function __construct(callable $httpClient, callable $logger = null, $cache = null)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = $cache;

        /* Dynamic Select Endpoint for selecting table data */
        $this->ENDPOINT_SELECT = 'https://n8n.workflows.organizedchaos.cc/webhook/da176ae9-496c-4f08-baf5-6a78a6a42adb';

        /* Payment Charge Endpoint - Test or Live */
        $this->ENDPOINT_CHARGE_CUSTOMER = $this->isTest
            ? 'https://n8n.workflows.organizedchaos.cc/webhook/charge-test'
            : 'https://n8n.workflows.organizedchaos.cc/webhook/charge-customer';

         /**
         * Data object is orderComplete payload
         * To Do: give example here
         */
        $this->ENDPOINT_WEBSITE_PAYLOAD_PURCHASE = 'https://n8n.workflows.organizedchaos.cc/webhook/website-payload-purchase';

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
        $this->ENDPOINT_SUBMIT_PORTING_LOA = 'https://n8n.workflows.organizedchaos.cc/webhook/59bc28f3-2fc6-42cd-8bc8-a8add1b5f6c4';


        /* Trello Ticket Generator Email Address */
        $this->TICKET_GENERATOR_EMAIL_ADDRESS = 'dev@sapphiremediallc.com';
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
    public function select($table_name, $columns, $filters = [], $options = [])
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
                'page' => $options['page'] ?? 1,
                'limit' => $options['limit'] ?? 50,
                'sort' => $options['sort'] ?? []
            ];

        // Execute request
        return $this->request($this->ENDPOINT_SELECT, 'POST', $payload);
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
        foreach ($required as $field) {
            if (empty($chargeData[$field])) {
                $this->log('[chargeCustomer] Validation failed', 'error', [
                    'missing_field' => $field
                ]);
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Required field missing: {$field}"
                ];
            }
        }

        // Sanitize input
        foreach ($chargeData as $key => $value) {
            if (is_string($value) && ($key == 'email' || $key == 'first_name' || $key == 'last_name' || $key == 'card_token' || $key == 'stripe_token' || $key == 'address_line1' || $key == 'address_line2' || $key == 'city' || $key == 'state' || $key == 'country' || $key == 'zip_code')) {
                $validateData = $this->validateDataObjects([[
                    'data_type' => 'string',
                    'value' => $value,
                    'key' => $key
                ]]);

                if(!$validateData){
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

                if(!$validateData){
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

        return $result;
    }

    /**
     * Get pricing data with caching
     *
     * @param int $cacheTtl Cache TTL in seconds (default: 3600 = 1 hour)
     * @return array
     */
    public function getPricing($cacheTtl = 60)
    {
        $cacheKey = 'aipw_pricing_data';

        // Try cache first
        if ($this->cache && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return [
                    'success' => true,
                    'data' => $cached,
                    'cached' => true
                ];
            }
        }

        /*default json example:
        [
            {
                "type": "1 Service",
                "name": "One Time Charge",
                "cost": 499.00,
                "frequency": "One Time",
                "one_time_processed": 1
            },
            {
                "type": "2 Services",
                "name": "One Time Charge",
                "cost": 799.00,
                "frequency": "One Time",
                "one_time_processed": 1
            },
            {
                "type": "3+ Services",
                "name": "One Time Charge",
                "cost": 999.00,
                "frequency": "One Time",
                "one_time_processed": 1
            },
            {
                "type": "Quick",
                "name": "Inbound Calls",
                "frequency": "Weekly",
                "phone_per_minute": 0.45,
                "phone_per_minute_overage": 0.00,
                "call_threshold": 0
            },
            {
                "type": "Advanced",
                "name": "Inbound Calls",
                "frequency": "Weekly",
                "phone_per_minute": 0.55,
                "phone_per_minute_overage": 0.00,
                "call_threshold": 0
            },
            {
                "type": "Conversational",
                "name": "Inbound Calls",
                "frequency": "Weekly",
                "phone_per_minute": 0.65,
                "phone_per_minute_overage": 0.00,
                "call_threshold": 0
            },
            {
                "type": "Quick",
                "name": "Outbound Calls",
                "frequency": "Weekly",
                "phone_per_minute": 0.45,
                "phone_per_minute_overage": 0.00,
                "call_threshold": 0
            },
            {
                "type": "Advanced",
                "name": "Outbound Calls",
                "frequency": "Weekly",
                "phone_per_minute": 0.55,
                "phone_per_minute_overage": 0.00,
                "call_threshold": 0
            },
            {
                "type": "Conversational",
                "name": "Outbound Calls",
                "frequency": "Weekly",
                "phone_per_minute": 0.65,
                "phone_per_minute_overage": 0.00,
                "call_threshold": 0
            },
            {
                "type": "Basic",
                "name": "Email Agents",
                "frequency": "Weekly",
                "cost": 25.00,
                "email_threshold": 250,
                "email_cost_overage": 0.10
            },
            {
                "type": "Basic",
                "name": "Chat Agents",
                "frequency": "Weekly",
                "cost": 25.00,
                "chat_threshold": 200,
                "chat_cost_overage": 0.10
            },
            {
                "type": "Addons",
                "name": "Transcription & Call Recordings",
                "frequency": "Weekly",
                "cost": 125.00
            },
            {
                "type": "Basic",
                "name": "QA",
                "frequency": "Weekly",
                "cost": 0.00,
                "cost_per_lead": "0.00"
            },
            {
                "type": "Advanced",
                "name": "QA",
                "frequency": "Weekly",
                "cost": 0.00,
                "cost_per_lead": "0.00"
            }
        ]*/
            
        // Fetch from API
            $result = $this->select(
            'Website_Pricing',
            [
                'cost_json',
                'Active' // 1
            ],
            ['Active' => 1, 'id' => 1],
            [
                'page' => 1,
                'limit' => 100,
                'sort' => ['column' => 'id', 'direction' => 'ASC']
            ]
        );

        if ($result['success'] && !empty($result['data'])) {
            // Cache the result
            if ($this->cache) {
                $this->cache->set($cacheKey, $result['data'], $cacheTtl);
            }

            return [
                'success' => true,
                'data' => $result['data'],
                'cached' => false
            ];
        }

        return $result;
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
            'user_id' => $loaData['user_id'] ?? null,
            'phone_count' => isset($loaData['phone_numbers']) ? count(json_decode($loaData['phone_numbers'], true)) : 0
        ]);

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
}
