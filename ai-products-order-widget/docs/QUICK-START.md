# Quick Start Guide - AI Products Order Widget

## Installation Steps (5 minutes)

### 1. Upload Files to WordPress
Upload these files to: `wp-content/plugins/ai-products-order-widget/`

**Required Files:**
- `ai-products-order-widget.php` (main plugin file)
- `composer.json` (dependencies)

### 2. Install Dependencies
```bash
cd wp-content/plugins/ai-products-order-widget/
composer install
```

### 3. Activate Plugin
Go to WordPress Admin → Plugins → Activate "AI Products Order Widget"

### 4. Add to Page
Add this shortcode to any page:
```
[ai_products_widget]
```

## ✅ You're Done! The widget is now live.

---

## What's Included

### Core Features (Ready to Use)
✅ **Product Selection**: AI Calls, AI Emails, AI Chat
✅ **Addon System**: 6 different addons
✅ **User Account Creation**: Automatic via n8n API
✅ **AI Calls Configuration**: 
   - Inbound/Outbound setup
   - Call routing types
   - Agent levels
   - Number porting workflow
✅ **Web Storage Persistence**: Uses localStorage
✅ **PDF Generation**: Automatic order receipts
✅ **AJAX Submission**: No page reload

### Files Provided

#### 1. **ai-products-order-widget.php** (Main Plugin)
- Complete working plugin
- All form logic
- Web storage JavaScript
- API integration
- PDF generation

#### 2. **composer.json**
- Manages dompdf dependency

#### 3. **README.md**
- Full documentation
- API endpoint details
- Usage examples

#### 4. **config.php** (Optional - Future Use)
- Centralized configuration
- Easy customization
- Product/addon pricing
- Feature toggles

#### 5. **database-helper.php** (Optional - Future Use)
- WordPress database tables
- CRUD operations for orders
- Ready to integrate when needed

#### 6. **api-helper.php** (Optional - Future Use)
- Additional API methods
- Helper functions for n8n endpoints
- Usage examples

#### 7. **styles-template.css** (For Later)
- CSS template for styling
- Ready to customize

---

## How It Works

### User Flow
1. User fills out form (name, email, username, password)
2. User selects products (AI Calls, Emails, Chat)
3. Product-specific config appears (dynamic)
4. User selects addons
5. User submits form

### Backend Flow
1. Form data saved to localStorage (automatic)
2. AJAX submission to WordPress
3. User created via n8n API
4. PDF receipt generated
5. Success response with download link

### Data Persistence
- All form data saved to `localStorage` under key: `aipw_order_data`
- Survives page navigation
- Cleared on successful submission

---

## Customization Points

### Add New Products
Edit in `ai-products-order-widget.php`:
```php
private $products = [
    'new_product' => [
        'name' => 'New Product',
        'slug' => 'new_product',
        'has_phone_setup' => false
    ]
];
```

### Add New Addons
```php
private $addons = [
    'new_addon' => 'New Addon Name'
];
```

### Modify API Endpoints
```php
const API_SELECT = 'your-endpoint';
const API_CREATE_USER = 'your-endpoint';
```

---

## AI Calls Product Flow

### When User Selects "AI Calls"
1. **Shows Configuration:**
   - Inbound/Outbound selection
   - Setup type (Forwarding, Standard, Multi-forward)
   - Agent level (Basic, Advanced, Professional)
   - "Are you porting?" question

2. **If Porting = YES:**
   - Port numbers count field
   - Agent number field
   - Whitelabel checkbox (for PDF port cloning)
   - *Note: Whitelabel creates Trello ticket (implement separately)*

3. **If Porting = NO:**
   - New numbers count field
   - Agent number field
   - No port letter generated

---

## API Integration

### User Creation
**Endpoint:** `https://n8n.workflows.organizedchaos.cc/webhook/users/create`

**Payload:**
```json
{
  "username": "user@example.com",
  "email": "user@example.com",
  "password": "password123",
  "first_name": "John",
  "last_name": "Doe",
  "role": "user",
  "status": "active",
  "account_status": "active"
}
```

### Data Selection
**Endpoint:** `https://n8n.workflows.organizedchaos.cc/webhook/da176ae9-496c-4f08-baf5-6a78a6a42adb`

**Payload:**
```json
{
  "table_name": "users",
  "columns": ["id", "email"],
  "filters": {
    "email": "user@example.com"
  }
}
```

---

## Next Steps

### Immediate (No Code Changes)
1. ✅ Install plugin
2. ✅ Add shortcode to page
3. ✅ Test form submission

### Short Term (When Ready)
1. **Add Custom CSS** - Use `styles-template.css` as starting point
2. **Add Custom JavaScript** - Enhance form validation
3. **Configure PDF** - Add logo, customize template

### Medium Term
1. **Database Storage** - Use `database-helper.php` to store orders in WordPress
2. **Admin Dashboard** - View/manage orders
3. **Email Notifications** - Send order confirmations
4. **Trello Integration** - Auto-create tickets for porting

### Long Term
1. **Payment Gateway** - Stripe/PayPal integration
2. **Multi-step Form** - Break into wizard
3. **Advanced Analytics** - Track conversions
4. **Customer Portal** - View order history

---

## Troubleshooting

### Form Not Appearing
- Check shortcode is correct: `[ai_products_widget]`
- Verify plugin is activated
- Check for JavaScript errors in console

### User Creation Failing
- Verify API endpoint is accessible
- Check n8n webhook is active
- Review error message in response

### PDF Not Generating
- Ensure dompdf is installed (`composer install`)
- Check uploads directory is writable
- Look for PHP errors in logs

### localStorage Not Working
- Ensure browser supports localStorage
- Check browser storage isn't full
- Verify no browser extensions blocking storage

---

## File Structure

```
wp-content/plugins/ai-products-order-widget/
├── ai-products-order-widget.php    ← Main plugin (REQUIRED)
├── composer.json                    ← Dependencies (REQUIRED)
├── vendor/                          ← Created by composer (REQUIRED)
├── README.md                        ← Documentation
├── config.php                       ← Configuration (optional)
├── database-helper.php              ← DB operations (optional)
├── api-helper.php                   ← API helpers (optional)
└── styles-template.css              ← CSS template (optional)
```

---

## Key Features Explained

### Web Storage (localStorage)
- **Key:** `aipw_order_data`
- **Saves:** All form fields automatically
- **Triggers:** On any input/change event
- **Loads:** On page load
- **Clears:** On successful submission

### Dynamic Form Sections
- Product checkboxes show/hide config sections
- Porting radio buttons toggle options
- All sections hidden by default

### PDF Receipt
- Generated automatically on submission
- Saved to: `wp-content/uploads/ai-orders/`
- Download link provided in success message
- Includes all order details

### AJAX Submission
- No page reload
- Real-time validation
- Success/error messages
- Disables submit button during processing

---

## Support & Contact

For issues or questions:
- Review README.md for detailed documentation
- Check troubleshooting section
- Contact: Organized Chaos development team

---

## License

GPL v2 or later

**Version:** 1.0.0  
**Last Updated:** October 30, 2025  
**Compatibility:** WordPress 5.0+, PHP 7.4+