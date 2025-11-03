# Customer2AI Maintenance Mode Plugin

A professional WordPress maintenance mode plugin for Customer2AI with a sleek, modern design featuring the robot mascot.

## Features

- ✅ Beautiful maintenance page matching Customer2AI branding
- ✅ Fully responsive design (desktop, tablet, mobile)
- ✅ Easy-to-use admin settings panel
- ✅ Customizable title and message
- ✅ Social media links (LinkedIn, Facebook, Instagram)
- ✅ Administrators can still access the site while maintenance mode is active
- ✅ Proper 503 HTTP status code for SEO
- ✅ Matches the exact design from the provided PDF

## Installation

### Method 1: Manual Installation (Recommended)

1. Download the `customer2ai-maintenance-mode` folder
2. Upload it to your WordPress site's `/wp-content/plugins/` directory
3. Log into your WordPress admin panel
4. Go to **Plugins** → **Installed Plugins**
5. Find "Customer2AI Maintenance Mode" and click **Activate**

### Method 2: ZIP Upload

1. Compress the `customer2ai-maintenance-mode` folder into a ZIP file
2. Log into your WordPress admin panel
3. Go to **Plugins** → **Add New** → **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

## Configuration

After activation:

1. Go to **Settings** → **Maintenance Mode** in your WordPress admin
2. Configure the following options:
   - **Enable Maintenance Mode**: Check this box to activate maintenance mode
   - **Page Title**: Customize the main heading (default: "UNDER MAINTENANCE")
   - **Message**: Customize the message below the title
   - **Social Media Links**: Add your LinkedIn, Facebook, and Instagram URLs
   - **Show Social Media Icons**: Toggle social icons visibility

3. Click **Save Changes**

## Usage

### Enabling Maintenance Mode

1. Navigate to **Settings** → **Maintenance Mode**
2. Check the "Enable Maintenance Mode" checkbox
3. Click **Save Changes**

Your site will now show the maintenance page to all non-admin visitors.

### Admin Access

When maintenance mode is enabled:
- **Administrators** can still access the full site normally
- **Logged-in users with admin privileges** bypass the maintenance page
- **All other visitors** see the maintenance page

### Testing

To test the maintenance page:
1. Enable maintenance mode in the settings
2. Open your site in an **incognito/private browser window** (to simulate a non-logged-in visitor)
3. You should see the maintenance page

## Features Overview

### Header Navigation
- Customer2AI logo
- Navigation menu (Platform, Features, Testimonials, FAQ, Contact, Blog)
- Dashboard button

### Main Content
- Large "UNDER MAINTENANCE" heading
- Customizable message
- Robot mascot image with circular frame and caution tape overlay
- Social media icons (optional)

### Footer
- Customer2AI logo and address
- Company links
- Support links
- Legal links
- Social media icons
- Copyright notice

## Technical Details

- **PHP Version**: 7.4 or higher
- **WordPress Version**: 5.0 or higher
- **License**: GPL v2 or later
- **Color Scheme**: Dark blue gradient background with orange accents
- **Font**: Inter (loaded from Google Fonts)

## File Structure

```
customer2ai-maintenance-mode/
├── customer2ai-maintenance-mode.php    # Main plugin file
├── assets/
│   └── robot.webp                      # Robot mascot image
└── README.md                            # This file
```

## Customization

### Changing Colors

Edit the CSS in the `display_maintenance_page()` method:
- Background gradient: `.background: linear-gradient(180deg, #0a1628 0%, #1a2844 100%);`
- Orange accent: `#ff8c00` and `#ffa500`
- Blue accent: `#2563eb`

### Changing the Robot Image

Replace `/assets/robot.webp` with your own image (recommended: 1024x1024px, WebP or PNG format)

### Adding Custom Content

Modify the `display_maintenance_page()` method in the main plugin file to add additional HTML content.

## Support

For support or customization requests, please contact Customer2AI support.

## Changelog

### Version 1.0.0
- Initial release
- Full-featured maintenance mode with Customer2AI branding
- Admin settings panel
- Responsive design
- Social media integration

## Credits

- Design based on Customer2AI brand guidelines
- Robot image included in plugin assets
- Icons from Simple Icons (social media)

## License

This plugin is licensed under the GPL v2 or later.
