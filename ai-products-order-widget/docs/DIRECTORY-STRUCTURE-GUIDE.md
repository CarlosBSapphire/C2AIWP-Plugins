# Directory Structure Guide

## 📁 How to Organize Your Plugin Files

After your designer provides the Figma assets, organize your files like this:

```
wp-content/plugins/ai-products-order-widget/
│
├── ai-products-order-widget.php          ← Main plugin file (REQUIRED)
├── composer.json                         ← Dependencies (REQUIRED)
├── vendor/                               ← Created by composer install (REQUIRED)
│   └── dompdf/                          
│
├── assets/                               ← Create this folder for design files
│   ├── css/
│   │   └── widget-styles.css            ← Your custom CSS from Figma
│   │
│   ├── js/
│   │   └── widget-scripts.js            ← Your custom JavaScript
│   │
│   ├── images/                          ← Images from Figma
│   │   ├── logo.svg
│   │   ├── logo@2x.png
│   │   ├── icon-calls.svg
│   │   ├── icon-emails.svg
│   │   ├── icon-chat.svg
│   │   ├── icon-qa.svg
│   │   ├── icon-avs.svg
│   │   ├── icon-package.svg
│   │   ├── icon-phone.svg
│   │   ├── icon-verification.svg
│   │   ├── icon-transcription.svg
│   │   └── background-pattern.svg
│   │
│   └── fonts/                           ← Custom fonts (if needed)
│       ├── CustomFont-Regular.woff2
│       ├── CustomFont-Bold.woff2
│       └── font-face.css
│
├── docs/                                 ← Documentation (optional)
│   ├── README.md
│   ├── QUICK-START.md
│   ├── PROJECT-SUMMARY.md
│   ├── FIGMA-IMPLEMENTATION-GUIDE.md
│   └── INDEX.md
│
├── includes/                             ← Helper files (optional)
│   ├── config.php
│   ├── database-helper.php
│   └── api-helper.php
│
└── styles-template.css                   ← Reference only (delete after implementing)
```

---

## 🚀 Quick Setup After Designer Delivers

### Step 1: Create Folders
```bash
cd wp-content/plugins/ai-products-order-widget/
mkdir -p assets/css assets/js assets/images assets/fonts
```

### Step 2: Place CSS File
```bash
# Designer gives you the CSS file
# Place it here:
assets/css/widget-styles.css
```

### Step 3: Place JavaScript File (if needed)
```bash
# Designer gives you any custom JS
# Place it here:
assets/js/widget-scripts.js
```

### Step 4: Place Images
```bash
# Designer exports images from Figma
# Place them here:
assets/images/logo.svg
assets/images/icon-calls.svg
# ... etc
```

### Step 5: Place Fonts (if using custom fonts)
```bash
# Designer provides font files
# Place them here:
assets/fonts/YourFont-Regular.woff2
assets/fonts/YourFont-Bold.woff2
```

---

## 📦 What Files Do What?

### Core Plugin Files (Don't Delete These!)
| File | Purpose |
|------|---------|
| `ai-products-order-widget.php` | Main plugin - all functionality |
| `composer.json` | Manages dompdf dependency |
| `vendor/` | PHP libraries (created by composer) |

### Design Files (You Create These)
| File/Folder | Purpose |
|-------------|---------|
| `assets/css/widget-styles.css` | Your custom styles from Figma |
| `assets/js/widget-scripts.js` | Your custom interactions |
| `assets/images/` | All images/icons from Figma |
| `assets/fonts/` | Custom web fonts |

### Optional Files (Can Delete If Not Using)
| File | Purpose |
|------|---------|
| `styles-template.css` | Example CSS (reference only) |
| `widget-scripts.js` | Example JS (reference only) |
| `config.php` | Configuration helper |
| `database-helper.php` | Database operations helper |
| `api-helper.php` | API operations helper |
| `docs/*.md` | Documentation files |

---

## 🎨 Working with Your Designer

### What to Ask For:

1. **CSS File**
   - "Can you export the CSS from Figma?"
   - "Which Figma plugin are you using to generate CSS?"
   - Place result in: `assets/css/widget-styles.css`

2. **Images**
   - "Please export all icons as SVG"
   - "Please export photos/images as PNG at 2x resolution"
   - Place in: `assets/images/`

3. **Fonts**
   - "Are we using custom fonts?"
   - "Can you provide the font files in WOFF2 format?"
   - Place in: `assets/fonts/`

4. **Design Specs**
   - "What are the exact color hex codes?"
   - "What are the spacing values?"
   - "What are the breakpoints for responsive?"

---

## 🔄 Workflow

```
Designer Creates in Figma
         ↓
Designer Exports Assets
    ├── CSS file
    ├── Images (SVG/PNG)
    └── Fonts (WOFF2)
         ↓
You Place Files in Folders
    ├── CSS → assets/css/
    ├── Images → assets/images/
    └── Fonts → assets/fonts/
         ↓
Plugin Automatically Loads Them
         ↓
Test & Iterate
```

---

## ✅ Verification Checklist

After placing files, verify:

- [ ] `assets/css/widget-styles.css` exists
- [ ] Images load correctly (check browser console)
- [ ] Fonts load correctly (check in browser inspector)
- [ ] Custom JS works (check browser console)
- [ ] No 404 errors (check network tab)
- [ ] Responsive design works (test all breakpoints)

---

## 🐛 Troubleshooting

### CSS Not Loading?
1. Check file exists at: `assets/css/widget-styles.css`
2. Check filename is exactly `widget-styles.css`
3. Clear WordPress cache
4. Check browser console for errors

### Images Not Loading?
1. Check path in CSS: `url('../images/icon-calls.svg')`
2. Check file permissions: `chmod 644 assets/images/*`
3. Check browser console for 404 errors

### Fonts Not Loading?
1. Check @font-face declaration in CSS
2. Check CORS if fonts on CDN
3. Use WOFF2 format (best browser support)

---

## 💡 Pro Tips

1. **Use SVG for icons** - Scalable, small file size, can be colored with CSS
2. **Optimize images** - Use tools like TinyPNG, ImageOptim
3. **Use CSS variables** - Makes color changes easier
4. **Version your assets** - Update version number in plugin to bust cache
5. **Test mobile first** - Easier to scale up than down

---

## 📝 File Naming Conventions

### Good Names:
- `widget-styles.css` ✅
- `icon-calls.svg` ✅
- `logo-primary.png` ✅
- `CustomFont-Bold.woff2` ✅

### Bad Names:
- `Untitled-1.css` ❌
- `image copy 2.png` ❌
- `Icon Final FINAL v3.svg` ❌
- `font.ttf` ❌

---

## 🎯 Minimal Setup (Just to Get Started)

If you want to test with minimal setup:

```
wp-content/plugins/ai-products-order-widget/
├── ai-products-order-widget.php
├── composer.json
├── vendor/
└── assets/
    └── css/
        └── widget-styles.css    ← Just this one file to start!
```

Add more as needed!

---

## 📞 Questions to Ask Designer

Before they start:
- "What's your design handoff process?"
- "Do you use any Figma plugins for export?"
- "Can you use our naming conventions for assets?"

During design:
- "Can I get a preview link to the Figma file?"
- "Are there any custom animations I need to implement?"
- "What's the mobile breakpoint?"

At handoff:
- "Can you provide a style guide?"
- "Are all assets exported?"
- "Any special fonts or libraries needed?"

---

## 🚀 Next Steps

1. Wait for designer to complete Figma design
2. Get exported assets (CSS, images, fonts)
3. Create `assets/` folder structure
4. Place files in correct locations
5. Test in browser
6. Iterate with designer
7. Launch! 🎉