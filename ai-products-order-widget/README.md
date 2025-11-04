# AI Products Order Widget

A comprehensive WordPress plugin for managing AI product orders including AI Calls, AI Emails, and AI Chat with various addons and automatic user account creation.

## Features

- **Multiple Product Support**: AI Calls (Phone), AI Emails, and AI Chat
- **Addon System**: QA, AVS Match, Custom Package, Phone Numbers, Lead Verification, Transcription & Recordings
- **Advanced Call Configuration**: Support for forwarding, standard setup, and multi-agent configurations
- **Port Management**: Handle number porting with automatic PDF generation
- **Web Storage Persistence**: Saves user selections in localStorage to persist across page navigation
- **PDF Generation**: Automatic order receipt generation using dompdf
- **API Integration**: Creates users via n8n webhook endpoints
- **AJAX Form Submission**: No page reload required

## Installation

### 1. Install the Plugin

Upload the plugin files to your WordPress installation:

```bash
wp-content/plugins/ai-products-order-widget/
├── ai-products-order-widget.php
├── composer.json
├── vendor/ (after running composer install)
└── README.md
```

### 2. Install Dependencies

Navigate to the plugin directory and run:

```bash
cd wp-content/plugins/ai-products-order-widget/
composer install
```

This will install dompdf and its dependencies.

### 3. Activate the Plugin

1. Go to WordPress Admin > Plugins
2. Find "AI Products Order Widget"
3. Click "Activate"

### 4. Create Upload Directory

The plugin will automatically create the required directories, but you can manually ensure they exist:

```bash
mkdir -p wp-content/uploads/ai-orders
chmod 755 wp-content/uploads/ai-orders
```

## Usage

### Adding the Widget to a Page

Use the shortcode on any page or post:

```
[ai_products_widget]
```

The widget will take up the entire page width and display a comprehensive order form.

### Form Structure

The widget includes the following sections:

1. **User Information**
   - First Name, Last Name, Email, Username, Password

2. **Product Selection**
   - AI Calls (Phone)
   - AI Emails
   - AI Chat

3. **Product-Specific Configuration**
   - Each product has its own configuration section
   - Sections appear/hide based on product selection

4. **AI Calls Configuration** (when selected)
   - Inbound/Outbound setup
   - Call setup type (Forwarding, Standard, Multi-forward)
   - Agent level (Basic, Advanced, Professional)
   - Number porting options
   - Agent number configuration

5. **AI Emails Configuration** (when selected)
   - Domain configuration
   - Expected email volume

6. **AI Chat Configuration** (when selected)
   - Website URL
   - Expected concurrent chats

7. **Addons Selection**
   - QA
   - AVS Match
   - Custom Package
   - Phone Numbers
   - Lead Verification
   - Transcription & Recordings

8. **Additional Notes**
   - Free text field for special requirements

## Web Storage (localStorage)

The plugin automatically saves all form data to the browser's localStorage under the key `aipw_order_data`. This means:

- Users can navigate away and return without losing their selections
- Form data persists across browser sessions
- Data is cleared upon successful submission

### Storage Format

```javascript
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "products": ["ai_calls", "ai_emails"],
  "addons": ["qa", "transcription_recordings"],
  "ai_calls": {
    "inbound_outbound": "both",
    "setup_type": "standard",
    "agent_level": "professional",
    "porting": "yes",
    "port_numbers_count": "5",
    "agent_number": "+1234567890"
  }
}
```

## API Integration

### User Creation Endpoint

**Endpoint**: `https://n8n.workflows.organizedchaos.cc/webhook/users/create`

**Method**: POST

**Request Body**:
```json
{
  "username": "user@example.com",
  "email": "user@example.com",
  "password": "securepassword",
  "first_name": "John",
  "last_name": "Doe",
  "role": "user",
  "status": "active",
  "account_status": "active"
}
```

**Expected Response**:
```json
{
  "success": true,
  "user_id": 12345
}
```

### Data Selection Endpoint

**Endpoint**: `https://n8n.workflows.organizedchaos.cc/webhook/da176ae9-496c-4f08-baf5-6a78a6a42adb`

**Method**: POST

**Request Body**:
```json
{
  "table_name": "users",
  "columns": ["column_1", "column_2"],
  "filters": {
    "filter_key_1": "filter_value_1"
  },
  "page": 1,
  "limit": 50
}
```

## PDF Generation

The plugin automatically generates a PDF receipt upon successful order submission using dompdf. The PDF includes:

- Customer information
- Products ordered
- Product-specific configurations
- Selected addons
- Additional notes
- Order timestamp

PDFs are saved to: `wp-content/uploads/ai-orders/order_{user_id}_{timestamp}.pdf`

## Form Flow

### AI Calls Product Flow

1. User selects "AI Calls (Phone)" product
2. Configuration section appears with:
   - Inbound/Outbound selection
   - Setup type selection
   - Agent level selection
   - Porting question

3. If "Are You Porting?" = Yes:
   - Show port numbers count field
   - Show agent number field
   - Show whitelabel option
   - Generate PDF Port Cloning letter (if whitelabel selected)
   - Send ticket to Trello

4. If "Are You Porting?" = No:
   - Show new numbers count field
   - Show agent number field
   - No port letter needed

## Customization

### Adding New Products

Edit the `$products` array in the main plugin class:

```php
private $products = [
    'new_product' => [
        'name' => 'New Product Name',
        'slug' => 'new_product',
        'has_phone_setup' => false
    ]
];
```

### Adding New Addons

Edit the `$addons` array:

```php
private $addons = [
    'new_addon' => 'New Addon Name'
];
```

### Modifying PDF Template

The PDF template is generated in the `generate_pdf_html()` method. You can customize the HTML and CSS there.

## JavaScript Events

The plugin triggers the following JavaScript events:

- Form change/input: Saves data to localStorage
- Product checkbox change: Shows/hides configuration sections
- Porting radio change: Shows/hides porting options
- Form submit: Sends AJAX request and generates PDF

## Security

- WordPress nonce verification on form submission
- Data sanitization for all user inputs
- Password is not sanitized (preserved as entered)
- AJAX endpoints are properly secured
- File uploads directory is protected

## AJAX Response

### Success Response

```json
{
  "success": true,
  "data": {
    "message": "Order submitted successfully!",
    "user_id": 12345,
    "pdf_url": "https://yoursite.com/wp-content/uploads/ai-orders/order_12345_1234567890.pdf"
  }
}
```

### Error Response

```json
{
  "success": false,
  "data": {
    "message": "Error message here"
  }
}
```

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Composer (for dependency management)
- Write permissions for wp-content/uploads directory

## Troubleshooting

### PDF Not Generating

1. Check that the uploads directory exists and is writable:
   ```bash
   chmod 755 wp-content/uploads/ai-orders
   ```

2. Verify dompdf is installed:
   ```bash
   cd wp-content/plugins/ai-products-order-widget
   composer install
   ```

### Form Data Not Persisting

1. Check browser console for localStorage errors
2. Ensure localStorage is enabled in the browser
3. Check browser storage limits (typically 5-10MB)

### API Connection Issues

1. Verify the API endpoints are accessible
2. Check that the server can make outbound HTTP requests
3. Review server error logs for connection issues

## Future Enhancements

- Custom CSS/JS file support (currently inline)
- Database storage for orders
- Email notifications
- Admin dashboard for order management
- Payment gateway integration
- Multi-step form wizard
- Conditional logic for addons based on products

## Support

For issues or questions, please contact the development team at Organized Chaos.

## License

GPL v2 or later