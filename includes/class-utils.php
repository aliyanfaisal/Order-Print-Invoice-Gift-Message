<?php
if (!defined('ABSPATH')) {
    exit;
}

class Opigm_Utils
{

    public static function is_hebrew($text)
    {
        if (empty($text)) {
            return false;
        }

        // Ensure UTF-8 encoding to prevent regex failures
        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // Unicode range for Hebrew: 0590–05FF and Presentation Forms: FB1D–FB4F
        return preg_match('/[\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]/u', $text) > 0;
    }

    public static function render_bidi($text)
    {
        if (empty($text)) {
            return $text;
        }

        // Check if there is any Hebrew character to trigger processing
        if (!self::is_hebrew($text)) {
            return $text;
        }

        // Standardize line breaks: convert <br/> variants to \n for processing
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        $lines = explode("\n", $text);
        $new_lines = [];

        foreach ($lines as $line) {
            $new_lines[] = self::process_bidi_line($line);
        }

        // Return with <br/> for HTML output consistency in PDFs
        return implode("<br/>", $new_lines);
    }

    private static function process_bidi_line($text)
    {
        // Simple but effective Bidi implementation for Hebrew
        // Splitting into segments of Hebrew and Non-Hebrew
        // Include Presentation Forms in regex: \x{FB1D}-\x{FB4F}
        preg_match_all('/([\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}\s\p{P}]+)|([^\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]+)/u', $text, $matches);

        $segments = $matches[0];

        // If regex fails (e.g. bad encoding passed through), cleanup
        if (empty($segments)) {
            return $text;
        }

        $is_rtl_line = self::is_hebrew($text);

        if (!$is_rtl_line) {
            return $text;
        }

        // Visual reordering: Reverse the segments order for the whole line if it's mostly RTL
        // but we need to keep LTR segments internal order.
        $reversed_segments = array_reverse($segments);
        $result = '';

        foreach ($reversed_segments as $seg) {
            if (self::is_hebrew($seg)) {
                // Reverse Hebrew characters visually
                $result .= self::utf8_strrev($seg);
            } else {
                // Keep LTR segments (Numbers, English) in their logical order
                $result .= $seg;
            }
        }

        return $result;
    }

    public static function utf8_strrev($str)
    {
        preg_match_all('/./us', $str, $ar);
        return implode('', array_reverse($ar[0]));
    }

    public static function is_order_invoiceable($order)
    {
        if (!$order) {
            return false;
        }

        $status = $order->get_status();

        // Only allow invoice for Processing, Completed, and custom paid statuses
        $allowed_statuses = apply_filters('opigm_invoiceable_statuses', [
            'processing',
            'completed',
        ]);

        return in_array($status, $allowed_statuses);
    }
}
