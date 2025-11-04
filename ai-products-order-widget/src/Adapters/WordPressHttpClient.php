<?php

namespace AIPW\Adapters;

/**
 * WordPress HTTP Client Adapter
 *
 * Adapts WordPress wp_remote_* functions for use with platform-agnostic services.
 *
 * @package AIPW\Adapters
 * @version 1.0.0
 */
class WordPressHttpClient
{
    /**
     * Execute HTTP request using WordPress functions
     *
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $headers
     * @return array
     */
    public static function request($url, $method = 'POST', $data = [], $headers = [])
    {
        $wp_headers = [];
        foreach ($headers as $key => $value) {
            $wp_headers[$key] = $value;
        }

        $args = [
            'method' => strtoupper($method),
            'headers' => $wp_headers,
            'timeout' => 15,
            'body' => !empty($data) ? json_encode($data) : null
        ];

        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
            unset($args['body']);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => $response->get_error_message(),
                'status' => 0
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return [
            'success' => $status >= 200 && $status < 300,
            'data' => $decoded ?? $body,
            'error' => $status >= 200 && $status < 300 ? null : "HTTP {$status}",
            'status' => $status,
            'raw_body' => $body
        ];
    }
}
