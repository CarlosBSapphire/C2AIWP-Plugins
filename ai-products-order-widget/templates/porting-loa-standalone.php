<?php
/**
 * Standalone Porting LOA Form Template
 * Shortcode: [ai_porting_loa]
 * Usage: Add ?uuid=YOUR_UUID to the URL
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    echo '<p>Please log in to access your porting forms.</p>';
    return;
}

$current_user_id = get_current_user_id();
?>

<div id="aipwLoaContainer" class="aipw-loa-standalone-container">
    <div id="aipwLoaContent">
        <!-- Loading state -->
        <div id="aipwLoaLoading" class="aipw-loading">
            <div class="aipw-spinner"></div>
            <p>Loading your porting request...</p>
        </div>

        <!-- Empty/Error state -->
        <div id="aipwLoaEmpty" class="aipw-empty-state" style="display: none;">
            <!-- Will be populated dynamically -->
        </div>

        <!-- LOA Form -->
        <div id="aipwLoaForm" class="aipw-loa-form-container" style="display: none;">
            <!-- Form will be dynamically inserted here -->
        </div>

        <!-- Success state -->
        <div id="aipwLoaSuccess" class="aipw-success" style="display: none;">
            <div class="aipw-success-icon">✓</div>
            <h2>LOA Submitted Successfully!</h2>
            <p>Your Letter of Authorization has been signed and submitted.</p>
            <p>We'll begin processing your number porting request shortly.</p>
            <p>You will receive a confirmation email with next steps.</p>
        </div>
    </div>
</div>
