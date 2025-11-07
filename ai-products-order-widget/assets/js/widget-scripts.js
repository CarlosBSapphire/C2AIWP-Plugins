/**
 * AI Products Order Widget - Custom JavaScript
 * 
 * This file is for any custom interactions from your Figma design
 * The core functionality (form handling, localStorage) is already in the plugin
 */

(function($) {
    'use strict';
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        
        // Add any custom initialization here
        console.log('AI Products Widget custom scripts loaded');
        
        // Example: Smooth scroll to sections
        initSmoothScroll();
        
        // Example: Animated product cards
        initProductCardAnimations();
        
        // Example: Enhanced form interactions
        initFormEnhancements();
        
    });
    
    /**
     * Smooth scroll to sections (if you have navigation)
     */
    function initSmoothScroll() {
        $('.aipw-nav-link').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            if ($(target).length) {
                $('html, body').animate({
                    scrollTop: $(target).offset().top - 100
                }, 800, 'swing');
            }
        });
    }
    
    /**
     * Product card animations and interactions
     */
    function initProductCardAnimations() {
        
        // Add selected class when checkbox is checked
        $('.aipw-product-checkbox, .aipw-addon-checkbox').on('change', function() {
            var $parent = $(this).closest('.aipw-product-option, .aipw-addon-option');
            if ($(this).is(':checked')) {
                $parent.addClass('selected');
            } else {
                $parent.removeClass('selected');
            }
        });
        
        // Make entire card clickable
        $('.aipw-product-option, .aipw-addon-option').on('click', function(e) {
            // Don't trigger if clicking the checkbox itself
            if (!$(e.target).is('input[type="checkbox"]')) {
                var $checkbox = $(this).find('input[type="checkbox"]');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });
    }
    
    /**
     * Enhanced form interactions
     */
    function initFormEnhancements() {
        
        // Add floating labels effect (if your design has them)
        $('.aipw-field input, .aipw-field textarea, .aipw-field select').on('focus blur', function(e) {
            var $field = $(this).closest('.aipw-field');
            if (e.type === 'focus' || $(this).val() !== '') {
                $field.addClass('has-value');
            } else {
                $field.removeClass('has-value');
            }
        });
        
        // Character counter for textarea (if needed)
        $('.aipw-field textarea[maxlength]').each(function() {
            var maxLength = $(this).attr('maxlength');
            var $counter = $('<div class="aipw-char-counter">0 / ' + maxLength + '</div>');
            $(this).after($counter);
            
            $(this).on('input', function() {
                var currentLength = $(this).val().length;
                $counter.text(currentLength + ' / ' + maxLength);
            });
        });
        
        // Password strength indicator (if needed)
        $('input[type="password"]#password').on('input', function() {
            var password = $(this).val();
            var strength = calculatePasswordStrength(password);
            updatePasswordStrengthIndicator(strength);
        });
    }
    
    /**
     * Calculate password strength
     */
    function calculatePasswordStrength(password) {
        var strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        return strength;
    }
    
    /**
     * Update password strength indicator
     */
    function updatePasswordStrengthIndicator(strength) {
        var $indicator = $('#password-strength-indicator');
        
        if ($indicator.length === 0) {
            $indicator = $('<div id="password-strength-indicator" class="aipw-password-strength"></div>');
            $('input[type="password"]#password').after($indicator);
        }
        
        var strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        var strengthClass = ['very-weak', 'weak', 'fair', 'good', 'strong', 'very-strong'];
        
        $indicator
            .removeClass(strengthClass.join(' '))
            .addClass(strengthClass[strength])
            .text(strengthText[strength] || '');
    }
    
    /**
     * Example: Animated number counter (for stats, if needed)
     */
    function animateNumbers() {
        $('.aipw-stat-number').each(function() {
            var $this = $(this);
            var countTo = parseInt($this.attr('data-count'));
            
            $({ countNum: 0 }).animate({
                countNum: countTo
            }, {
                duration: 2000,
                easing: 'swing',
                step: function() {
                    $this.text(Math.floor(this.countNum));
                },
                complete: function() {
                    $this.text(countTo);
                }
            });
        });
    }
    
    /**
     * Example: Progress bar animation
     */
    function animateProgressBars() {
        $('.aipw-progress-bar').each(function() {
            var $bar = $(this);
            var progress = $bar.attr('data-progress');
            
            $bar.animate({
                width: progress + '%'
            }, 1500, 'swing');
        });
    }
    
    /**
     * Example: Parallax effect (if your design has it)
     */
    function initParallax() {
        $(window).on('scroll', function() {
            var scrolled = $(window).scrollTop();
            $('.aipw-parallax').css('transform', 'translateY(' + (scrolled * 0.5) + 'px)');
        });
    }
    
    /**
     * Example: Intersection Observer for scroll animations
     */
    function initScrollAnimations() {
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('aipw-animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            document.querySelectorAll('.aipw-animate-on-scroll').forEach(function(el) {
                observer.observe(el);
            });
        }
    }
    
    /**
     * Example: Modal/Dialog functionality (if needed)
     */
    function initModals() {
        $('.aipw-modal-trigger').on('click', function(e) {
            e.preventDefault();
            var modalId = $(this).attr('data-modal');
            $('#' + modalId).addClass('active');
            $('body').addClass('aipw-modal-open');
        });
        
        $('.aipw-modal-close, .aipw-modal-overlay').on('click', function() {
            $(this).closest('.aipw-modal').removeClass('active');
            $('body').removeClass('aipw-modal-open');
        });
        
        // Close on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.aipw-modal.active').removeClass('active');
                $('body').removeClass('aipw-modal-open');
            }
        });
    }
    
    /**
     * Example: Tooltip initialization (if your design has tooltips)
     */
    function initTooltips() {
        $('.aipw-tooltip-trigger').on('mouseenter', function() {
            var tooltipText = $(this).attr('data-tooltip');
            var $tooltip = $('<div class="aipw-tooltip">' + tooltipText + '</div>');
            
            $('body').append($tooltip);
            
            var offset = $(this).offset();
            $tooltip.css({
                top: offset.top - $tooltip.outerHeight() - 10,
                left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
            });
            
            setTimeout(function() {
                $tooltip.addClass('active');
            }, 10);
            
        }).on('mouseleave', function() {
            $('.aipw-tooltip').removeClass('active');
            setTimeout(function() {
                $('.aipw-tooltip').remove();
            }, 300);
        });
    }
    
    /**
     * Example: Form validation enhancements
     */
    function enhanceFormValidation() {
        
        // Real-time email validation
        $('input[type="email"]').on('blur', function() {
            var email = $(this).val();
            var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            var $field = $(this).closest('.aipw-field');
            if (email && !isValid) {
                $field.addClass('has-error');
                if (!$field.find('.aipw-error-message').length) {
                    $field.append('<span class="aipw-error-message">Please enter a valid email address</span>');
                }
            } else {
                $field.removeClass('has-error');
                $field.find('.aipw-error-message').remove();
            }
        });
        
        // Password confirmation matching
        $('#password, #confirm_password').on('input', function() {
            var password = $('#password').val();
            var confirmPassword = $('#confirm_password').val();
            var $field = $('#confirm_password').closest('.aipw-field');
            
            if (confirmPassword && password !== confirmPassword) {
                $field.addClass('has-error');
                if (!$field.find('.aipw-error-message').length) {
                    $field.append('<span class="aipw-error-message">Passwords do not match</span>');
                }
            } else {
                $field.removeClass('has-error');
                $field.find('.aipw-error-message').remove();
            }
        });
    }
    
    /**
     * Access plugin data passed from PHP
     */
    function getPluginData() {
        if (typeof aipwData !== 'undefined') {
            console.log('AJAX URL:', aipwData.ajaxUrl);
            console.log('Plugin URL:', aipwData.pluginUrl);
            return aipwData;
        }
        return null;
    }
    
})(jQuery);
