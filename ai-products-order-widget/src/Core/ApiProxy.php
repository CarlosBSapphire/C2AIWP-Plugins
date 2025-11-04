<?php

namespace AIPW\Core;

use AIPW\Services\N8nClient;
use AIPW\Services\PortingLOAGenerator;

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
        'process_payment',
        'complete_order',
        'send_porting_loa',
        'get_pricing',
        'create_user',
        'validate_phone'
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
    private function handleProcessPayment($data)
    {
        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'card_number', 'amount'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Required field missing: {$field}",
                    'error_code' => 'MISSING_FIELD'
                ];
            }
        }

        // Call n8n charge-customer endpoint
        $chargeData = [
            'amount' => $data['amount'],
            'currency' => 'usd',
            'payment_method' => [
                'card_number' => $data['card_number'],
                'card_expire' => $data['card_expire'],
                'card_cvv' => $data['card_cvv'],
                'card_name' => $data['card_name']
            ],
            'customer' => [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone_number'] ?? ''
            ],
            'billing_address' => [
                'address' => $data['shipping_address'] ?? '',
                'city' => $data['shipping_city'] ?? '',
                'state' => $data['shipping_state'] ?? '',
                'zip' => $data['shipping_zip'] ?? '',
                'country' => $data['shipping_country'] ?? 'US'
            ],
            'metadata' => [
                'products' => $data['products'] ?? [],
                'addons' => $data['addons'] ?? []
            ]
        ];

        $result = $this->n8nClient->chargeCustomer($chargeData);

        // Don't expose card details in response
        if ($result['success'] && isset($result['data']['payment_method'])) {
            unset($result['data']['payment_method']['card_number']);
            unset($result['data']['payment_method']['card_cvv']);
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

        // Step 1: Charge customer using Stripe token
        if (!empty($payment['stripe_token']) && $setupTotal > 0) {
            $this->log('info', '[handleCompleteOrder] Processing payment', [
                'stripe_token' => substr($payment['stripe_token'], 0, 10) . '...',
                'amount' => $setupTotal
            ]);
            // Format data to match n8n charge-customer webhook expectations
            $chargeData = [
                'name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                'first_name' => $payment['first_name'] ?? '',
                'last_name' => $payment['last_name'] ?? '',
                'email' => $payment['email'] ?? '',
                'phone_number' => $payment['phone_number'] ?? '',
                'address_line_1' => $payment['shipping_address'] ?? '',
                'address_line_2' => '',
                'city' => $payment['shipping_city'] ?? '',
                'state' => $payment['shipping_state'] ?? '',
                'Country' => $payment['shipping_country'] ?? 'United States',
                'Zip_Code' => $payment['shipping_zip'] ?? '',
                'card_token' => $payment['stripe_token'],
                'total_to_charge' => $setupTotal
            ];

            $chargeResult = $this->n8nClient->chargeCustomer($chargeData);

            $this->log('info', '[handleCompleteOrder] Charge result', [
                'success' => $chargeResult['success'],
                'error' => $chargeResult['error'] ?? null,
                'charge_id' => $chargeResult['data']['charge_id'] ?? null
            ]);

            if (!$chargeResult['success']) {
                $this->log('error', '[handleCompleteOrder] Payment failed', [
                    'error' => $chargeResult['error'] ?? 'Unknown error',
                    'full_result' => $chargeResult
                ]);
                return [
                    'success' => false,
                    'error' => 'Payment failed: ' . ($chargeResult['error'] ?? 'Unknown error'),
                    'error_code' => 'PAYMENT_FAILED'
                ];
            }

            // Store charge ID in payment info
            $payment['charge_id'] = $chargeResult['data']['charge_id'] ?? null;
            $this->log('info', '[handleCompleteOrder] Payment successful', [
                'charge_id' => $payment['charge_id']
            ]);
        }

        // Step 2: Prepare complete order payload for n8n webhook
        $orderPayload = [
            // Products and addons
            'products' => $data['products'] ?? [],
            'addons' => $data['addons'] ?? [],

            // Pricing
            'setup_total' => $setupTotal,
            'weekly_cost' => $data['weekly_cost'] ?? 0,

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
     * Handle send_porting_loa action
     *
     * @param array $data
     * @return array
     */
    private function handleSendPortingLoa($data)
    {
        try {
            // Generate LOA PDF
            $customerData = [
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'company' => $data['company'] ?? '',
                'address' => $data['address'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
                'zip' => $data['zip'] ?? ''
            ];

            $phoneNumbers = $data['phone_numbers'] ?? [];

            $loaGenerator = new PortingLOAGenerator($customerData, $phoneNumbers);
            $pdfResult = $loaGenerator->getBase64();

            if (!$pdfResult['success']) {
                return $pdfResult;
            }

            // Send via n8n email webhook
            $emailData = [
                'to' => $data['email'],
                'from' => 'alerts@customer2.ai',
                'bcc' => 'sales@customer2.ai',
                'subject' => 'Porting Letter of Authorization - Customer2.AI',
                'body' => $this->getPortingEmailTemplate($data),
                'attachments' => [
                    [
                        'filename' => $pdfResult['filename'],
                        'content' => $pdfResult['base64'],
                        'type' => 'application/pdf',
                        'disposition' => 'attachment'
                    ]
                ]
            ];

            // Call n8n email endpoint
            $emailResult = $this->sendEmail($emailData);

            return $emailResult;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to send Porting LOA: ' . $e->getMessage(),
                'error_code' => 'LOA_SEND_FAILED'
            ];
        }
    }

    /**
     * Handle get_pricing action
     *
     * @param array $data
     * @return array
     */
    private function handleGetPricing($data)
    {
        return $this->n8nClient->getPricing();
    }

    /**
     * Handle create_user action
     *
     * @param array $data
     * @return array
     */
    private function handleCreateUser($data)
    {
        return $this->n8nClient->createUser($data);
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
     * Send email via n8n endpoint
     *
     * @param array $emailData
     * @return array
     */
    private function sendEmail($emailData)
    {
        // This would call an n8n webhook for email sending
        // For now, return mock response
        // TODO: Implement actual n8n email webhook call

        return [
            'success' => true,
            'data' => [
                'message_id' => 'email_' . uniqid(),
                'status' => 'sent'
            ],
            'error' => null
        ];
    }

    /**
     * Get porting email template
     *
     * @param array $data
     * @return string
     */
    private function getPortingEmailTemplate($data)
    {
        $customerName = ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '');

        return <<<HTML
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Porting Letter of Authorization</h2>

    <p>Dear {$customerName},</p>

    <p>Thank you for choosing Customer2.AI! Attached to this email is your <strong>Porting Letter of Authorization (LOA)</strong>.</p>

    <h3>Next Steps:</h3>
    <ol>
        <li><strong>Print</strong> the attached LOA document</li>
        <li><strong>Sign</strong> the document where indicated</li>
        <li><strong>Scan or photograph</strong> the signed document</li>
        <li><strong>Reply to this email</strong> with the signed LOA attached</li>
        <li><strong>Include a copy of your utility bill</strong> for Twilio porting verification</li>
    </ol>

    <h3>Required Documents:</h3>
    <ul>
        <li>✅ Signed Porting LOA (attached to this email)</li>
        <li>✅ Recent utility bill showing the service address on file with your current carrier</li>
    </ul>

    <p><strong>Important:</strong> The utility bill is required by Twilio for verification purposes. It must show the same service address that appears on your phone bill.</p>

    <p>Once we receive both documents, we will begin the porting process immediately. This typically takes 7-10 business days.</p>

    <p>If you have any questions, please don't hesitate to reach out to our support team.</p>

    <p>Best regards,<br>
    <strong>Customer2.AI Team</strong><br>
    <a href="mailto:support@customer2.ai">support@customer2.ai</a></p>
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
