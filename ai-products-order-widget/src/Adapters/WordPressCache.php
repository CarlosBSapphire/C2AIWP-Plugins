<?php

namespace AIPW\Adapters;

/**
 * WordPress Cache Adapter
 *
 * Adapts WordPress transient functions for use with platform-agnostic services.
 *
 * @package AIPW\Adapters
 * @version 1.0.0
 */
class WordPressCache
{
    /**
     * Get cached value
     *
     * @param string $key
     * @return mixed|false
     */
    public function get($key)
    {
        return get_transient($key);
    }

    /**
     * Set cached value
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function set($key, $value, $ttl = 3600)
    {
        return set_transient($key, $value, $ttl);
    }

    /**
     * Check if cache key exists
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return get_transient($key) !== false;
    }

    /**
     * Delete cached value
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        return delete_transient($key);
    }

    /**
     * Clear all cached values with a specific prefix
     *
     * @param string $prefix
     * @return int Number of items cleared
     */
    public function clearByPrefix($prefix)
    {
        global $wpdb;

        $count = 0;
        $sql = $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $prefix) . '%'
        );

        $transients = $wpdb->get_col($sql);

        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            if (delete_transient($key)) {
                $count++;
            }
        }

        return $count;
    }
}
