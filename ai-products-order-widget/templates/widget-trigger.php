<?php
/**
 * AI Products Order Widget - Trigger Button Template
 *
 * This template renders the button that opens the modal widget.
 * The modal itself is created dynamically by JavaScript.
 *
 * @package AIPW
 * @version 2.0.0
 *
 * Available variables:
 * @var array $products Product configuration
 * @var array $addons Addons configuration
 * @var array $agent_levels Agent levels configuration
 * @var array $pricing Pricing data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="aipw-widget-container">
    <button
        class="aipw-trigger-btn"
        data-aipw-open
        type="button"
        aria-label="Open AI Products Order Widget">
        Get Started with Customer2.AI Services
    </button>
</div>

<style>
.aipw-widget-container {
    text-align: center;
    padding: 40px 20px;
}

.aipw-trigger-btn {
    background: #000;
    color: #fff;
    border: 2px solid #000;
    padding: 20px 50px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.aipw-trigger-btn:hover {
    background: #fff;
    color: #000;
    transform: scale(1.05);
}

.aipw-trigger-btn:active {
    transform: scale(0.98);
}
</style>

<?php
/**
 * Note: The modal HTML is dynamically created by modal-widget.js
 * when the button is clicked. This keeps the DOM clean and improves
 * page load performance.
 */
?>
