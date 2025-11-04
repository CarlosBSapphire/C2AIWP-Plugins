<?php

namespace AIPW\Core;

/**
 * Security Validator
 *
 * Platform-agnostic security validation for database field access.
 * Prevents unauthorized access to encrypted/sensitive fields.
 *
 * @package AIPW\Core
 * @version 1.0.0
 */
class SecurityValidator
{
    /**
     * Fields that are NEVER accessible via select queries (encrypted/sensitive)
     *
     * @var array
     */
    private static $blocked_fields = [
        'users' => [
            'password',
            'remember_token',
            'provider_token',
            'stripe_payment_method',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_setup_intent_id',
            'stripe_bank_account_id',
            'paypal_subscription_id',
            'paypal_payer_id',
            'bank_routing_number',
            'bank_account_last4',
            'bank_account_name',
            'tax_id',
            'ip_address',
            'settings'
        ],
        'PaymentMethods' => [
            'stripe_payment_method',
            'seti_id',
            'CreditCardNumber',
            'cvv',
            'routing_number',
            'account_number'
        ],
        'payments' => [
            'transaction_id',
            'metadata'
        ],
        'manual_charges' => []
    ];

    /**
     * Fields that are publicly accessible (no authentication required)
     *
     * @var array
     */
    private static $public_fields = [
        'users' => [
            'id',
            'username',
            'email',
            'email_verified_at',
            'first_name',
            'last_name',
            'full_name',
            'company',
            'role',
            'status',
            'timezone',
            'locale',
            'created_at',
            'updated_at'
        ],
        'pricing' => '*' // All pricing fields are public
    ];

    /**
     * Validate field access for a select query
     *
     * @param string $table_name The database table name
     * @param array $columns Array of column names to validate
     * @param string|null $user_role Optional user role for additional validation
     * @param string|null $user_id Optional user ID for ownership validation
     *
     * @return array ['valid' => bool, 'error' => string|null, 'blocked_field' => string|null]
     */
    public static function validateFieldAccess($table_name, $columns, $user_role = null, $user_id = null)
    {
        // Check for blocked fields
        $blocked = self::getBlockedFields($table_name);

        foreach ($columns as $column) {
            if (in_array($column, $blocked)) {
                return [
                    'valid' => false,
                    'error' => "Access denied: Field '{$column}' is encrypted/sensitive",
                    'blocked_field' => $column,
                    'table' => $table_name
                ];
            }
        }

        // Additional role-based validation could be added here
        // For now, just check blocked fields

        return [
            'valid' => true,
            'error' => null,
            'blocked_field' => null
        ];
    }

    /**
     * Get blocked fields for a specific table
     *
     * @param string $table_name
     * @return array
     */
    public static function getBlockedFields($table_name)
    {
        return self::$blocked_fields[$table_name] ?? [];
    }

    /**
     * Get public fields for a specific table
     *
     * @param string $table_name
     * @return array|string Array of field names or '*' for all fields
     */
    public static function getPublicFields($table_name)
    {
        return self::$public_fields[$table_name] ?? [];
    }

    /**
     * Check if a field is blocked
     *
     * @param string $table_name
     * @param string $field_name
     * @return bool
     */
    public static function isFieldBlocked($table_name, $field_name)
    {
        $blocked = self::getBlockedFields($table_name);
        return in_array($field_name, $blocked);
    }

    /**
     * Check if a field is public
     *
     * @param string $table_name
     * @param string $field_name
     * @return bool
     */
    public static function isFieldPublic($table_name, $field_name)
    {
        $public = self::getPublicFields($table_name);

        if ($public === '*') {
            return true;
        }

        return in_array($field_name, $public);
    }

    /**
     * Sanitize input data (platform-agnostic)
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            // Trim whitespace
            $data = trim($data);
            // Remove control characters except newline and tab
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        }

        return $data;
    }

    /**
     * Validate phone number format (E.164)
     *
     * @param string $phone
     * @return bool
     */
    public static function isValidE164($phone)
    {
        // E.164 format: +[country code][number]
        // Max 15 digits total
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate audit log entry for blocked field access attempt
     *
     * @param string $table_name
     * @param string $field_name
     * @param array $context
     * @return array
     */
    public static function logBlockedAccess($table_name, $field_name, $context = [])
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'blocked_field_access',
            'severity' => 'warning',
            'table' => $table_name,
            'field' => $field_name,
            'context' => $context,
            'message' => "Blocked attempt to access encrypted field: {$field_name} in table: {$table_name}"
        ];
    }
}
