<?php
declare(strict_types=1);

/**
 * Request guards for the public header search endpoint.
 *
 * APCu is preferred because it is in-memory and shared by PHP workers on the
 * same host. The locked temporary-file fallback keeps the protection useful on
 * hosts where APCu is not installed. The client address intentionally comes
 * from REMOTE_ADDR only; forwarded headers are user-controlled unless a
 * trusted proxy has already rewritten the server variable.
 */

function homeSearchClientAddress(): string
{
    $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (filter_var($address, FILTER_VALIDATE_IP)) {
        return $address;
    }

    // A missing/invalid address must share a conservative bucket rather than
    // bypassing the limiter.
    return 'unknown-client';
}

/**
 * @return array{allowed: bool, retry_after: int, available: bool}
 */
function homeSearchRateLimit(string $scope, int $limit, int $windowSeconds): array
{
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $key = hash('sha256', 'alh-home-search|' . $scope . '|' . homeSearchClientAddress());
    $now = time();

    if (function_exists('apcu_fetch') && function_exists('apcu_store') && function_exists('apcu_inc')) {
        $countKey = 'alh_home_search_count_' . $key;
        $resetKey = 'alh_home_search_reset_' . $key;
        $resetAt = (int) apcu_fetch($resetKey);

        if ($resetAt <= $now) {
            $resetAt = $now + $windowSeconds;
            apcu_store($resetKey, $resetAt, $windowSeconds + 5);
            apcu_store($countKey, 0, $windowSeconds + 5);
        }

        $incremented = false;
        $count = (int) apcu_inc($countKey, 1, $incremented, $windowSeconds + 5);
        if (!$incremented) {
            apcu_store($countKey, 1, $windowSeconds + 5);
            $count = 1;
        }

        if ($count > $limit) {
            return [
                'allowed' => false,
                'retry_after' => max(1, $resetAt - $now),
                'available' => true,
            ];
        }

        return ['allowed' => true, 'retry_after' => 0, 'available' => true];
    }

    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'ahmad-learning-hub-home-search';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return ['allowed' => false, 'retry_after' => 60, 'available' => false];
    }

    // The fallback uses one small file per client key. Periodically remove
    // expired files so a stream of unique addresses cannot grow the temp
    // directory without bound.
    if (mt_rand(1, 100) === 1) {
        $staleBefore = $now - max(300, $windowSeconds * 2);
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.lock') ?: [] as $stalePath) {
            $modifiedAt = @filemtime($stalePath);
            if ($modifiedAt !== false && $modifiedAt < $staleBefore) {
                @unlink($stalePath);
            }
        }
    }

    $path = $directory . DIRECTORY_SEPARATOR . $key . '.lock';
    $handle = @fopen($path, 'c+b');
    if ($handle === false || !@flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return ['allowed' => false, 'retry_after' => 60, 'available' => false];
    }

    $contents = stream_get_contents($handle);
    $parts = preg_split('/\s+/', trim((string) $contents));
    $resetAt = isset($parts[0]) ? (int) $parts[0] : 0;
    $count = isset($parts[1]) ? (int) $parts[1] : 0;

    if ($resetAt <= $now) {
        $resetAt = $now + $windowSeconds;
        $count = 0;
    }
    $count++;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, $resetAt . "\n" . $count);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($count > $limit) {
        return [
            'allowed' => false,
            'retry_after' => max(1, $resetAt - $now),
            'available' => true,
        ];
    }

    return ['allowed' => true, 'retry_after' => 0, 'available' => true];
}

function homeSearchRateLimitResponse(array $result): void
{
    $status = $result['available'] ? 429 : 503;
    if ($result['retry_after'] > 0) {
        header('Retry-After: ' . (int) $result['retry_after']);
    }
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $status === 429
            ? 'Too many search requests. Please wait a moment and try again.'
            : 'Search is temporarily unavailable. Please try again later.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function homeSearchRequestQuery(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    // Reject oversized input instead of silently truncating attacker data.
    if (strlen($value) > 512 || preg_match('//u', $value) !== 1) {
        return '';
    }

    $query = trim(function_exists('mb_substr')
        ? mb_substr($value, 0, 120, 'UTF-8')
        : substr($value, 0, 120));
    $query = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $query) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $query) ?? '');
}
