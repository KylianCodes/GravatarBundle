<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Service;

use KylianCodes\GravatarBundle\DataCollector\GravatarDataCollector;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provides Gravatar URL generation, image fetching, profile retrieval and existence checks.
 */
class GravatarService
{
    private const AVATAR_URL = 'https://www.gravatar.com/avatar/';
    private const PROFILE_API_URL = 'https://api.gravatar.com/v3/profiles/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly int $size = 80,
        private readonly string $rating = 'g',
        private readonly string $default = 'mp',
        private readonly string $format = 'url',
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
        private readonly ?string $apiKey = null,
        private readonly ?TokenStorageInterface $tokenStorage = null,
        private readonly ?GravatarDataCollector $collector = null,
    ) {
    }

    /**
     * Returns the Gravatar URL or base64-encoded image for a given email.
     */
    public function get(
        string $email,
        ?int $size = null,
        ?string $rating = null,
        ?string $default = null,
        ?string $format = null,
    ): string {
        $format ??= $this->format;

        return match ($format) {
            'base64' => $this->getBase64($email, $size, $rating, $default),
            default => $this->getUrl($email, $size, $rating, $default),
        };
    }

    /**
     * Returns the Gravatar avatar URL for a given email (uses MD5 hash).
     */
    public function getUrl(
        string $email,
        ?int $size = null,
        ?string $rating = null,
        ?string $default = null,
    ): string {
        $hash = $this->md5Hash($email);
        $params = http_build_query([
            's' => $size ?? $this->size,
            'r' => $rating ?? $this->rating,
            'd' => $default ?? $this->default,
        ]);

        return self::AVATAR_URL.$hash.'?'.$params;
    }

    /**
     * Returns the base64-encoded image for a given email, with optional cache.
     */
    public function getBase64(
        string $email,
        ?int $size = null,
        ?string $rating = null,
        ?string $default = null,
    ): string {
        $url = $this->getUrl($email, $size, $rating, $default);
        $cacheKey = 'gravatar_base64_'.md5($url);
        $start = microtime(true);
        $cacheHit = true;

        $fetch = function () use ($url): string {
            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? 'image/jpeg';

            return 'data:'.$contentType.';base64,'.base64_encode($content);
        };

        try {
            if ($this->cache !== null) {
                $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($fetch, &$cacheHit): string {
                    $item->expiresAfter($this->cacheTtl);
                    $cacheHit = false;

                    return $fetch();
                });
            } else {
                $result = $fetch();
            }

            $this->record('getBase64', $email, $start, $cacheHit, true);

            return $result;
        } catch (\Throwable $e) {
            $this->record('getBase64', $email, $start, false, false);
            throw $e;
        }
    }

    /**
     * Returns true if a Gravatar account exists for the given email.
     */
    public function exists(string $email): bool
    {
        $hash = $this->md5Hash($email);
        $url = self::AVATAR_URL.$hash.'?d=404';
        $start = microtime(true);

        try {
            $response = $this->httpClient->request('HEAD', $url);
            $exists = $response->getStatusCode() !== 404;
            $this->record('exists', $email, $start, false, true);

            return $exists;
        } catch (\Throwable) {
            $this->record('exists', $email, $start, false, false);

            return false;
        }
    }

    /**
     * Returns Gravatar profile data for the given email via the v3 REST API, or null if none exists.
     *
     * @return array{display_name?: string|null, description?: string|null, links?: array<mixed>}|null
     */
    public function getProfile(string $email): ?array
    {
        $hash = $this->sha256Hash($email);
        $url = self::PROFILE_API_URL.$hash;
        $cacheKey = 'gravatar_profile_'.$hash;
        $start = microtime(true);
        $cacheHit = true;

        $fetch = function () use ($url): ?array {
            try {
                $options = [];
                if ($this->apiKey !== null) {
                    $options['headers'] = ['Authorization' => 'Bearer '.$this->apiKey];
                }

                $response = $this->httpClient->request('GET', $url, $options);

                if ($response->getStatusCode() !== 200) {
                    return null;
                }

                $data = $response->toArray();

                return [
                    'display_name' => $data['display_name'] ?? null,
                    'description' => $data['description'] ?? null,
                    'links' => $data['links'] ?? [],
                ];
            } catch (\Throwable) {
                return null;
            }
        };

        if ($this->cache !== null) {
            $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($fetch, &$cacheHit): mixed {
                $item->expiresAfter($this->cacheTtl);
                $cacheHit = false;

                return $fetch();
            });
        } else {
            $result = $fetch();
        }

        $this->record('getProfile', $email, $start, $cacheHit, $result !== null);

        return $result;
    }

    /**
     * Returns the email of the currently authenticated Symfony user, or null.
     */
    public function getCurrentUserEmail(): ?string
    {
        if ($this->tokenStorage === null) {
            return null;
        }

        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return null;
        }

        $user = $token->getUser();

        if ($user === null) {
            return null;
        }

        if (method_exists($user, 'getEmail')) {
            return $user->getEmail();
        }

        if (method_exists($user, 'getUserIdentifier')) {
            $identifier = $user->getUserIdentifier();
            if (str_contains($identifier, '@')) {
                return $identifier;
            }
        }

        return null;
    }

    /** MD5 hash used for avatar URLs (Gravatar classic). */
    private function md5Hash(string $email): string
    {
        return md5(strtolower(trim($email)));
    }

    /** SHA256 hash used for the v3 REST API profile endpoint. */
    private function sha256Hash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function record(string $method, string $email, float $start, bool $cacheHit, bool $success): void
    {
        $this->collector?->addCall($method, $email, (microtime(true) - $start) * 1000, $cacheHit, $success);
    }
}
