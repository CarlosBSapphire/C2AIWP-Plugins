<?php

namespace AIPW\Services;

/**
 * Twilio Porting Service
 *
 * Handles automated phone number porting via Twilio API.
 * Platform-agnostic - uses injectable HTTP client.
 *
 * @package AIPW\Services
 * @version 2.0.0
 */
class TwilioPortingService
{
    /**
     * Twilio Account SID
     *
     * @var string
     */
    private $accountSid;

    /**
     * Twilio Auth Token
     *
     * @var string
     */
    private $authToken;

    /**
     * HTTP client callable
     *
     * @var callable
     */
    private $httpClient;

    /**
     * Logger callable
     *
     * @var callable|null
     */
    private $logger;

    /**
     * Twilio API base URL
     *
     * @var string
     */
    private $baseUrl = 'https://api.twilio.com/2010-04-01';

    /**
     * Constructor
     *
     * @param string $accountSid Twilio Account SID
     * @param string $authToken Twilio Auth Token
     * @param callable $httpClient HTTP client function
     * @param callable|null $logger Logger function
     */
    public function __construct($accountSid, $authToken, callable $httpClient, callable $logger = null)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Create a port-in request
     *
     * @param array $portingData Porting information
     * @return array ['success' => bool, 'port_in_sid' => string|null, 'data' => array|null, 'error' => string|null]
     */
    public function createPortInRequest($portingData)
    {
        try {
            $this->validatePortingData($portingData);

            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers/Local.json";

            $data = [
                'PhoneNumber' => $portingData['phone_number'],
                'FriendlyName' => $portingData['friendly_name'] ?? 'Ported Number',
                'VoiceUrl' => $portingData['voice_url'] ?? '',
                'VoiceMethod' => 'POST',
                'StatusCallback' => $portingData['status_callback'] ?? '',
                'StatusCallbackMethod' => 'POST'
            ];

            $response = $this->makeRequest('POST', $url, $data);

            if ($response['success']) {
                $this->log('info', 'Port-in request created', [
                    'sid' => $response['data']['sid'] ?? null,
                    'phone_number' => $portingData['phone_number']
                ]);

                return [
                    'success' => true,
                    'port_in_sid' => $response['data']['sid'] ?? null,
                    'data' => $response['data'],
                    'error' => null
                ];
            }

            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Port-in request failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'port_in_sid' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get port-in request status
     *
     * @param string $portInSid Port-in request SID
     * @return array ['success' => bool, 'status' => string|null, 'data' => array|null, 'error' => string|null]
     */
    public function getPortInStatus($portInSid)
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers/{$portInSid}.json";

            $response = $this->makeRequest('GET', $url);

            if ($response['success']) {
                return [
                    'success' => true,
                    'status' => $response['data']['status'] ?? 'unknown',
                    'data' => $response['data'],
                    'error' => null
                ];
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a port-in request
     *
     * @param string $portInSid Port-in request SID
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function cancelPortInRequest($portInSid)
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers/{$portInSid}.json";

            $response = $this->makeRequest('DELETE', $url);

            if ($response['success']) {
                $this->log('info', 'Port-in request cancelled', ['sid' => $portInSid]);
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List all port-in requests
     *
     * @param array $filters Optional filters
     * @return array ['success' => bool, 'port_ins' => array|null, 'error' => string|null]
     */
    public function listPortInRequests($filters = [])
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers.json";

            if (!empty($filters)) {
                $url .= '?' . http_build_query($filters);
            }

            $response = $this->makeRequest('GET', $url);

            if ($response['success']) {
                return [
                    'success' => true,
                    'port_ins' => $response['data']['incoming_phone_numbers'] ?? [],
                    'error' => null
                ];
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'port_ins' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search for available phone numbers
     *
     * @param string $areaCode Area code to search
     * @param string $country Country code (default: US)
     * @return array ['success' => bool, 'numbers' => array|null, 'error' => string|null]
     */
    public function searchAvailableNumbers($areaCode, $country = 'US')
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/AvailablePhoneNumbers/{$country}/Local.json";
            $url .= "?AreaCode={$areaCode}";

            $response = $this->makeRequest('GET', $url);

            if ($response['success']) {
                return [
                    'success' => true,
                    'numbers' => $response['data']['available_phone_numbers'] ?? [],
                    'error' => null
                ];
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'numbers' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Purchase a phone number
     *
     * @param string $phoneNumber Phone number to purchase (E.164 format)
     * @param array $options Purchase options
     * @return array ['success' => bool, 'sid' => string|null, 'data' => array|null, 'error' => string|null]
     */
    public function purchaseNumber($phoneNumber, $options = [])
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers.json";

            $data = array_merge([
                'PhoneNumber' => $phoneNumber,
                'FriendlyName' => $options['friendly_name'] ?? $phoneNumber,
                'VoiceUrl' => $options['voice_url'] ?? '',
                'VoiceMethod' => 'POST',
                'SmsUrl' => $options['sms_url'] ?? '',
                'SmsMethod' => 'POST'
            ], $options);

            $response = $this->makeRequest('POST', $url, $data);

            if ($response['success']) {
                $this->log('info', 'Number purchased', [
                    'sid' => $response['data']['sid'] ?? null,
                    'phone_number' => $phoneNumber
                ]);

                return [
                    'success' => true,
                    'sid' => $response['data']['sid'] ?? null,
                    'data' => $response['data'],
                    'error' => null
                ];
            }

            return $response;
        } catch (\Exception $e) {
            $this->log('error', 'Number purchase failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'sid' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update phone number configuration
     *
     * @param string $sid Phone number SID
     * @param array $updates Configuration updates
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function updateNumber($sid, $updates)
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers/{$sid}.json";

            $response = $this->makeRequest('POST', $url, $updates);

            if ($response['success']) {
                $this->log('info', 'Number updated', ['sid' => $sid]);
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Release a phone number
     *
     * @param string $sid Phone number SID
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function releaseNumber($sid)
    {
        try {
            $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/IncomingPhoneNumbers/{$sid}.json";

            $response = $this->makeRequest('DELETE', $url);

            if ($response['success']) {
                $this->log('info', 'Number released', ['sid' => $sid]);
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate porting data
     *
     * @param array $data
     * @throws \Exception
     */
    private function validatePortingData($data)
    {
        if (empty($data['phone_number'])) {
            throw new \Exception('Phone number is required');
        }

        // Validate E.164 format
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $data['phone_number'])) {
            throw new \Exception('Phone number must be in E.164 format (+1234567890)');
        }
    }

    /**
     * Make HTTP request to Twilio API
     *
     * @param string $method HTTP method
     * @param string $url URL
     * @param array $data Request data
     * @return array
     */
    private function makeRequest($method, $url, $data = [])
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("{$this->accountSid}:{$this->authToken}"),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        // Convert data to form-encoded string for Twilio
        $body = http_build_query($data);

        $response = call_user_func($this->httpClient, $url, $method, $body, $headers);

        return $response;
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
