<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>404 - Page Not Found | <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="error404">
    
    <div class="c2ai-404-container">
        
        <!-- Header -->
        <header class="c2ai-404-header">
            <div class="c2ai-404-logo">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <span class="site-name"><?php bloginfo('name'); ?></span>
                    </a>
                <?php endif; ?>
            </div>
            
            <nav class="c2ai-404-nav">
                <a href="<?php echo esc_url(home_url('/platform')); ?>">PLATFORM</a>
                <a href="<?php echo esc_url(home_url('/features')); ?>">FEATURES</a>
                <a href="<?php echo esc_url(home_url('/testimonials')); ?>">TESTIMONIALS</a>
                <a href="<?php echo esc_url(home_url('/faq')); ?>">FAQ</a>
                <a href="<?php echo esc_url(home_url('/contact')); ?>">CONTACT</a>
                <a href="<?php echo esc_url(home_url('/blog')); ?>">BLOG</a>
            </nav>
            
            <div class="c2ai-404-dashboard">
                <a href="<?php echo esc_url(home_url('/dashboard')); ?>" class="dashboard-btn">DASHBOARD</a>
            </div>
        </header>

        <!-- Main 404 Content -->
        <main class="c2ai-404-main">
            <div class="c2ai-404-content">
                
                <!-- Large 404 with image -->
                <div class="c2ai-404-number">
                    <span class="number-text">404</span>
                    <div class="c2ai-404-image-container">
                        <img src="<?php echo c2ai_404_PLUGIN_URL; ?>assets/images/robot-space.webp" 
                             alt="Lost robot in space" 
                             class="c2ai-404-image">
                    </div>
                </div>
                
                <!-- Text Content -->
                <div class="c2ai-404-text">
                    <h1 class="c2ai-404-title">PAGE NOT FOUND</h1>
                    <p class="c2ai-404-subtitle">It seems you got a little bit lost.</p>
                    
                    <a href="<?php echo esc_url(get_option('c2_404_home_url', home_url('/'))); ?>" 
                       class="c2ai-404-home-btn">Return Home</a>
                </div>
                
            </div>
        </main>

        <!-- Social Media Icons -->
        <div class="c2ai-404-social">
            <?php if ($linkedin = get_option('c2ai_404_linkedin_url')) : ?>
            <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                </svg>
            </a>
            <?php endif; ?>
            
            <?php if ($facebook = get_option('c2ai_404_facebook_url')) : ?>
            <a href="<?php echo esc_url($facebook); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
                </svg>
            </a>
            <?php endif; ?>
            
            <?php if ($instagram = get_option('c2ai_404_instagram_url')) : ?>
            <a href="<?php echo esc_url($instagram); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
            </a>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="c2ai-404-footer">
            <div class="footer-content">
                <div class="footer-logo">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <span class="site-name"><?php bloginfo('name'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="footer-info">
                    <p class="footer-address">
                        <strong>Address:</strong><br>
                        9414 E San Salvadore Dr | Suite 250<br>
                        Scottsdale, AZ 85258
                    </p>
                </div>
                
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Company</h4>
                        <a href="<?php echo esc_url(home_url('/platform')); ?>">Platform</a>
                        <a href="<?php echo esc_url(home_url('/features')); ?>">Features <span class="badge">New</span></a>
                        <a href="<?php echo esc_url(home_url('/testimonials')); ?>">Testimonials</a>
                    </div>
                    
                    <div class="footer-column">
                        <h4>Support</h4>
                        <a href="<?php echo esc_url(home_url('/contact')); ?>">Contact us</a>
                        <a href="<?php echo esc_url(home_url('/faq')); ?>">FAQ</a>
                    </div>
                    
                    <div class="footer-column">
                        <h4>Legal</h4>
                        <a href="<?php echo esc_url(home_url('/information')); ?>">Customer 2 AI Information</a>
                        <a href="<?php echo esc_url(home_url('/terms-of-service')); ?>">Terms Of Service</a>
                        <a href="<?php echo esc_url(home_url('/privacy-policy')); ?>">Privacy Policy</a>
                        <a href="<?php echo esc_url(home_url('/cookie-policy')); ?>">Cookie Policy</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-social">
                    <?php if ($linkedin = get_option('c2_404_linkedin_url')) : ?>
                    <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($facebook = get_option('c2_404_facebook_url')) : ?>
                    <a href="<?php echo esc_url($facebook); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($instagram = get_option('c2_404_instagram_url')) : ?>
                    <a href="<?php echo esc_url($instagram); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>
                
                <p class="copyright">Copyright © <?php echo date('Y'); ?> Customer2AI</p>
            </div>
        </footer>
        
    </div>

    <?php wp_footer(); ?>
</body>
</html>
