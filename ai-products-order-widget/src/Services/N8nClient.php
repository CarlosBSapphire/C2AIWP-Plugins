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
    const ENDPOINT_SELECT = 'https://n8n.workflows.organizedchaos.cc/webhook/da176ae9-496c-4f08-baf5-6a78a6a42adb';
    const ENDPOINT_CREATE_USER = 'https://n8n.workflows.organizedchaos.cc/webhook/users/create';
    const ENDPOINT_CHARGE_CUSTOMER = 'https://n8n.workflows.organizedchaos.cc/webhook/charge-customer';

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
        $payload = [
            [
                'table_name' => $table_name,
                'columns' => $columns,
                'filters' => $filters,
                'page' => $options['page'] ?? 1,
                'limit' => $options['limit'] ?? 50,
                'sort' => $options['sort'] ?? []
            ]
        ];

        // Execute request
        return $this->request(self::ENDPOINT_SELECT, 'POST', $payload);
    }

    /**
     * Create a new user
     *
     * @param array $userData
     * @return array
     */
    public function createUser($userData)
    {
        // Sanitize input
        $userData = SecurityValidator::sanitizeInput($userData);

        // Validate required fields
        if (empty($userData['email']) || !SecurityValidator::isValidEmail($userData['email'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Valid email address is required'
            ];
        }

        return $this->request(self::ENDPOINT_CREATE_USER, 'POST', $userData);
    }

    /**
     * Charge customer via Stripe
     *
     * @param array $chargeData
     * @return array
     */
    public function chargeCustomer($chargeData)
    {
        // Sanitize input
        $chargeData = SecurityValidator::sanitizeInput($chargeData);

        // Validate required fields
        $required = ['amount', 'currency', 'payment_method'];
        foreach ($required as $field) {
            if (empty($chargeData[$field])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Required field missing: {$field}"
                ];
            }
        }

        return $this->request(self::ENDPOINT_CHARGE_CUSTOMER, 'POST', $chargeData);
    }

    /**
     * Get pricing data with caching
     *
     * @param int $cacheTtl Cache TTL in seconds (default: 3600 = 1 hour)
     * @return array
     */
    public function getPricing($cacheTtl = 3600)
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

        // Fetch from API
        $result = $this->select(
            'pricing',
            [
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
            ['active' => true],
            [
                'page' => 1,
                'limit' => 100,
                'sort' => ['column' => 'service_type', 'direction' => 'ASC']
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
}
