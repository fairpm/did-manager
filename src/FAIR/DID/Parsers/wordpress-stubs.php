<?php

/**
 * WordPress function stubs for the afragen/wordpress-plugin-readme-parser library.
 *
 * The WordPress.org readme parser uses several WordPress core functions.
 * This file provides standalone implementations of those functions for use
 * outside of a WordPress environment.
 *
 * @package FairDidManager
 */

declare(strict_types=1);

// Only define if not already defined (e.g., when running in WordPress context)

if (!function_exists('esc_html')) {
    /**
     * Escaping for HTML blocks.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses')) {
    /**
     * Filters text content and strips out disallowed HTML.
     *
     * @param string $content Content to filter.
     * @param array  $allowed_html Allowed HTML elements.
     * @return string Filtered content.
     */
    function wp_kses(string $content, array $allowed_html): string
    {
        // Build allowed tags string for strip_tags
        $allowed_tags = '';
        foreach (array_keys($allowed_html) as $tag) {
            $allowed_tags .= '<' . $tag . '>';
        }

        // Strip disallowed tags
        $content = strip_tags($content, $allowed_tags);

        // Remove disallowed attributes from allowed tags
        foreach ($allowed_html as $tag => $attributes) {
            if (empty($attributes)) {
                // Remove all attributes from this tag
                $content = preg_replace('/<' . $tag . '\s+[^>]*>/i', '<' . $tag . '>', $content);
            }
        }

        return $content;
    }
}

if (!function_exists('force_balance_tags')) {
    /**
     * Balances tags of string using a modified stack.
     *
     * @param string $text Text to be balanced.
     * @return string Balanced text.
     */
    function force_balance_tags(string $text): string
    {
        // Simple implementation - just return the text as-is for now
        // A full implementation would balance unclosed tags
        return $text;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    /**
     * Properly strips all HTML tags including script and style.
     *
     * @param string $text          String containing HTML tags.
     * @param bool   $remove_breaks Whether to remove line breaks.
     * @return string The processed string.
     */
    function wp_strip_all_tags(string $text, bool $remove_breaks = false): string
    {
        // Remove script and style blocks
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);

        // Strip remaining tags
        $text = strip_tags($text);

        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }

        return trim($text);
    }
}

if (!function_exists('get_user_by')) {
    /**
     * Retrieve user info by a given field.
     *
     * Stub that returns false since we're not in WordPress.
     *
     * @param string     $field The field to retrieve user by.
     * @param int|string $value A value for $field.
     * @return object|false User object on success, false on failure.
     */
    function get_user_by(string $field, $value)
    {
        // Return a mock user object with the nicename set to the value
        // This allows contributors to pass through without WordPress
        if ($field === 'login' || $field === 'slug') {
            $user = new stdClass();
            $user->user_nicename = is_string($value) ? $value : (string)$value;
            return $user;
        }
        return false;
    }
}
