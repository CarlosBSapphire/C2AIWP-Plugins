<?php

namespace AIPW\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Porting Letter of Authorization (LOA) Generator
 *
 * Generates PDF documents for phone number porting requests.
 * Platform-agnostic - uses Dompdf for PDF generation.
 *
 * @package AIPW\Services
 * @version 2.0.0
 */
class PortingLOAGenerator
{
    /**
     * Customer information
     *
     * @var array
     */
    private $customerData;

    /**
     * Phone numbers to port
     *
     * @var array
     */
    private $phoneNumbers;

    /**
     * PDF options
     *
     * @var Options
     */
    private $pdfOptions;

    /**
     * Constructor
     *
     * @param array $customerData Customer information
     * @param array $phoneNumbers Array of phone numbers with providers
     */
    public function __construct($customerData, $phoneNumbers = [])
    {
        $this->customerData = $customerData;
        $this->phoneNumbers = $phoneNumbers;

        $this->pdfOptions = new Options();
        $this->pdfOptions->set('isHtml5ParserEnabled', true);
        $this->pdfOptions->set('isRemoteEnabled', true);
    }

    /**
     * Generate Porting LOA PDF
     *
     * @return array ['success' => bool, 'pdf' => string|null, 'filename' => string, 'error' => string|null]
     */
    public function generate()
    {
        try {
            $html = $this->buildHTML();

            $dompdf = new Dompdf($this->pdfOptions);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('Letter', 'portrait');
            $dompdf->render();

            $pdfContent = $dompdf->output();
            $filename = $this->generateFilename();

            return [
                'success' => true,
                'pdf' => $pdfContent,
                'filename' => $filename,
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'pdf' => null,
                'filename' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build HTML for PDF
     *
     * @return string
     */
    private function buildHTML()
    {
        $firstName = $this->escape($this->customerData['first_name'] ?? '');
        $lastName = $this->escape($this->customerData['last_name'] ?? '');
        $businessName = $this->escape($this->customerData['company'] ?? '');
        $address = $this->escape($this->customerData['address'] ?? '');
        $city = $this->escape($this->customerData['city'] ?? '');
        $state = $this->escape($this->customerData['state'] ?? '');
        $zip = $this->escape($this->customerData['zip'] ?? '');

        // Build phone numbers table rows
        $phoneRows = $this->buildPhoneNumberRows();

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0.5in;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .title {
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 20px;
            padding: 10px 0;
            border-top: 3px solid #000;
            border-bottom: 3px solid #000;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .note {
            font-size: 9pt;
            font-style: italic;
            color: #333;
        }

        .input-field {
            border: 1px solid #000;
            padding: 8px;
            min-height: 25px;
            margin-bottom: 10px;
        }

        .input-group {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .input-col {
            display: table-cell;
            padding-right: 10px;
        }

        .input-col:last-child {
            padding-right: 0;
        }

        .label {
            font-size: 9pt;
            margin-bottom: 3px;
        }

        table.phone-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table.phone-table th,
        table.phone-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        table.phone-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .signature-section {
            margin-top: 30px;
        }

        .authorization-text {
            font-size: 9pt;
            line-height: 1.6;
            text-align: justify;
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 3px solid #000;
        }

        .signature-row {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .signature-col {
            display: table-cell;
            width: 33%;
            padding-right: 10px;
        }

        .signature-line {
            border-bottom: 2px solid #000;
            height: 40px;
        }

        .notice {
            margin-top: 30px;
            padding: 15px;
            background-color: #f0f0f0;
            border: 2px solid #000;
            font-weight: bold;
            font-size: 10pt;
        }

        .footer-note {
            font-size: 8pt;
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="data:image/svg+xml;base64,{$this->getLogoBase64()}" alt="Customer2.AI" style="height: 60px;">
        </div>
        <div class="title">PORTING LETTER OF AUTHORIZATION (LOA)</div>
    </div>

    <div class="section">
        <div class="section-title">1. Customer Name (your name should appear exactly as it does on your telephone bill):</div>

        <div class="input-group">
            <div class="input-col" style="width: 50%;">
                <div class="label">First Name</div>
                <div class="input-field">{$firstName}</div>
            </div>
            <div class="input-col" style="width: 50%;">
                <div class="label">Last Name</div>
                <div class="input-field">{$lastName}</div>
            </div>
        </div>

        <div class="label">Business Name <span class="note">(if the service is in your company's name)</span></div>
        <div class="input-field">{$businessName}</div>
    </div>

    <div class="section">
        <div class="section-title">2. Service Address on file with your current carrier</div>
        <div class="note">(Please note, this must be a physical location and cannot be a PO Box):</div>

        <div class="label">Address</div>
        <div class="input-field">{$address}</div>

        <div class="input-group">
            <div class="input-col" style="width: 50%;">
                <div class="label">City</div>
                <div class="input-field">{$city}</div>
            </div>
            <div class="input-col" style="width: 25%;">
                <div class="label">State</div>
                <div class="input-field">{$state}</div>
            </div>
            <div class="input-col" style="width: 25%;">
                <div class="label">Zip/Postal Code</div>
                <div class="input-field">{$zip}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">3. List all the Telephone Number(s) which you authorize to change from your current phone service provider to the Company or its designated agent.</div>

        <table class="phone-table">
            <thead>
                <tr>
                    <th>Phone Number*</th>
                    <th>Service Provider</th>
                </tr>
            </thead>
            <tbody>
                {$phoneRows}
            </tbody>
        </table>

        <div class="footer-note">*If you have more than 4 numbers, please list on an extra page.</div>
    </div>

    <div class="authorization-text">
        By signing the below, I verify that I am, or represent (for a business), the above-named service customer,
        authorized to change the primary carrier(s) for the telephone number(s) listed, and am at least 18 years of age.
        The name and address I have provided is the name and address on record with my local telephone company
        for each telephone number listed. I authorize <strong>Customer2.AI</strong> (the "Company") or its
        designated agent to act on my behalf and notify my current carrier(s) to change my preferred carrier(s) for the
        listed number(s) and service(s), to obtain any information the Company deems necessary to make the carrier
        change(s), including, for example, an inventory of telephone lines billed to the telephone number(s), carrier or
        customer identifying information, billing addresses, and my credit history.
    </div>

    <div class="signature-section">
        <div class="signature-row">
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="label">Authorized Signature</div>
            </div>
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="label">Print</div>
            </div>
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="label">Date</div>
            </div>
        </div>
    </div>

    <div class="notice">
        For toll free numbers, please change RespOrg to TWI01. Please do not end service on the
        number for 10 days after RespOrg change.
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Build phone number table rows
     *
     * @return string
     */
    private function buildPhoneNumberRows()
    {
        $rows = '';

        // Ensure at least 4 rows
        $phoneCount = max(4, count($this->phoneNumbers));

        for ($i = 0; $i < $phoneCount; $i++) {
            $phone = isset($this->phoneNumbers[$i]) ? $this->escape($this->phoneNumbers[$i]['number'] ?? '') : '';
            $provider = isset($this->phoneNumbers[$i]) ? $this->escape($this->phoneNumbers[$i]['provider'] ?? '') : '';

            $rows .= "<tr><td>{$phone}</td><td>{$provider}</td></tr>\n";
        }

        return $rows;
    }

    /**
     * Generate filename for PDF
     *
     * @return string
     */
    private function generateFilename()
    {
        $lastName = preg_replace('/[^a-zA-Z0-9]/', '', $this->customerData['last_name'] ?? 'Customer');
        $date = date('Y-m-d');

        return "Porting_LOA_{$lastName}_{$date}.pdf";
    }

    /**
     * Escape HTML entities
     *
     * @param string $text
     * @return string
     */
    private function escape($text)
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get Customer2.AI logo as base64
     *
     * @return string
     */
    private function getLogoBase64()
    {
        // Simple SVG logo
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 50">
    <text x="10" y="35" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#000">
        CUSTOMER<tspan fill="#00838F">2.AI</tspan>
    </text>
    <text x="10" y="45" font-family="Arial, sans-serif" font-size="8" fill="#666">
        AI-DRIVEN. HUMAN-FOCUSED.
    </text>
</svg>
SVG;

        return base64_encode($svg);
    }

    /**
     * Save PDF to file
     *
     * @param string $directory Directory to save to
     * @return array ['success' => bool, 'filepath' => string|null, 'error' => string|null]
     */
    public function saveToFile($directory)
    {
        $result = $this->generate();

        if (!$result['success']) {
            return $result;
        }

        try {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filepath = rtrim($directory, '/') . '/' . $result['filename'];
            file_put_contents($filepath, $result['pdf']);

            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $result['filename'],
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'filepath' => null,
                'filename' => null,
                'error' => 'Failed to save PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get PDF as base64 string (for email attachments)
     *
     * @return array ['success' => bool, 'base64' => string|null, 'filename' => string, 'error' => string|null]
     */
    public function getBase64()
    {
        $result = $this->generate();

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'base64' => base64_encode($result['pdf']),
            'filename' => $result['filename'],
            'error' => null
        ];
    }
}
