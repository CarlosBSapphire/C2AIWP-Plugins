# Letter of Authorization (LOA) PDF Generation - Integration Guide

## 📋 Overview

This guide shows you how to integrate the LOA (Letter of Authorization) PDF generation into your AI Products Order Widget plugin. The LOA matches the Customer2AI format exactly.

---

## 🎯 What You're Adding

1. **LOA PDF Generation** - Creates professional porting authorization letters
2. **Dynamic Phone Number Fields** - Users can add multiple numbers
3. **Service Address Collection** - Required for LOA
4. **Dual PDF Output** - Both order receipt AND LOA when porting

---

## 📦 Files Provided

| File | Purpose |
|------|---------|
| `loa-pdf-generation.php` | PHP functions for LOA PDF generation |
| `loa-form-fields.html` | Updated HTML form fields |
| `LOA-INTEGRATION-GUIDE.md` | This file |

---

## 🔧 Step-by-Step Integration

### Step 1: Add LOA Generation Methods

Open your `ai-products-order-widget.php` and add these three methods to the `AI_Products_Order_Widget` class:

```php
/**
 * Generate LOA PDF for number porting
 */
private function generate_loa_pdf($order_data, $user_data) {
    // Copy from loa-pdf-generation.php
}

/**
 * Generate HTML for LOA PDF
 */
private function generate_loa_html($order_data, $user_data) {
    // Copy from loa-pdf-generation.php
}
```

**Where to add:** After the existing `generate_pdf_html()` method (around line 780)

---

### Step 2: Update generate_order_pdf() Method

**Find this method (around line 665):**
```php
private function generate_order_pdf($order_data, $user_data) {
    // ... existing code ...
    
    return $filepath;  // ← Currently returns single path
}
```

**Replace the return statement with:**
```php
// Save order PDF
$filename = 'order_' . $order_data['user_id'] . '_' . time() . '.pdf';
$filepath = $pdf_dir . '/' . $filename;

file_put_contents($filepath, $dompdf->output());

// Also generate LOA if porting numbers
$loa_path = null;
if (!empty($order_data['ai_calls']['porting']) && $order_data['ai_calls']['porting'] === 'yes') {
    $loa_path = $this->generate_loa_pdf($order_data, $user_data);
}

return [
    'order_pdf' => $filepath,
    'loa_pdf' => $loa_path
];
```

---

### Step 3: Update handle_order_submission() Method

**Find this section (around line 615):**
```php
// Generate PDF
$pdf_path = $this->generate_order_pdf($order_data, $user_data);

wp_send_json_success([
    'message' => 'Order submitted successfully!',
    'user_id' => $user_id,
    'pdf_url' => $pdf_path ? WP_CONTENT_URL . '/uploads/ai-orders/' . basename($pdf_path) : null
]);
```

**Replace with:**
```php
// Generate PDF(s)
$pdf_paths = $this->generate_order_pdf($order_data, $user_data);

$response = [
    'message' => 'Order submitted successfully!',
    'user_id' => $user_id
];

// Add order PDF URL
if (!empty($pdf_paths['order_pdf'])) {
    $response['pdf_url'] = WP_CONTENT_URL . '/uploads/ai-orders/' . basename($pdf_paths['order_pdf']);
}

// Add LOA PDF URL if generated
if (!empty($pdf_paths['loa_pdf'])) {
    $response['loa_url'] = WP_CONTENT_URL . '/uploads/ai-orders/' . basename($pdf_paths['loa_pdf']);
}

wp_send_json_success($response);
```

---

### Step 4: Update Form HTML

**Find the "Porting Yes Options" section (around line 225):**

```php
<!-- Porting Yes Options -->
<div id="porting-yes-options" class="aipw-conditional" style="display:none;">
    <h3>Porting Configuration</h3>
    <!-- OLD fields here -->
</div>
```

**Replace entire section with content from `loa-form-fields.html`**

---

### Step 5: Update JavaScript Form Submission Handler

**Find the JavaScript success handler (around line 470):**

```javascript
if (data.success) {
    messageDiv.textContent = data.data.message || 'Order submitted successfully!';
    messageDiv.style.color = 'green';
    messageDiv.style.display = 'block';
    
    // Provide PDF download link if available
    if (data.data.pdf_url) {
        const pdfLink = document.createElement('a');
        pdfLink.href = data.data.pdf_url;
        pdfLink.textContent = 'Download PDF Receipt';
        pdfLink.style.display = 'block';
        pdfLink.style.marginTop = '10px';
        messageDiv.appendChild(pdfLink);
    }
}
```

**Replace with:**
```javascript
if (data.success) {
    messageDiv.textContent = data.data.message || 'Order submitted successfully!';
    messageDiv.style.color = 'green';
    messageDiv.style.display = 'block';
    
    // Provide Order PDF download link
    if (data.data.pdf_url) {
        const pdfLink = document.createElement('a');
        pdfLink.href = data.data.pdf_url;
        pdfLink.textContent = '📄 Download Order Receipt';
        pdfLink.style.display = 'block';
        pdfLink.style.marginTop = '10px';
        pdfLink.style.fontWeight = 'bold';
        messageDiv.appendChild(pdfLink);
    }
    
    // Provide LOA PDF download link if porting
    if (data.data.loa_url) {
        const loaLink = document.createElement('a');
        loaLink.href = data.data.loa_url;
        loaLink.textContent = '📋 Download Letter of Authorization (LOA)';
        loaLink.style.display = 'block';
        loaLink.style.marginTop = '5px';
        loaLink.style.fontWeight = 'bold';
        messageDiv.appendChild(loaLink);
    }
}
```

---

### Step 6: Add CSS Styles

Add the CSS from `loa-form-fields.html` to your `assets/css/widget-styles.css`:

```css
/* LOA Form Styles */
.aipw-field-group {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border-left: 4px solid #0066FF;
}

.aipw-field-group h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #333;
}

/* ... rest of styles from loa-form-fields.html */
```

---

## 🧪 Testing Checklist

### Basic Functionality
- [ ] Form displays correctly
- [ ] Phone number count field works
- [ ] Dynamic phone fields are added/removed based on count
- [ ] Service address fields are required
- [ ] Business name field is optional

### PDF Generation
- [ ] Order receipt PDF generates
- [ ] LOA PDF generates when porting = yes
- [ ] LOA PDF does NOT generate when porting = no
- [ ] Both PDFs have download links in success message

### LOA PDF Content
- [ ] Logo/header displays correctly
- [ ] Customer name appears
- [ ] Business name appears (if provided)
- [ ] Service address is complete
- [ ] Phone numbers are listed
- [ ] Service provider appears
- [ ] Authorization text is complete
- [ ] Footer note about toll-free numbers appears

### Edge Cases
- [ ] Works with 1 phone number
- [ ] Works with 10+ phone numbers
- [ ] Works without business name
- [ ] Handles missing service provider gracefully

---

## 📊 Data Flow

```
User Fills Form
    ↓
Selects "Porting: Yes"
    ↓
Additional Fields Appear:
  - Service Address
  - Business Name (optional)
  - Current Provider
  - Phone Numbers (dynamic)
    ↓
User Submits Form
    ↓
PHP Processes:
  ├── Creates User Account (API)
  ├── Generates Order Receipt PDF
  └── Generates LOA PDF (if porting)
    ↓
Returns Both PDF URLs
    ↓
User Downloads:
  ├── Order Receipt
  └── LOA (for signing)
```

---

## 🎨 LOA PDF Layout

```
┌────────────────────────────────────┐
│        CUSTOMER2AI Logo            │
│    AI-DRIVEN. HUMAN-FOCUSED.       │
├────────────────────────────────────┤
│                                    │
│  PORTING LETTER OF AUTHORIZATION   │
│                                    │
│  1. Customer Name                  │
│     [First] [Last]                 │
│     [Business Name]                │
│                                    │
│  2. Service Address                │
│     [Address]                      │
│     [City] [State] [Zip]           │
│                                    │
│  3. Phone Numbers to Port          │
│     ┌─────────────┬──────────────┐│
│     │ Number      │ Provider     ││
│     ├─────────────┼──────────────┤│
│     │ (555)123... │ Verizon      ││
│     │ (555)456... │ Verizon      ││
│     └─────────────┴──────────────┘│
│                                    │
│  Authorization Text...             │
│                                    │
│  ____________  ____________  _____ │
│  Signature     Print        Date   │
│                                    │
│  [Toll-free RespOrg Note]          │
└────────────────────────────────────┘
```

---

## 💡 Customization Options

### Change Company Name in LOA

**In `generate_loa_html()` method, find:**
```php
<div class="logo-text">CUSTOMER2AI</div>
```

**Change to your company name:**
```php
<div class="logo-text">YOUR COMPANY</div>
```

**Also update in authorization text:**
```php
I authorize <strong>Customer2AI</strong>
```

### Change Color Scheme

**In the LOA HTML `<style>` section:**
```css
.logo-text {
    color: #00A3E0;  /* ← Change this color */
}

.divider {
    border-top: 3px solid #000;  /* ← Change border color */
}
```

### Add Company Logo Image

**Replace the text logo with an image:**
```php
<div class="logo-container">
    <img src="<?php echo AIPW_PLUGIN_URL; ?>assets/images/logo.png" 
         alt="Company Logo" 
         style="max-width: 200px; height: auto;">
</div>
```

---

## 🐛 Troubleshooting

### Issue: PDFs not generating

**Check:**
1. dompdf installed: `composer require dompdf/dompdf`
2. Uploads directory writable: `wp-content/uploads/ai-orders/`
3. PHP memory limit sufficient (at least 128MB)

**Fix permissions:**
```bash
chmod 755 wp-content/uploads/ai-orders/
```

### Issue: LOA PDF blank or incomplete

**Check:**
1. All required order data present
2. No PHP errors in error log
3. dompdf options set correctly

**Debug:**
```php
// Add before generating PDF
error_log('Order Data: ' . print_r($order_data, true));
error_log('User Data: ' . print_r($user_data, true));
```

### Issue: Dynamic phone fields not appearing

**Check:**
1. JavaScript loaded correctly
2. Browser console for errors
3. Element IDs match between HTML and JS

**Test in console:**
```javascript
document.getElementById('phone-numbers-container')
// Should return element, not null
```

### Issue: LOA formatting looks wrong

**Check:**
1. CSS is being applied
2. dompdf version compatible
3. HTML structure valid

**Force CSS inline (if needed):**
```php
$options->set('defaultMediaType', 'screen');
$options->set('isPhpEnabled', true);
```

---

## 📋 Sample Order Data Structure

After integration, your order data should look like this:

```php
$order_data = [
    'user_id' => 12345,
    'products' => ['ai_calls'],
    'ai_calls' => [
        'porting' => 'yes',
        'port_numbers_count' => 3,
        'port_numbers' => [
            [
                'number' => '+1 (555) 123-4567',
                'provider' => 'Verizon'
            ],
            [
                'number' => '+1 (555) 234-5678',
                'provider' => 'Verizon'
            ],
            [
                'number' => '+1 (555) 345-6789',
                'provider' => 'AT&T'
            ]
        ],
        'service_address' => '123 Main St',
        'service_city' => 'Los Angeles',
        'service_state' => 'CA',
        'service_zip' => '90001',
        'current_provider' => 'Verizon',
        'business_name' => 'Acme Corp',  // optional
        'agent_number' => '+1 (555) 999-8888'
    ]
];
```

---

## ✅ Completion Checklist

After integration, verify:

- [ ] All PHP methods added to class
- [ ] `generate_order_pdf()` returns array with both PDFs
- [ ] `handle_order_submission()` handles both PDF URLs
- [ ] Form has updated HTML fields
- [ ] CSS styles added
- [ ] JavaScript handles dynamic fields
- [ ] Success message shows both download links
- [ ] Tested with porting = yes
- [ ] Tested with porting = no
- [ ] LOA PDF matches uploaded example
- [ ] Dynamic phone fields work
- [ ] Service address fields work
- [ ] Business name (optional) works

---

## 🚀 Production Checklist

Before deploying to production:

- [ ] Test with real phone numbers
- [ ] Verify LOA is legally compliant
- [ ] Check PDF file sizes reasonable
- [ ] Ensure secure file storage
- [ ] Add file cleanup cron job (optional)
- [ ] Test with different browsers
- [ ] Test on mobile devices
- [ ] Review generated PDFs with legal team
- [ ] Set up PDF backup system
- [ ] Document for your team

---

## 📞 Support

If you run into issues:

1. Check PHP error log
2. Check browser console
3. Verify dompdf installed
4. Test with minimal data
5. Compare with sample data structure

---

## 🎉 You're Done!

Your plugin now generates professional LOA documents for number porting!

**Test it:**
1. Fill out the form
2. Select "Porting: Yes"
3. Fill in service address
4. Add phone numbers
5. Submit
6. Download both PDFs

Enjoy! 🚀