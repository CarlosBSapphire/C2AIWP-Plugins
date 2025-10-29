<?php
/**
 * MU Plugin Loader
 * Automatically loads all plugin main files in subfolders of /mu-plugins/
 * Skips itself and only loads files containing a valid WordPress plugin header.
 */

$mu_plugin_dir = WPMU_PLUGIN_DIR;

// Get all subdirectories inside mu-plugins
$subfolders = glob($mu_plugin_dir . '/*', GLOB_ONLYDIR);

foreach ($subfolders as $folder) {
    // Find all PHP files directly inside each subfolder
    foreach (glob($folder . '/*.php') as $plugin_file) {
        // Skip this loader itself
        if (basename($plugin_file) === basename(__FILE__)) {
            continue;
        }

        // Check if the file contains a WordPress plugin header (Plugin Name:)
        $contents = file_get_contents($plugin_file, false, null, 0, 2048); // Read first 2KB for performance
        if (strpos($contents, 'Plugin Name:') === false) {
            continue; // Skip non-plugin PHP files
        }

        // Require the plugin file
        require_once $plugin_file;
    }
}
