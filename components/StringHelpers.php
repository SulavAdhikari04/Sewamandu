<?php
/**
 * Shared string formatting helpers.
 */

function formatDisplayName(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_convert_case')) {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($value));
}
