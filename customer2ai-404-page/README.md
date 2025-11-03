# Customer2 Custom 404 Page Plugin

A custom WordPress plugin that creates a beautiful, space-themed 404 error page for Customer2 AI website.

## Features

- **Custom 404 Design**: Modern, space-themed error page with floating robot astronaut
- **Fully Responsive**: Works perfectly on desktop, tablet, and mobile devices
- **Customizable Social Links**: Add LinkedIn, Facebook, and Instagram links
- **SEO Friendly**: Proper meta tags and noindex/nofollow for error pages
- **Easy Configuration**: Simple settings page in WordPress admin
- **Matches Brand**: Integrates seamlessly with your existing Customer2 AI branding

## Installation

### Method 1: Upload via WordPress Admin
1. Download the plugin folder as a ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

### Method 2: FTP Upload
1. Upload the entire `customer2-404-page` folder to `/wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Activate "Customer2 Custom 404 Page"

## Configuration

After activation, configure the plugin:

1. Go to **Settings → Custom 404 Page** in WordPress admin
2. Enter your social media URLs:
   - LinkedIn URL
   - Facebook URL
   - Instagram URL
3. Optionally customize the "Home Page URL" for the Return Home button
4. Click "Save Changes"

## File Structure

```
customer2-404-page/
├── customer2-404-page.php    # Main plugin file
├── README.md                  # This file
├── templates/
│   └── 404-template.php      # 404 page HTML template
├── assets/
│   ├── css/
│   │   └── 404-style.css     # Stylesheet
│   └── images/
│       └── robot-space.webp  # Space robot image
```

## Customization

### Changing Colors
Edit `assets/css/404-style.css` and modify the color values:
- Background gradient: `.c2-404-container` 
- Button colors: `.c2-404-home-btn` and `.dashboard-btn`
- Text colors: Various classes

### Changing Navigation Links
Edit `templates/404-template.php` and modify the navigation menu items in the header section.

### Changing Footer Content
Edit `templates/404-template.php` to update:
- Footer address
- Footer links
- Copyright text

### Using a Different Image
Replace `assets/images/robot-space.webp` with your own image (recommended size: 1024x1024px, WebP or PNG format).

## Technical Details

- **WordPress Version**: 5.0 or higher
- **PHP Version**: 7.4 or higher
- **License**: GPL v2 or later

## Features Breakdown

### Header
- Logo display (uses your site's custom logo)
- Navigation menu
- Dashboard button

### Main Content
- Large "404" text with image visible through it
- "PAGE NOT FOUND" heading
- Friendly error message
- "Return Home" call-to-action button

### Social Media Section
- Configurable social media icons
- LinkedIn, Facebook, and Instagram support
- Opens in new tab with proper security attributes

### Footer
- Company information
- Multi-column link structure
- Address display
- Social media icons
- Copyright notice

## Browser Support

- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Troubleshooting

### 404 page doesn't show
1. Make sure the plugin is activated
2. Clear your WordPress cache
3. If using a caching plugin, clear its cache
4. Try deactivating other plugins that might conflict

### Image not displaying
1. Check that the image file exists in `assets/images/`
2. Verify file permissions (should be 644)
3. Clear browser cache

### Styles not applying
1. Clear browser cache
2. Check that the CSS file is being loaded (view page source)
3. Try disabling other plugins that might override styles

## Support

For issues or questions:
- Check the WordPress error log
- Ensure your WordPress installation meets minimum requirements
- Contact your web developer for custom modifications

## Changelog

### Version 1.0.0
- Initial release
- Space-themed 404 page design
- Social media integration
- Responsive design
- Admin settings page

## Credits

- Design: Customer2 AI Design Team
- Development: Custom WordPress Plugin
- Robot Space Image: AI Generated

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2025 Customer2 AI

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```
