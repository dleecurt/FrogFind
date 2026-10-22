<?php
// Shared helpers for FrogFind.

// A normal desktop browser User-Agent. Without one, PHP sends no UA at all,
// which DuckDuckGo and many sites treat as a bot and block.
define('FROG_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0');

function frog_http_options($method = 'GET') {
    return ['http' => [
        'method'          => $method,
        'header'          => "User-Agent: " . FROG_USER_AGENT . "\r\n" .
                             "Accept-Language: en-US,en;q=0.8\r\n",
        'timeout'         => 15,
        'follow_location' => 1,
    ]];
}

// Context for explicit use with file_get_contents()/get_headers().
function frog_context($method = 'GET') {
    return stream_context_create(frog_http_options($method));
}

// Also make it the default, so readfile() and imagecreatefrom*() send the UA too.
stream_context_set_default(frog_http_options());

// Returns 'jpg', 'png' or null, judged by the URL path's file extension.
function frog_image_type($url) {
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    if (preg_match('/\.jpe?g$/', $path)) return 'jpg';
    if (preg_match('/\.png$/', $path))   return 'png';
    return null;
}

// Case-insensitive lookup in a get_headers($url, true) array. When redirects
// were followed the value is an array; the last one is the final response's.
function frog_header($headers, $name) {
    if (!is_array($headers)) return null;
    foreach ($headers as $key => $value) {
        if (is_string($key) && strcasecmp($key, $name) === 0) {
            return is_array($value) ? end($value) : $value;
        }
    }
    return null;
}

// Escape text for HTML output.
function h($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}
