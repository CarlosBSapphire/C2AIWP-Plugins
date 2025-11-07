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
     * Twilio Porting API base URL
     *
     * @var string
     */
    private $portingBaseUrl = 'https://numbers.twilio.com/v1';

    /**
     * Twilio Upload API base URL
     *
     * @var string
     */
    private $uploadBaseUrl = 'https://numbers-upload.twilio.com/v1';

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
     * Check if a phone number can be ported to Twilio
     *
     * @param string $phoneNumber Phone number in E.164 format (e.g., +16175551212)
     * @param string|null $targetAccountSid Target Account SID (optional)
     * @param string|null $addressSid Address SID (optional)
     * @return array [
     *     'success' => bool,
     *     'portable' => bool|null,
     *     'pin_and_account_number_required' => bool|null,
     *     'number_type' => string|null,
     *     'not_portable_reason' => string|null,
     *     'not_portable_reason_code' => int|null,
     *     'data' => array|null,
     *     'error' => string|null
     * ]
     */
    public function checkPortability($phoneNumber, $targetAccountSid = null, $addressSid = null)
    {
        try {
            // Validate E.164 format
            if (!preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber)) {
                throw new \Exception('Phone number must be in E.164 format (e.g., +16175551212)');
            }

            $this->log('info', '[checkPortability] Checking portability', [
                'phone_number' => $phoneNumber,
                'target_account_sid' => $targetAccountSid
            ]);

            // Build URL with phone number
            $url = "{$this->portingBaseUrl}/Porting/Portability/PhoneNumber/{$phoneNumber}";

            // Add query parameters if provided
            $queryParams = [];
            if ($targetAccountSid) {
                $queryParams['TargetAccountSid'] = $targetAccountSid;
            }
            if ($addressSid) {
                $queryParams['AddressSid'] = $addressSid;
            }

            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            // Make GET request to Porting API
            $response = $this->makePortingRequest('GET', $url);

            if ($response['success']) {
                $data = $response['data'];

                $this->log('info', '[checkPortability] Portability check complete', [
                    'phone_number' => $phoneNumber,
                    'portable' => $data['portable'] ?? null,
                    'number_type' => $data['number_type'] ?? null,
                    'pin_required' => $data['pin_and_account_number_required'] ?? null
                ]);

                return [
                    'success' => true,
                    'portable' => $data['portable'] ?? null,
                    'pin_and_account_number_required' => $data['pin_and_account_number_required'] ?? null,
                    'number_type' => $data['number_type'] ?? null,
                    'country' => $data['country'] ?? null,
                    'not_portable_reason' => $data['not_portable_reason'] ?? null,
                    'not_portable_reason_code' => $data['not_portable_reason_code'] ?? null,
                    'account_sid' => $data['account_sid'] ?? null,
                    'data' => $data,
                    'error' => null
                ];
            }

            $this->log('error', '[checkPortability] Portability check failed', [
                'phone_number' => $phoneNumber,
                'error' => $response['error'] ?? 'Unknown error'
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->log('error', '[checkPortability] Exception', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'portable' => null,
                'pin_and_account_number_required' => null,
                'number_type' => null,
                'country' => null,
                'not_portable_reason' => null,
                'not_portable_reason_code' => null,
                'account_sid' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make HTTP request to Twilio Porting API
     *
     * @param string $method HTTP method
     * @param string $url URL
     * @param array $data Request data (optional)
     * @return array
     */
    private function makePortingRequest($method, $url, $data = [])
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("{$this->accountSid}:{$this->authToken}"),
            'Accept' => 'application/json'
        ];

        // For GET requests, data should be in query string (already in URL)
        // For POST/PUT, use JSON body
        $body = null;
        if ($method !== 'GET' && !empty($data)) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($data);
        }

        $this->log('info', '[makePortingRequest] API Request', [
            'method' => $method,
            'url' => $url
        ]);

        $response = call_user_func($this->httpClient, $url, $method, $body, $headers);

        $this->log('info', '[makePortingRequest] API Response', [
            'success' => $response['success'] ?? false,
            'status' => $response['status'] ?? null
        ]);

        return $response;
    }

    /**
     * Check portability for multiple phone numbers
     *
     * @param array $phoneNumbers Array of phone numbers in E.164 format
     * @param string|null $targetAccountSid Target Account SID (optional)
     * @return array [
     *     'success' => bool,
     *     'results' => array, // Array of portability results indexed by phone number
     *     'portable_count' => int,
     *     'not_portable_count' => int,
     *     'error' => string|null
     * ]
     */
    public function checkBulkPortability($phoneNumbers, $targetAccountSid = null)
    {
        try {
            $results = [];
            $portableCount = 0;
            $notPortableCount = 0;

            $this->log('info', '[checkBulkPortability] Starting bulk check', [
                'count' => count($phoneNumbers)
            ]);

            foreach ($phoneNumbers as $phoneNumber) {
                $result = $this->checkPortability($phoneNumber, $targetAccountSid);
                $results[$phoneNumber] = $result;

                if ($result['success'] && $result['portable']) {
                    $portableCount++;
                } elseif ($result['success'] && !$result['portable']) {
                    $notPortableCount++;
                }
            }

            $this->log('info', '[checkBulkPortability] Bulk check complete', [
                'total' => count($phoneNumbers),
                'portable' => $portableCount,
                'not_portable' => $notPortableCount
            ]);

            return [
                'success' => true,
                'results' => $results,
                'portable_count' => $portableCount,
                'not_portable_count' => $notPortableCount,
                'total_count' => count($phoneNumbers),
                'error' => null
            ];
        } catch (\Exception $e) {
            $this->log('error', '[checkBulkPortability] Exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'results' => [],
                'portable_count' => 0,
                'not_portable_count' => 0,
                'total_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload a utility bill document for port-in verification
     *
     * @param string $filePath Path to the utility bill file (PDF or image)
     * @param string|null $friendlyName Friendly name for the document
     * @return array [
     *     'success' => bool,
     *     'document_sid' => string|null,
     *     'status' => string|null,
     *     'mime_type' => string|null,
     *     'data' => array|null,
     *     'error' => string|null
     * ]
     */
    public function uploadUtilityBill($filePath, $friendlyName = null)
    {
        try {
            // Validate file exists
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // Validate file size (max 10MB)
            $fileSize = filesize($filePath);
            $maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if ($fileSize > $maxSize) {
                throw new \Exception("File size ({$fileSize} bytes) exceeds maximum allowed size (10MB)");
            }

            // Validate file type (PDF or image)
            $mimeType = mime_content_type($filePath);
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception("Invalid file type: {$mimeType}. Allowed types: PDF, JPEG, PNG");
            }

            $this->log('info', '[uploadUtilityBill] Starting upload', [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'friendly_name' => $friendlyName
            ]);

            $url = "{$this->uploadBaseUrl}/Documents";

            // Prepare multipart form data
            $formData = [
                'document_type' => 'utility_bill',
                'File' => new \CURLFile($filePath, $mimeType, basename($filePath))
            ];

            if ($friendlyName) {
                $formData['friendly_name'] = $friendlyName;
            }

            // Make multipart request
            $response = $this->makeMultipartRequest('POST', $url, $formData);

            if ($response['success']) {
                $data = $response['data'];

                // Validate mime_type in response (if empty, upload failed)
                if (empty($data['mime_type'])) {
                    $this->log('error', '[uploadUtilityBill] Upload failed - no mime_type in response', [
                        'response' => $data
                    ]);

                    return [
                        'success' => false,
                        'document_sid' => null,
                        'status' => null,
                        'mime_type' => null,
                        'data' => $data,
                        'error' => 'Document upload failed - file had no content or was not processed'
                    ];
                }

                $this->log('info', '[uploadUtilityBill] Upload successful', [
                    'document_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? null,
                    'mime_type' => $data['mime_type'] ?? null
                ]);

                return [
                    'success' => true,
                    'document_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? null,
                    'mime_type' => $data['mime_type'] ?? null,
                    'version' => $data['version'] ?? null,
                    'friendly_name' => $data['friendly_name'] ?? null,
                    'data' => $data,
                    'error' => null
                ];
            }

            $this->log('error', '[uploadUtilityBill] Upload failed', [
                'error' => $response['error'] ?? 'Unknown error'
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->log('error', '[uploadUtilityBill] Exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'document_sid' => null,
                'status' => null,
                'mime_type' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload a utility bill from base64-encoded content
     *
     * @param string $base64Content Base64-encoded file content
     * @param string $fileName Original filename
     * @param string $mimeType MIME type (e.g., 'application/pdf')
     * @param string|null $friendlyName Friendly name for the document
     * @return array [
     *     'success' => bool,
     *     'document_sid' => string|null,
     *     'status' => string|null,
     *     'mime_type' => string|null,
     *     'data' => array|null,
     *     'error' => string|null
     * ]
     */
    public function uploadUtilityBillFromBase64($base64Content, $fileName, $mimeType, $friendlyName = null)
    {
        try {
            $this->log('info', '[uploadUtilityBillFromBase64] Starting upload from base64', [
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'content_length' => strlen($base64Content)
            ]);

            // Decode base64
            $fileContent = base64_decode($base64Content);
            if ($fileContent === false) {
                throw new \Exception('Failed to decode base64 content');
            }

            // Validate file size (max 10MB)
            $fileSize = strlen($fileContent);
            $maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if ($fileSize > $maxSize) {
                throw new \Exception("File size ({$fileSize} bytes) exceeds maximum allowed size (10MB)");
            }

            // Validate MIME type
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception("Invalid file type: {$mimeType}. Allowed types: PDF, JPEG, PNG");
            }

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'utility_bill_');
            if ($tempFile === false) {
                throw new \Exception('Failed to create temporary file');
            }

            // Write content to temp file
            if (file_put_contents($tempFile, $fileContent) === false) {
                @unlink($tempFile);
                throw new \Exception('Failed to write to temporary file');
            }

            try {
                // Upload the temporary file
                $result = $this->uploadUtilityBill($tempFile, $friendlyName);

                // Clean up temp file
                @unlink($tempFile);

                return $result;
            } catch (\Exception $e) {
                // Clean up temp file on error
                @unlink($tempFile);
                throw $e;
            }
        } catch (\Exception $e) {
            $this->log('error', '[uploadUtilityBillFromBase64] Exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'document_sid' => null,
                'status' => null,
                'mime_type' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make multipart/form-data HTTP request to Twilio Upload API
     *
     * @param string $method HTTP method
     * @param string $url URL
     * @param array $formData Multipart form data
     * @return array
     */
    private function makeMultipartRequest($method, $url, $formData = [])
    {
        $this->log('info', '[makeMultipartRequest] Starting multipart request', [
            'method' => $method,
            'url' => $url,
            'form_fields' => array_keys($formData)
        ]);

        // Initialize cURL
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "{$this->accountSid}:{$this->authToken}",
            CURLOPT_POSTFIELDS => $formData,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
                // Content-Type will be set automatically by cURL for multipart
            ]
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($curlError) {
            $this->log('error', '[makeMultipartRequest] cURL error', [
                'error' => $curlError
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => "cURL error: {$curlError}",
                'status' => null
            ];
        }

        // Parse JSON response
        $responseData = json_decode($responseBody, true);

        $this->log('info', '[makeMultipartRequest] Response received', [
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300
        ]);

        // Check HTTP status code
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'error' => null,
                'status' => $httpCode
            ];
        }

        // Handle error response
        $errorMessage = $responseData['message'] ?? 'Unknown error';
        $errorCode = $responseData['code'] ?? null;

        return [
            'success' => false,
            'data' => $responseData,
            'error' => "HTTP {$httpCode}: {$errorMessage}" . ($errorCode ? " (Code: {$errorCode})" : ''),
            'status' => $httpCode
        ];
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
