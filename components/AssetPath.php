<?php

function base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = dirname($script, 2);
    $base = str_replace('\\', '/', $base);

    if ($base === '/' || $base === '.' || $base === '') {
        return '';
    }

    return $base;
}

function asset(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}
