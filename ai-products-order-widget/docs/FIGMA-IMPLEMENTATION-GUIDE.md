# Implementing Figma Design into AI Products Order Widget

## 🎨 Overview

Your designer creates the UI in Figma → You implement it in WordPress plugin

---

## 📋 Step-by-Step Implementation Process

### Step 1: Get Assets from Designer

Ask your designer to provide:

#### Required Exports:
- [ ] **CSS/SCSS files** (if using a Figma-to-CSS plugin)
- [ ] **Image assets** (logos, icons, backgrounds)
  - Export as SVG (preferred) or PNG (2x for retina)
- [ ] **Design specifications:**
  - Colors (hex codes)
  - Fonts (names, weights, sizes)
  - Spacing values
  - Border radius values
  - Shadow values
  - Breakpoints for responsive design

#### Helpful Tools for Designer:
- **Figma Plugins:**
  - "CSS Gen" - Exports CSS directly
  - "Anima" - Converts to HTML/CSS
  - "Figma to Code" - Generates code
  - "Inspect" - Shows spacing/colors

---

### Step 2: Prepare Your Plugin Structure

Create proper file structure for CSS/JS:

```bash
wp-content/plugins/ai-products-order-widget/
├── ai-products-order-widget.php
├── assets/
│   ├── css/
│   │   └── widget-styles.css          ← Your custom CSS goes here
│   ├── js/
│   │   └── widget-scripts.js          ← Your custom JS goes here
│   └── images/
│       ├── logo.svg
│       ├── icon-calls.svg
│       ├── icon-emails.svg
│       └── icon-chat.svg
├── composer.json
└── vendor/
```

---

### Step 3: Enqueue CSS and JavaScript

Modify your main plugin file to load assets:

```php
// Add this to your AI_Products_Order_Widget class

/**
 * Enqueue plugin styles and scripts
 */
public function enqueue_assets() {
    // Only load on pages with the shortcode
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ai_products_widget')) {
        
        // Enqueue CSS
        wp_enqueue_style(
            'aipw-widget-styles',
            AIPW_PLUGIN_URL . 'assets/css/widget-styles.css',
            [],
            AIPW_VERSION
        );
        
        // Enqueue JavaScript (if needed for interactions)
        wp_enqueue_script(
            'aipw-widget-scripts',
            AIPW_PLUGIN_URL . 'assets/js/widget-scripts.js',
            ['jquery'], // dependencies
            AIPW_VERSION,
            true // load in footer
        );
        
        // Pass data to JavaScript
        wp_localize_script('aipw-widget-scripts', 'aipwData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aipw_ajax_nonce')
        ]);
    }
}

// Add this to __construct():
add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
```

---

### Step 4: Implement the CSS

#### Option A: Direct CSS Implementation (Simplest)

1. Designer gives you CSS
2. You paste it into `assets/css/widget-styles.css`
3. Adjust class names to match your HTML structure

#### Option B: SCSS/SASS Implementation (More Organized)

```bash
assets/
├── scss/
│   ├── _variables.scss      ← Colors, fonts, spacing
│   ├── _mixins.scss          ← Reusable styles
│   ├── _base.scss            ← Base/reset styles
│   ├── _components.scss      ← Buttons, inputs, etc.
│   ├── _layout.scss          ← Grid, containers
│   └── widget-styles.scss    ← Main file (imports all)
└── css/
    └── widget-styles.css     ← Compiled output
```

Compile SCSS to CSS:
```bash
npm install -g sass
sass assets/scss/widget-styles.scss assets/css/widget-styles.css --watch
```

---

### Step 5: Map Figma Design to HTML Classes

Your designer's Figma layers → Your HTML classes

#### Example Mapping:

**Figma Layer Name** → **HTML Class**
```
"Primary Button"     → .aipw-btn-primary
"Input Field"        → .aipw-input
"Product Card"       → .aipw-product-card
"Section Container"  → .aipw-section
```

#### Update Your PHP to Use New Classes:

**Before (current):**
```php
<div class="aipw-section">
    <h2>Select Products</h2>
</div>
```

**After (with design classes):**
```php
<div class="aipw-section aipw-section--products">
    <h2 class="aipw-section__title">Select Products</h2>
</div>
```

---

### Step 6: Implement Component by Component

Break it down into pieces:

#### 1. Typography
```css
/* From Figma specs */
:root {
    --font-primary: 'Inter', sans-serif;
    --font-heading: 'Poppins', sans-serif;
    
    --font-size-xs: 12px;
    --font-size-sm: 14px;
    --font-size-base: 16px;
    --font-size-lg: 18px;
    --font-size-xl: 24px;
    --font-size-2xl: 32px;
}

.aipw-container {
    font-family: var(--font-primary);
    font-size: var(--font-size-base);
}

.aipw-section h2 {
    font-family: var(--font-heading);
    font-size: var(--font-size-xl);
}
```

#### 2. Colors
```css
:root {
    --color-primary: #0066FF;
    --color-secondary: #6C757D;
    --color-success: #28A745;
    --color-danger: #DC3545;
    --color-warning: #FFC107;
    
    --color-bg: #FFFFFF;
    --color-bg-alt: #F8F9FA;
    --color-border: #DEE2E6;
    
    --color-text: #212529;
    --color-text-muted: #6C757D;
}
```

#### 3. Spacing
```css
:root {
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    --spacing-2xl: 48px;
}
```

#### 4. Components (Buttons, Inputs, etc.)
```css
/* Button from Figma */
.aipw-btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.aipw-btn-primary {
    background: var(--color-primary);
    color: white;
    border: none;
}

.aipw-btn-primary:hover {
    background: #0052CC;
    transform: translateY(-2px);
}

/* Input from Figma */
.aipw-input {
    padding: 12px 16px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 16px;
}

.aipw-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.1);
}
```

---

### Step 7: Handle Images and Icons

#### Option 1: Use Image Files
```php
<img src="<?php echo AIPW_PLUGIN_URL; ?>assets/images/icon-calls.svg" 
     alt="AI Calls" 
     class="aipw-product-icon">
```

#### Option 2: Inline SVG (Better for customization)
```php
<svg class="aipw-icon" viewBox="0 0 24 24">
    <path d="M..." fill="currentColor"/>
</svg>
```

#### Option 3: Icon Font (e.g., Font Awesome)
```php
// Enqueue Font Awesome
wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

// Use in HTML
<i class="fas fa-phone aipw-icon"></i>
```

---

### Step 8: Implement Responsive Design

Match Figma's breakpoints:

```css
/* Mobile First Approach */

/* Base styles (mobile) */
.aipw-container {
    padding: 16px;
}

/* Tablet */
@media (min-width: 768px) {
    .aipw-container {
        padding: 24px;
    }
    
    .aipw-product-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

/* Desktop */
@media (min-width: 1024px) {
    .aipw-container {
        max-width: 1200px;
        padding: 32px;
    }
    
    .aipw-product-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Large Desktop */
@media (min-width: 1440px) {
    .aipw-container {
        max-width: 1400px;
    }
}
```

---

### Step 9: Add Animations/Interactions

From Figma prototypes:

```css
/* Smooth transitions */
* {
    transition: all 0.3s ease;
}

/* Hover effects */
.aipw-product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

/* Focus states */
.aipw-input:focus {
    border-color: var(--color-primary);
}

/* Loading states */
.aipw-loading {
    position: relative;
    pointer-events: none;
}

.aipw-loading::after {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

---

### Step 10: JavaScript Enhancements

If designer specified interactions:

```javascript
// assets/js/widget-scripts.js

jQuery(document).ready(function($) {
    
    // Smooth scrolling to sections
    $('.aipw-nav-link').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('html, body').animate({
            scrollTop: $(target).offset().top - 100
        }, 800);
    });
    
    // Animated number counters
    $('.aipw-stat-number').each(function() {
        var $this = $(this);
        var countTo = $this.attr('data-count');
        $({ countNum: $this.text() }).animate({
            countNum: countTo
        }, {
            duration: 2000,
            step: function() {
                $this.text(Math.floor(this.countNum));
            }
        });
    });
    
    // Product card selection with animation
    $('.aipw-product-card').on('click', function() {
        $(this).toggleClass('selected');
        $(this).find('.aipw-checkbox').prop('checked', function(i, val) {
            return !val;
        });
    });
    
});
```

---

## 🔄 Workflow with Designer

### Phase 1: Design Handoff
**Designer provides:**
1. Figma link with developer mode access
2. Exported assets (images, icons)
3. Style guide (colors, fonts, spacing)
4. Prototype links (for interactions)

### Phase 2: Implementation
**You do:**
1. Create CSS file structure
2. Implement component by component
3. Test responsiveness
4. Add interactions/animations

### Phase 3: Review & Iterate
**Together:**
1. Compare implementation with Figma
2. Designer reviews in browser
3. Make adjustments
4. Repeat until perfect

---

## 🛠️ Useful Tools

### For You (Developer):
- **Figma Dev Mode** - Inspect designs, get CSS
- **Figma to Code** - Auto-generate code
- **PerfectPixel** - Browser extension to overlay Figma designs
- **WhatFont** - Identify fonts
- **ColorZilla** - Pick colors from designs

### For Designer:
- **Figma plugins:**
  - CSS Gen
  - Anima (Figma to HTML/React)
  - Zeplin (handoff tool)
  - Avocode (inspect tool)

---

## 📝 Example: Product Card Implementation

### 1. Designer's Figma:
```
┌─────────────────────┐
│  📞                 │
│  AI Calls (Phone)  │
│  ──────────────────│
│  • Feature 1       │
│  • Feature 2       │
│  ──────────────────│
│  $99/month         │
│  [  Select  ]      │
└─────────────────────┘
```

### 2. Designer Exports:
- Specs: 300px wide, 20px padding, 16px border-radius
- Colors: #0066FF (primary), #F8F9FA (background)
- Shadows: 0 4px 12px rgba(0,0,0,0.1)

### 3. You Implement HTML:
```php
<div class="aipw-product-card" data-product="ai_calls">
    <div class="aipw-product-icon">
        <?php echo file_get_contents(AIPW_PLUGIN_DIR . 'assets/images/icon-calls.svg'); ?>
    </div>
    <h3 class="aipw-product-title">AI Calls (Phone)</h3>
    <ul class="aipw-product-features">
        <li>Feature 1</li>
        <li>Feature 2</li>
    </ul>
    <div class="aipw-product-price">$99/month</div>
    <button class="aipw-btn aipw-btn-primary">Select</button>
</div>
```

### 4. You Implement CSS:
```css
.aipw-product-card {
    width: 300px;
    padding: 20px;
    border-radius: 16px;
    background: #F8F9FA;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
}

.aipw-product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.aipw-product-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
}

.aipw-product-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 16px;
    color: #212529;
}

.aipw-product-features {
    list-style: none;
    padding: 0;
    margin: 16px 0;
    text-align: left;
}

.aipw-product-features li::before {
    content: "•";
    color: #0066FF;
    margin-right: 8px;
}

.aipw-product-price {
    font-size: 24px;
    font-weight: 700;
    color: #0066FF;
    margin: 16px 0;
}

.aipw-btn-primary {
    width: 100%;
    padding: 12px 24px;
    background: #0066FF;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.aipw-btn-primary:hover {
    background: #0052CC;
}
```

---

## ✅ Quality Checklist

Before calling it done:

- [ ] Matches Figma design pixel-perfect (use overlay tool)
- [ ] Responsive on all breakpoints (mobile, tablet, desktop)
- [ ] All fonts loaded correctly
- [ ] All colors match exactly
- [ ] All spacing matches
- [ ] Hover states work
- [ ] Focus states work (accessibility)
- [ ] Animations smooth (60fps)
- [ ] Images optimized (compressed)
- [ ] No console errors
- [ ] Cross-browser tested (Chrome, Firefox, Safari, Edge)
- [ ] Accessibility tested (keyboard navigation, screen readers)

---

## 🚨 Common Pitfalls

1. **Not using CSS variables** - Makes updates harder
2. **Hardcoding colors** - Use variables for consistency
3. **Not testing mobile** - Design often breaks on small screens
4. **Forgetting hover/focus states** - Makes UI feel unfinished
5. **Not optimizing images** - Slows down page load
6. **Inline styles** - Keep CSS in separate files
7. **Not using classes consistently** - Use BEM or similar naming

---

## 📚 Resources

- **Figma for Developers:** https://www.figma.com/developers
- **CSS Variables Guide:** https://developer.mozilla.org/en-US/docs/Web/CSS/--*
- **BEM Naming:** http://getbem.com/
- **Flexbox Guide:** https://css-tricks.com/snippets/css/a-guide-to-flexbox/
- **Grid Guide:** https://css-tricks.com/snippets/css/complete-guide-grid/

---

## 🎯 Next Steps

1. **Get design handoff from designer** (Figma link + assets)
2. **Create file structure** (css/, js/, images/ folders)
3. **Update plugin to enqueue assets** (add enqueue_assets() method)
4. **Implement CSS component by component**
5. **Test on all devices**
6. **Review with designer**
7. **Deploy!**

This approach keeps your code clean and makes future design updates easy! 🚀