<?php

namespace AIPW\Adapters;

/**
 * WordPress Logger Adapter
 *
 * Adapts WordPress error_log for use with platform-agnostic services.
 *
 * @package AIPW\Adapters
 * @version 1.0.0
 */
class WordPressLogger
{
    /**
     * Log prefix
     *
     * @var string
     */
    private $prefix;

    /**
     * Constructor
     *
     * @param string $prefix Log message prefix
     */
    public function __construct($prefix = 'AIPW')
    {
        $this->prefix = $prefix;
    }

    /**
     * Log message
     *
     * @param string $message
     * @param string $level Log level: debug, info, warning, error
     * @param array $context Additional context
     */
    public function log($message, $level = 'info', $context = [])
    {
        $level = strtoupper($level);

        $contextString = !empty($context) ? ' | Context: ' . json_encode($context) : '';

        $logMessage = sprintf(
            '[%s] %s: %s%s',
            $this->prefix,
            $level,
            $message,
            $contextString
        );

        error_log($logMessage);
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log($message, 'debug', $context);
        }
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = [])
    {
        $this->log($message, 'info', $context);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = [])
    {
        $this->log($message, 'warning', $context);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = [])
    {
        $this->log($message, 'error', $context);
    }
}
