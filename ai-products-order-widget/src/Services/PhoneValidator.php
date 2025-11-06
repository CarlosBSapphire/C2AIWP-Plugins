<?php

namespace AIPW\Services;

require_once WP_CONTENT_DIR . '/mu-plugins/ai-products-order-widget/src/autoload.php';

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

/**
 * Phone Validator Service
 *
 * Platform-agnostic phone number validation and E.164 formatting.
 * Uses libphonenumber-for-php for server-side validation.
 *
 * @package AIPW\Services
 * @version 1.0.0
 */
class PhoneValidator
{
    /**
     * Phone number utility instance
     *
     * @var PhoneNumberUtil
     */
    private $phoneUtil;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->phoneUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * Validate and format phone number to E.164
     *
     * @param string $phone_number
     * @param string|null $default_country ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB')
     * @return array [
     *   'valid' => bool,
     *   'e164' => string|null,
     *   'country' => string|null,
     *   'national' => string|null,
     *   'error' => string|null
     * ]
     */
    public function validate($phone_number, $default_country = null)
    {
        // Empty phone number is considered valid (optional field)
        if (empty($phone_number)) {
            return [
                'valid' => true,
                'e164' => null,
                'country' => null,
                'national' => null,
                'error' => null
            ];
        }

        try {
            // Determine country context
            if ($default_country) {
                $numberProto = $this->phoneUtil->parse($phone_number, $default_country);
            } else {
                // If phone starts with +, parse without country context
                if (substr($phone_number, 0, 1) === '+') {
                    $numberProto = $this->phoneUtil->parse($phone_number, null);
                } else {
                    // Default to US if no country provided and no + prefix
                    $numberProto = $this->phoneUtil->parse($phone_number, 'US');
                }
            }

            // Validate the number
            if (!$this->phoneUtil->isValidNumber($numberProto)) {
                return [
                    'valid' => false,
                    'e164' => null,
                    'country' => null,
                    'national' => null,
                    'error' => 'Invalid phone number format'
                ];
            }

            // Format the number
            $e164 = $this->phoneUtil->format($numberProto, PhoneNumberFormat::E164);
            $national = $this->phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL);
            $country = $this->phoneUtil->getRegionCodeForNumber($numberProto);

            return [
                'valid' => true,
                'e164' => $e164,
                'country' => $country,
                'national' => $national,
                'error' => null
            ];
        } catch (NumberParseException $e) {
            return [
                'valid' => false,
                'e164' => null,
                'country' => null,
                'national' => null,
                'error' => 'Could not parse phone number: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate multiple phone numbers
     *
     * @param array $phone_numbers Array of phone numbers to validate
     * @param string|null $default_country
     * @return array [
     *   'valid' => bool,
     *   'results' => array,
     *   'errors' => array
     * ]
     */
    public function validateBatch($phone_numbers, $default_country = null)
    {
        $results = [];
        $errors = [];

        foreach ($phone_numbers as $index => $phone) {
            $validation = $this->validate($phone, $default_country);
            $results[$index] = $validation;

            if (!$validation['valid'] && !empty($phone)) {
                $errors[] = sprintf(
                    'Phone number #%d is invalid: %s (provided: %s)',
                    $index + 1,
                    $validation['error'],
                    $phone
                );
            }
        }

        return [
            'valid' => empty($errors),
            'results' => $results,
            'errors' => $errors
        ];
    }

    /**
     * Check if phone number is valid E.164 format
     *
     * @param string $phone
     * @return bool
     */
    public function isE164($phone)
    {
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    /**
     * Get country code from E.164 phone number
     *
     * @param string $e164_phone
     * @return string|null
     */
    public function getCountryCode($e164_phone)
    {
        try {
            $numberProto = $this->phoneUtil->parse($e164_phone, null);
            return $this->phoneUtil->getRegionCodeForNumber($numberProto);
        } catch (NumberParseException $e) {
            return null;
        }
    }

    /**
     * Get country name from country code
     *
     * @param string $country_code
     * @return string
     */
    public function getCountryName($country_code)
    {
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'IE' => 'Ireland',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'DK' => 'Denmark',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'ZA' => 'South Africa',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'PH' => 'Philippines',
            'ID' => 'Indonesia',
            'VN' => 'Vietnam',
            'KR' => 'South Korea',
            'TW' => 'Taiwan',
            'IL' => 'Israel',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'TR' => 'Turkey',
            'RU' => 'Russia',
            'UA' => 'Ukraine'
        ];

        return $countries[$country_code] ?? $country_code;
    }
}
