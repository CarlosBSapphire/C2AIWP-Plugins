<?php

namespace AIPW\Core;

require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-content/mu-plugins/ai-products-order-widget/vendor/autoload.php';

use AIPW\Services\N8nClient;
use AIPW\Services\PortingLOAGenerator;
use Dompdf\Dompdf;

/**
 * API Proxy Handler
 *
 * Securely handles API requests from frontend, preventing exposure of
 * webhook URLs and sensitive endpoints in browser console.
 *
 * @package AIPW\Core
 * @version 2.0.0
 */
class ApiProxy
{
    /**
     * n8n API client
     *
     * @var N8nClient
     */
    private $n8nClient;

    /**
     * Logger callable
     *
     * @var callable|null
     */
    private $logger;

    /**
     * Allowed actions (whitelist)
     *
     * @var array
     */
    private $allowedActions = [
        'charge_customer',
        'complete_order',
        'send_porting_loa',
        'get_pricing',
        'create_user',
        'validate_phone',
        'submit_porting_loa',
        'validate_coupon'
    ];

    /**
     * Constructor
     *
     * @param N8nClient $n8nClient
     * @param callable|null $logger
     */
    public function __construct(N8nClient $n8nClient, callable $logger = null)
    {
        $this->n8nClient = $n8nClient;
        $this->logger = $logger;
    }

    /**
     * Handle proxied API request
     *
     * @param string $action Action to perform
     * @param array $data Request data
     * @param string|null $nonce Security nonce (WordPress)
     * @return array Response data
     */
    public function handle($action, $data, $nonce = null)
    {
        try {
            // Validate action
            if (!in_array($action, $this->allowedActions)) {
                $this->log('warning', 'Invalid API action attempted', ['action' => $action]);

                return [
                    'success' => false,
                    'error' => 'Invalid action',
                    'error_code' => 'INVALID_ACTION'
                ];
            }

            // Sanitize input data
            $data = SecurityValidator::sanitizeInput($data);

            // Log request
            $this->log('info', 'API proxy request', [
                'action' => $action,
                'data_keys' => array_keys($data)
            ]);

            // Route to appropriate handler
            $method = 'handle' . str_replace('_', '', ucwords($action, '_'));

            if (method_exists($this, $method)) {
                return $this->$method($data);
            }

            return [
                'success' => false,
                'error' => 'Handler not implemented',
                'error_code' => 'NOT_IMPLEMENTED'
            ];
        } catch (\Exception $e) {
            $this->log('error', 'API proxy error', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'PROXY_ERROR'
            ];
        }
    }

    /**
     * Handle process_payment action
     *
     * @param array $data
     * @return array
     */
    private function handleChargeCustomer($data)
    {
        // Validate required fields
        $required = [
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'stripe_token',
            'card_token',
            'total_to_charge',
            'products'
        ];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Required field missing: {$field}",
                    'error_code' => 'MISSING_FIELD'
                ];
            }
        }

        if ($data['total_to_charge'] <= 0) {
            return [
                'success' => false,
                'error' => "Total to charge must be greater than zero.",
                'error_code' => 'INVALID_AMOUNT'
            ];
        }

        $columns = [
            'cost_json'
        ];

        $filters = ['sales_generated_id' => (!empty($data['sales_generated_id']) && isset($data['sales_generated_id'])) ? $data['sales_generated_id'] : '4c26d41a-6c83-4e44-9b17-7a243b2aeb17', 'Active' => 1];

        $product_count = count($data['products']);

        $get_current_pricing = $this->n8nClient->select('Website_Pricing', $columns, $filters);

        // Validate pricing data was returned
        if (!$get_current_pricing['success'] || empty($get_current_pricing['data'])) {
            return [
                'success' => false,
                'error' => 'Unable to retrieve pricing information',
                'error_code' => 'PRICING_NOT_FOUND'
            ];
        }

        // The n8n endpoint returns an array with one object containing cost_json
        $pricing_data = is_array($get_current_pricing['data']) ? $get_current_pricing['data'][0] : $get_current_pricing['data'];

        if (empty($pricing_data['cost_json'])) {
            return [
                'success' => false,
                'error' => 'Pricing data not found',
                'error_code' => 'PRICING_NOT_FOUND'
            ];
        }

        $total_to_charge = 0;
        //$weekly_charge = 0;

        // cost_json is already an array, no need to json_decode
        foreach ($pricing_data['cost_json'] as $price_obj) {
            if ($product_count == 1) {
                if ($price_obj['type'] == "1 Service") {
                    $total_to_charge = $price_obj['cost'] * 100;
                }
            } elseif ($product_count == 2) {
                if ($price_obj['type'] == "2 Services") {
                    $total_to_charge = $price_obj['cost'] * 100;
                }
            } elseif ($product_count >= 3) {
                if ($price_obj['type'] == "3+ Services") {
                    $total_to_charge = $price_obj['cost'] * 100;
                }
            }
        }

        //add backend weekly charge lookup
        if ($total_to_charge != $data['total_to_charge']) {
            return [
                'success' => false,
                'error' => "Invalid Pricing Package.",
                'error_code' => 'INVALID_AMOUNT'
            ];
        }

        // Call n8n charge-customer endpoint
        $chargeData = [
            'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone_number' => $data['phone_number'] ?? '',
            'address_line_1' => $data['shipping_address'] ?? '',
            'address_line_2' => '',
            'city' => $data['shipping_city'] ?? '',
            'state' => $data['shipping_state'] ?? '',
            'Country' => $data['shipping_country'] ?? 'US',
            'Zip_Code' => $data['shipping_zip'] ?? '',
            'stripe_token' => $data['stripe_token'],
            'card_token' => $data['card_token'],
            'total_to_charge' => $total_to_charge,
            //'weekly_charge' => $weekly_charge,
            'sales_generated_id' => (!empty($data['sales_generated_id']) && isset($data['sales_generated_id'])) ? $data['sales_generated_id'] : '4c26d41a-6c83-4e44-9b17-7a243b2aeb17' 
        ];

        $result = $this->n8nClient->chargeCustomer($chargeData);

        // Don't expose card details in response
        if ($result['success'] && isset($result['data']['payment_method'])) {
            unset($result['data']['payment_method']['card_token']);
        }

        return $result;
    }

    /**
     * Handle complete_order action
     *
     * @param array $data
     * @return array
     */
    private function handleCompleteOrder($data)
    {
        $this->log('info', '[handleCompleteOrder] Starting order completion', [
            'has_payment' => !empty($data['payment']),
            'setup_total' => $data['setup_total'] ?? 0,
            'products' => $data['products'] ?? [],
        ]);

        $payment = $data['payment'] ?? [];
        $setupTotal = $data['setup_total'] ?? 0;


        // Step 2: Prepare complete order payload for n8n webhook
        $orderPayload = [
            // Products and addons
            'products' => $data['products'] ?? [],
            'addons' => $data['addons'] ?? [],

            // Pricing
            'user_setup_total' => $setupTotal,
            'user_weekly_cost' => $data['weekly_cost'] ?? 0,
            'user_by_minute_cost' => $data['by_minute_cost'] ?? 0,

            // Payment info (includes Stripe token and charge ID)
            'payment' => $payment,

            // Call setup (if applicable)
            'call_setup' => $data['call_setup'] ?? null,

            // Timestamp
            'submitted_at' => date('Y-m-d H:i:s')
        ];

        // Step 3: Submit to n8n website-payload-purchase webhook
        $this->log('info', '[handleCompleteOrder] Submitting order to n8n', [
            'products_count' => count($orderPayload['products']),
            'has_call_setup' => !empty($orderPayload['call_setup'])
        ]);

        if ($data['total_to_charge'] <= 0) {
            return [
                'success' => false,
                'error' => "Total to charge must be greater than zero.",
                'error_code' => 'INVALID_AMOUNT'
            ];
        }

        $total_to_charge = 0;
        $weekly_charge = 0;
        $by_minute_charge = 0;
        $number_price = 0; // Initialize to prevent undefined variable

        // Safely extract call setup data if it exists
        $agent_quality_pricing = isset($data['call_setup']) ? ($data['call_setup']['agent_quality_pricing'] ?? null) : null;
        $agent_quality = isset($data['call_setup']) ? ($data['call_setup']['agent_quality'] ?? null) : null;
        $phone_number_type = isset($data['call_setup']) ? ($data['call_setup']['phone_number_type'] ?? null) : null;
        $number_count = isset($data['call_setup']) ? ($data['call_setup']['number_count'] ?? 0) : 0;
        $has_email_service = in_array('email_agents', $data['products']);
        $has_chat_service = in_array('chat_agents', $data['products']);
     

        $columns = [
            'cost_json'
        ];

        $filters = ['sales_generated_id' => (!empty($data['sales_generated_id']) && isset($data['sales_generated_id'])) ? $data['sales_generated_id'] : '4c26d41a-6c83-4e44-9b17-7a243b2aeb17', 'Active' => 1];

        $product_count = count($data['products']);

        $get_current_pricing = $this->n8nClient->select('Website_Pricing', $columns, $filters);

        // Validate pricing data was returned
        if (!$get_current_pricing['success'] || empty($get_current_pricing['data'])) {
            return [
                'success' => false,
                'error' => 'Unable to retrieve pricing information',
                'error_code' => 'PRICING_NOT_FOUND'
            ];
        }

        // The n8n endpoint returns an array with one object containing cost_json
        $pricing_data = is_array($get_current_pricing['data']) ? $get_current_pricing['data'][0] : $get_current_pricing['data'];

        if (empty($pricing_data['cost_json'])) {
            return [
                'success' => false,
                'error' => 'Pricing data not found',
                'error_code' => 'PRICING_NOT_FOUND'
            ];
        }

        // cost_json is already an array, no need to json_decode
        foreach ($pricing_data['cost_json'] as $price_obj) {

            if ($product_count == 1) {
                if ($price_obj['type'] == "1 Service") {
                    $total_to_charge = $price_obj['cost'];
                }
            } elseif ($product_count == 2) {
                if ($price_obj['type'] == "2 Services") {
                    $total_to_charge = $price_obj['cost'];
                }
            } elseif ($product_count >= 3) {
                if ($price_obj['type'] == "3+ Services") {
                    $total_to_charge = $price_obj['cost'];
                }
            }

            if($price_obj['type'] == 'Price Per Number'){
                $number_price = $number_count * $price_obj['cost_per_number'];
            }

            if($price_obj['type'] == 'Quick' && $price_obj['name'] == 'Inbound Calls'){
                if($agent_quality == 'Quick'){
                    $by_minute_charge += $price_obj['phone_per_minute'];
                }
            }

            if($price_obj['type'] == 'Advanced' && $price_obj['name'] == 'Inbound Calls'){
                if($agent_quality == 'Advanced'){
                    $by_minute_charge += $price_obj['phone_per_minute'];
                }
            }

            if($price_obj['type'] == 'Conversational' && $price_obj['name'] == 'Inbound Calls'){
                if($agent_quality == 'Conversational'){
                    $by_minute_charge += $price_obj['phone_per_minute'];
                }
            }

            if($price_obj['name'] == 'Email Agents'){
                if($has_email_service == true){
                    $weekly_charge += $price_obj['cost'];
                }
            }

            if($price_obj['name'] == 'Chat Agents'){
                if($has_chat_service == true){
                    $weekly_charge += $price_obj['cost'];
                }
            }
        } 

        $total_to_charge = $number_price + $total_to_charge;

        $orderPayload['by_minute_charge'] = $by_minute_charge;
        $orderPayload['weekly_cost'] = $weekly_charge;
        $orderPayload['setup_total'] = $total_to_charge;

        $result = $this->n8nClient->submitOrder($orderPayload);

        $this->log('info', '[handleCompleteOrder] Submit order result', [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'order_id' => $result['data']['order_id'] ?? null
        ]);

        if (!$result['success']) {
            $this->log('error', '[handleCompleteOrder] Order submission failed', [
                'error' => $result['error'] ?? 'Unknown error',
                'full_result' => $result
            ]);
            return $result;
        }

        $this->log('info', '[handleCompleteOrder] Order completed successfully', [
            'order_id' => $result['data']['order_id'] ?? uniqid('order_'),
            'charge_id' => $payment['charge_id'] ?? null
        ]);

        return [
            'success' => true,
            'data' => [
                'order_id' => $result['data']['order_id'] ?? uniqid('order_'),
                'charge_id' => $payment['charge_id'] ?? null,
                'message' => 'Order completed successfully'
            ],
            'error' => null
        ];
    }

    /**
     * Handle get_pricing action
     *
     * @param array $data
     * @return array
     */
    private function handleGetPricing($data)
    {
        return $this->n8nClient->getPricing($data);
    }

    /**
     * Handle validate_coupon action
     *
     * @param array $data
     * @return array
     */
    private function handleValidateCoupon($data)
    {
        return $this->n8nClient->validateCoupon($data);
    }


    /**
     * Handle validate_phone action
     *
     * @param array $data
     * @return array
     */
    private function handleValidatePhone($data)
    {
        if (empty($data['phone'])) {
            return [
                'success' => false,
                'error' => 'Phone number is required',
                'error_code' => 'MISSING_PHONE'
            ];
        }

        $phoneValidator = new \AIPW\Services\PhoneValidator();
        $result = $phoneValidator->validate($data['phone'], $data['country'] ?? null);

        return [
            'success' => $result['valid'],
            'data' => $result,
            'error' => $result['error']
        ];
    }

    /**
     * Handle submit_porting_loa action
     *
     * @param array $data
     * @return array
     */
    private function handleSubmitPortingLoa($data)
    {
        try {
            // Validate required fields
            if (empty($data['userId'])) {
                return [
                    'success' => false,
                    'error' => 'User ID is required',
                    'error_code' => 'MISSING_USER_ID'
                ];
            }

            if (empty($data['loa_html'])) {
                return [
                    'success' => false,
                    'error' => 'LOA HTML is required',
                    'error_code' => 'MISSING_LOA_HTML'
                ];
            }

            if (empty($data['numbers_to_port']) || !is_array($data['numbers_to_port'])) {
                return [
                    'success' => false,
                    'error' => 'Phone numbers array is required',
                    'error_code' => 'MISSING_PHONE_NUMBERS'
                ];
            }

            $this->log('info', '[handleSubmitPortingLoa] Generating PDF from HTML');

            // Generate PDF from HTML using Dompdf
            $dompdf = new Dompdf();
            $data['loa_html'] = base64_decode($data['loa_html']);
            $dompdf->loadHtml($data['loa_html']);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Get PDF content as base64
            $pdfOutput = $dompdf->output();
            $pdfBase64 = base64_encode($pdfOutput);

            $this->log('info', '[handleSubmitPortingLoa] PDF generated, size: ' . strlen($pdfBase64) . ' bytes');

            // Extract payment info
            $paymentInfo = $data['paymentInfo'] ?? [];

            // Prepare customer body data
            $customerBody = [
                'name' => trim(($paymentInfo['first_name'] ?? '') . ' ' . ($paymentInfo['last_name'] ?? '')),
                'first_name' => $paymentInfo['first_name'] ?? '',
                'last_name' => $paymentInfo['last_name'] ?? '',
                'email' => $paymentInfo['email'] ?? '',
                'phone_number' => $paymentInfo['phone_number'] ?? '',
                'address_line_1' => $paymentInfo['shipping_address'] ?? '',
                'address_line_2' => '',
                'city' => $paymentInfo['shipping_city'] ?? '',
                'state' => $paymentInfo['shipping_state'] ?? '',
                'Country' => $paymentInfo['shipping_country'] ?? 'US',
                'Zip_Code' => $paymentInfo['shipping_zip'] ?? '',
                'user_id' => $data['userId']['user_id'],
                'numbers_to_port' => $data['numbers_to_port'],
                'submitted_at' => date('Y-m-d H:i:s'),
                'sales_generated_id' => (!empty($data['sales_generated_id']) && isset($data['sales_generated_id'])) ? $data['sales_generated_id'] : '4c26d41a-6c83-4e44-9b17-7a243b2aeb17'
            ];

            // Prepare attachments array
            $attachments = [
                [
                    'filename' => 'porting_loa_' . $data['userId']['userId'] . '_' . date('Ymd') . '.pdf',
                    'content' => $pdfBase64,
                    'encoding' => 'base64',
                    'type' => 'application/pdf'
                ]
            ];

            // Add utility bill if provided
            if (!empty($data['utility_bill_base64'])) {
                $attachments[] = [
                    'filename' => 'utility_bill_' . $data['userId']['userId'] . '.' . ($data['utility_bill_extension'] ?? 'pdf'),
                    'content' => $data['utility_bill_base64'],
                    'encoding' => 'base64',
                    'type' => $data['utility_bill_mime_type'] ?? 'application/pdf'
                ];
            }

            // Prepare email message body
            $messageBody = $this->generatePortingEmailBody($customerBody, $data['numbers_to_port']);

            // Prepare payload for n8n email endpoint
            $emailPayload = [
                'sender_name' => 'Customer2 AI System',
                'recipient_email' => $paymentInfo['email'] ?? 'sales@customer2.ai',
                'subject' => 'Porting LOA Submission - ' . ($paymentInfo['first_name'] ?? '') . ' ' . ($paymentInfo['last_name'] ?? ''),
                'messagebody' => $messageBody,
                'attachment' => $attachments,
                'body' => $customerBody
            ];

            $this->log('info', '[handleSubmitPortingLoa] Submitting to n8n', [
                'recipient' => $emailPayload['recipient_email'],
                'attachment_count' => count($attachments)
            ]);

            // Submit to n8n webhook
            $result = $this->n8nClient->submitPortingLOA($emailPayload);

            if (!$result['success']) {
                $this->log('error', '[handleSubmitPortingLoa] Submission failed', [
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                return $result;
            }

            $this->log('info', '[handleSubmitPortingLoa] LOA form submitted successfully');

            return [
                'success' => true,
                'data' => [
                    'message' => 'LOA form submitted successfully'
                ],
                'error' => null
            ];
        } catch (\Exception $e) {
            $this->log('error', '[handleSubmitPortingLoa] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to submit LOA: ' . $e->getMessage(),
                'error_code' => 'LOA_SUBMIT_FAILED'
            ];
        }
    }


    /**
     * Generate email body for porting LOA submission
     */
    private function generatePortingEmailBody($customerData, $numbersToPort)
    {
        $phoneList = '';
        foreach ($numbersToPort as $entry) {
            $phoneList .= '- ' . ($entry['phone_number'] ?? 'N/A') . ' (Provider: ' . ($entry['service_provider'] ?? 'N/A') . ')' . "\n";
        }

        return <<<HTML
    <html>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <h2>Porting LOA Submission</h2>
        <p>A new porting Letter of Authorization has been submitted.</p>
        <h3>Customer Information:</h3>
        <ul>
            <li><strong>Name:</strong> {$customerData['name']}</li>
            <li><strong>Email:</strong> {$customerData['email']}</li>
            <li><strong>Phone:</strong> {$customerData['phone_number']}</li>
            <li><strong>Address:</strong> {$customerData['address_line_1']}, {$customerData['city']}, {$customerData['state']} {$customerData['Zip_Code']}</li>
            <li><strong>User ID:</strong> {$customerData['user_id']}</li>
        </ul>
        <h3>Numbers to Port:</h3>
        <pre>{$phoneList}</pre>
        <p>Please find the signed LOA and utility bill attached to this email.</p>
        <p>Submitted at: {$customerData['submitted_at']}</p>
    </body>
    </html>
HTML;
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
