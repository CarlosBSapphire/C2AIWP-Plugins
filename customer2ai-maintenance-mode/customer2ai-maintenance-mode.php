<?php
/**
 * Plugin Name: Customer2AI Maintenance Mode
 * Plugin URI: https://customer2ai.com
 * Description: A custom maintenance mode page for Customer2AI with a sleek design
 * Version: 1.0.0
 * Author: Customer2AI
 * Author URI: https://customer2ai.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('C2AI_MAINTENANCE_VERSION', '1.0.0');
define('C2AI_MAINTENANCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('C2AI_MAINTENANCE_PLUGIN_URL', plugin_dir_url(__FILE__));

class Customer2AI_Maintenance_Mode {
    
    private $option_name = 'c2ai_maintenance_mode';
    
    public function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Frontend hooks
        add_action('template_redirect', array($this, 'show_maintenance_page'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $default_options = array(
            'enabled' => false,
            'title' => 'UNDER MAINTENANCE',
            'message' => 'We apologize for the inconvenience, we will be back shortly.',
            'show_social' => true,
            'linkedin_url' => '#',
            'facebook_url' => '#',
            'instagram_url' => '#'
        );
        
        if (!get_option($this->option_name)) {
            add_option($this->option_name, $default_options);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Maintenance Mode Settings',
            'Maintenance Mode',
            'manage_options',
            'c2ai-maintenance-mode',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('c2ai_maintenance_settings', $this->option_name);
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option($this->option_name);
        // Set defaults if options don't exist or are false
        if ($options === false || !is_array($options)) {
            $options = array(
                'enabled' => false,
                'title' => 'UNDER MAINTENANCE',
                'message' => 'We apologize for the inconvenience, we will be back shortly.',
                'show_social' => true,
                'linkedin_url' => '#',
                'facebook_url' => '#',
                'instagram_url' => '#'
            );
        }
        
        // Ensure all keys exist with defaults
        $options = wp_parse_args($options, array(
            'enabled' => false,
            'title' => 'UNDER MAINTENANCE',
            'message' => 'We apologize for the inconvenience, we will be back shortly.',
            'show_social' => true,
            'linkedin_url' => '#',
            'facebook_url' => '#',
            'instagram_url' => '#'
        ));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('c2ai_maintenance_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enabled">Enable Maintenance Mode</label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="enabled" 
                                   name="<?php echo $this->option_name; ?>[enabled]" 
                                   value="1" 
                                   <?php checked(isset($options['enabled']) ? $options['enabled'] : false, 1); ?>>
                            <p class="description">When enabled, visitors will see the maintenance page. Administrators can still access the site.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="title">Page Title</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="title" 
                                   name="<?php echo $this->option_name; ?>[title]" 
                                   value="<?php echo esc_attr($options['title']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="message">Message</label>
                        </th>
                        <td>
                            <textarea id="message" 
                                      name="<?php echo $this->option_name; ?>[message]" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea($options['message']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Social Media Links</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="<?php echo $this->option_name; ?>[show_social]" 
                                       value="1" 
                                       <?php checked(isset($options['show_social']) ? $options['show_social'] : true, 1); ?>>
                                Show Social Media Icons
                            </label>
                            <br><br>
                            <label for="linkedin_url">LinkedIn URL:</label><br>
                            <input type="url" 
                                   id="linkedin_url" 
                                   name="<?php echo $this->option_name; ?>[linkedin_url]" 
                                   value="<?php echo esc_attr($options['linkedin_url']); ?>" 
                                   class="regular-text">
                            <br><br>
                            <label for="facebook_url">Facebook URL:</label><br>
                            <input type="url" 
                                   id="facebook_url" 
                                   name="<?php echo $this->option_name; ?>[facebook_url]" 
                                   value="<?php echo esc_attr($options['facebook_url']); ?>" 
                                   class="regular-text">
                            <br><br>
                            <label for="instagram_url">Instagram URL:</label><br>
                            <input type="url" 
                                   id="instagram_url" 
                                   name="<?php echo $this->option_name; ?>[instagram_url]" 
                                   value="<?php echo esc_attr($options['instagram_url']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Show maintenance page to non-admin users
     */
    public function show_maintenance_page() {
        $options = get_option($this->option_name);
        
        // Don't show maintenance page if disabled or user is admin
        if (empty($options['enabled']) || current_user_can('manage_options')) {
            return;
        }
        
        // Allow access to wp-login.php and wp-admin
        if (is_admin() || $GLOBALS['pagenow'] === 'wp-login.php') {
            return;
        }
        
        // Set 503 header
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 3600'); // 1 hour
        
        $this->display_maintenance_page($options);
        exit;
    }
    
    /**
     * Display the maintenance page
     */
    private function display_maintenance_page($options) {
        $title = isset($options['title']) ? $options['title'] : 'UNDER MAINTENANCE';
        $message = isset($options['message']) ? $options['message'] : 'We apologize for the inconvenience, we will be back shortly.';
        $show_social = isset($options['show_social']) ? $options['show_social'] : true;
        $linkedin_url = isset($options['linkedin_url']) ? $options['linkedin_url'] : '#';
        $facebook_url = isset($options['facebook_url']) ? $options['facebook_url'] : '#';
        $instagram_url = isset($options['instagram_url']) ? $options['instagram_url'] : '#';
        
        $robot_image = C2AI_MAINTENANCE_PLUGIN_URL . 'assets/robot.webp';
        $logo_svg = $this->get_logo_svg();
        
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($title); ?> - Customer2AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(180deg, #0a1628 0%, #1a2844 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 60px;
            background: rgba(10, 22, 40, 0.8);
            backdrop-filter: blur(10px);
        }
        
        .logo {
            width: 150px;
            height: auto;
        }
        
        .nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav a {
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav a:hover {
            color: #60a5fa;
        }
        
        .dashboard-btn {
            background: #2563eb;
            color: #ffffff;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .dashboard-btn:hover {
            background: #1d4ed8;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
        }
        
        .maintenance-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -0.02em;
        }
        
        .maintenance-message {
            font-size: 18px;
            color: #cbd5e1;
            margin-bottom: 60px;
            max-width: 600px;
        }
        
        .robot-container {
            position: relative;
            margin: 0 auto 60px;
            width: 400px;
            height: 400px;
        }
        
        .robot-circle {
            position: relative;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 165, 0, 0.1) 0%, rgba(255, 140, 0, 0.1) 100%);
            border: 3px solid;
            border-image: linear-gradient(135deg, #ff8c00, #ffa500) 1;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .robot-circle::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }
        
        .robot-image {
            width: 85%;
            height: 85%;
            object-fit: cover;
            border-radius: 50%;
            position: relative;
            z-index: 2;
        }
        
        .caution-tape {
            position: absolute;
            width: 120%;
            height: 60px;
            background: repeating-linear-gradient(
                45deg,
                #000000,
                #000000 20px,
                #fbbf24 20px,
                #fbbf24 40px
            );
            z-index: 3;
            opacity: 0.9;
        }
        
        /*.caution-tape.top {
            top: 30%;
            left: -10%;
            transform: rotate(-5deg);
        }
        
        .caution-tape.bottom {
            bottom: 30%;
            right: -10%;
            transform: rotate(-5deg);
        }*/
        
        /* Social Icons */
        .social-icons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 60px;
        }
        
        .social-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: #ffffff;
            color: #0a1628;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background: #60a5fa;
            color: #ffffff;
            transform: translateY(-3px);
        }
        
        .social-icon svg {
            width: 24px;
            height: 24px;
        }
        
        /* Footer */
        .footer {
            background: rgba(10, 22, 40, 0.8);
            backdrop-filter: blur(10px);
            padding: 60px 60px 40px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }
        
        .footer-logo-section {
            display: flex;
            flex-direction: column;
        }
        
        .footer-logo {
            width: 150px;
            margin-bottom: 30px;
        }
        
        .footer-address {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .footer-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #ffffff;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 12px;
        }
        
        .footer-section a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .footer-section a:hover {
            color: #60a5fa;
        }
        
        .new-badge {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 30px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }
        
        .footer-social {
            display: flex;
            gap: 15px;
        }
        
        .footer-social a {
            color: #94a3b8;
            transition: color 0.3s ease;
        }
        
        .footer-social a:hover {
            color: #60a5fa;
        }
        
        .copyright {
            color: #64748b;
            font-size: 14px;
        }
        
        .copyright a {
            color: #60a5fa;
            text-decoration: none;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .footer-content {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 20px 30px;
                flex-direction: column;
                gap: 20px;
            }
            
            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .maintenance-title {
                font-size: 32px;
            }
            
            .robot-container {
                width: 300px;
                height: 300px;
            }
            
            .robot-circle {
                width: 300px;
                height: 300px;
            }
            
            .footer {
                padding: 40px 30px 30px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .footer-bottom {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <?php echo $logo_svg; ?>
        </div>
        <nav class="nav">
            <a href="#">PLATFORM</a>
            <a href="#">FEATURES</a>
            <a href="#">TESTIMONIALS</a>
            <a href="#">FAQ</a>
            <a href="#">CONTACT</a>
            <a href="#">BLOG</a>
            <a href="#" class="dashboard-btn">DASHBOARD</a>
        </nav>
    </header>
    
    <!-- Main Content -->
    <main class="main-content">
        <h1 class="maintenance-title"><?php echo esc_html($title); ?></h1>
        <p class="maintenance-message"><?php echo esc_html($message); ?></p>
        
        <div class="robot-container">
            <div class="robot-circle">
                <img src="<?php echo esc_url($robot_image); ?>" alt="Robot under maintenance" class="robot-image">
            </div>
        </div>
        
        <?php if ($show_social): ?>
        <div class="social-icons">
            <a href="<?php echo esc_url($linkedin_url); ?>" class="social-icon" target="_blank" rel="noopener">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
            <a href="<?php echo esc_url($facebook_url); ?>" class="social-icon" target="_blank" rel="noopener">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <a href="<?php echo esc_url($instagram_url); ?>" class="social-icon" target="_blank" rel="noopener">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/></svg>
            </a>
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <?php echo $logo_svg; ?>
                </div>
                <div class="footer-address">
                    <strong>Address:</strong><br>
                    9414 E San Salvadore Dr | Suite 250<br>
                    Scottsdale, AZ 85258
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Company</h3>
                <ul>
                    <li><a href="#">Platform</a></li>
                    <li><a href="#">Features <span class="new-badge">New</span></a></li>
                    <li><a href="#">Testimonials</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Support</h3>
                <ul>
                    <li><a href="#">Contact us</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Legal</h3>
                <ul>
                    <li><a href="#">Customer 2 AI Information</a></li>
                    <li><a href="#">Terms Of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-social">
                <a href="<?php echo esc_url($linkedin_url); ?>" target="_blank" rel="noopener">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                </a>
                <a href="<?php echo esc_url($facebook_url); ?>" target="_blank" rel="noopener">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/></svg>
                </a>
            </div>
            <p class="copyright">Copyright &copy; 2025 <a href="#">Customer2AI</a></p>
        </div>
    </footer>
</body>
</html>
        <?php
    }
    
    /**
     * Get the Customer2AI logo SVG
     */
    private function get_logo_svg() {
        return '<svg width="150" height="40" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
            <text x="0" y="35" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#ffffff">
                CUSTOMER <tspan fill="#2563eb">2</tspan> AI
            </text>
        </svg>';
    }
}

// Initialize the plugin
new Customer2AI_Maintenance_Mode();
