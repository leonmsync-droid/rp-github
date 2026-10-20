<?php
declare(strict_types=1);

// Minimal GitHub REST client: authentication, the search speed limit and rate-limit waits.
final class GitHub
{
    private const API = 'https://api.github.com';
    private const MAX_WAIT = 900;

    private float $lastSearch = 0.0;

    public function __construct(private string $token, private string $userAgent)
    {
    }

    public function authenticated(): bool
    {
        return $this->token !== '';
    }

    // Search allows 30 requests/minute with a token and 10 without; stay just under.
    public function search(string $q, int $page = 1, int $perPage = 100): array
    {
        $gap = $this->authenticated() ? 2.1 : 6.5;
        $wait = $this->lastSearch + $gap - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1e6));
        }
        $this->lastSearch = microtime(true);
        return $this->get('/search/repositories', ['q' => $q, 'sort' => 'stars', 'order' => 'desc', 'per_page' => $perPage, 'page' => $page]);
    }

    /** @return array{status:int, headers:array, body:mixed, raw:string} */
    public function get(string $path, array $query = []): array
    {
        $url = self::API . $path . ($query ? '?' . http_build_query($query) : '');
        for ($attempt = 1; ; $attempt++) {
            try {
                [$status, $headers, $raw] = $this->request($url);
            } catch (RuntimeException $e) {
                // Network errors (timeouts, dropped connections) get the same three retries as server errors.
                if ($attempt > 3) {
                    throw $e;
                }
                logline($e->getMessage() . '; retrying');
                sleep(10 * $attempt);
                continue;
            }
            $this->pauseIfNearLimit($headers);
            $limited = in_array($status, [403, 429], true)
                && (isset($headers['retry-after']) || ($headers['x-ratelimit-remaining'] ?? null) === '0');
            if ($limited && $attempt <= 3) {
                $wait = isset($headers['retry-after'])
                    ? (int) $headers['retry-after']
                    : (int) ($headers['x-ratelimit-reset'] ?? time() + 60) - time() + 2;
                $wait = max(5, min(self::MAX_WAIT, $wait));
                logline("GitHub rate limit hit; waiting {$wait}s");
                sleep($wait);
                continue;
            }
            if ($status >= 500 && $attempt <= 3) {
                sleep(5 * $attempt);
                continue;
            }
            if ($status !== 200 && $status !== 204) {
                throw new RuntimeException("GitHub {$status} for {$path}: " . substr($raw, 0, 200));
            }
            return ['status' => $status, 'headers' => $headers, 'body' => json_decode($raw, true), 'raw' => $raw];
        }
    }

    // Long runs (one call per repo) sleep through the hourly reset instead of failing.
    private function pauseIfNearLimit(array $headers): void
    {
        if (($headers['x-ratelimit-resource'] ?? '') !== 'core' || (int) ($headers['x-ratelimit-remaining'] ?? 100) > 20) {
            return;
        }
        $wait = max(5, min(3700, (int) ($headers['x-ratelimit-reset'] ?? time()) - time() + 5));
        logline("Hourly API limit nearly used; pausing {$wait}s until it resets");
        sleep($wait);
    }

    private function request(string $url): array
    {
        $headers = [];
        $ch = curl_init($url);
        $send = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($this->authenticated()) {
            $send[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $send,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            throw new RuntimeException("GitHub request failed: {$error}");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$status, $headers, (string) $raw];
    }
}
