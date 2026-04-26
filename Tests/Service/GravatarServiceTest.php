<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Tests\Service;

use KylianCodes\GravatarBundle\DataCollector\GravatarDataCollector;
use KylianCodes\GravatarBundle\Service\GravatarService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GravatarServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private GravatarService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->service = new GravatarService($this->httpClient);
    }

    public function testGetUrlReturnsCorrectUrl(): void
    {
        $url = $this->service->getUrl('test@example.com');

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
        $this->assertStringContainsString(md5('test@example.com'), $url);
    }

    public function testGetUrlNormalizesEmail(): void
    {
        $url1 = $this->service->getUrl('Test@Example.COM');
        $url2 = $this->service->getUrl('test@example.com');

        $this->assertSame($url1, $url2);
    }

    public function testGetUrlContainsDefaultParams(): void
    {
        $url = $this->service->getUrl('test@example.com');

        $this->assertStringContainsString('s=80', $url);
        $this->assertStringContainsString('r=g', $url);
        $this->assertStringContainsString('d=mp', $url);
    }

    public function testGetUrlWithCustomParams(): void
    {
        $url = $this->service->getUrl('test@example.com', 200, 'pg', 'identicon');

        $this->assertStringContainsString('s=200', $url);
        $this->assertStringContainsString('r=pg', $url);
        $this->assertStringContainsString('d=identicon', $url);
    }

    public function testGetReturnsUrlByDefault(): void
    {
        $result = $this->service->get('test@example.com');

        $this->assertStringStartsWith('https://www.gravatar.com/avatar/', $result);
    }

    public function testGetBase64FetchesAndEncodesImage(): void
    {
        $imageContent = 'fake-image-binary-content';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($imageContent);
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('gravatar.com/avatar/'))
            ->willReturn($response);

        $result = $this->service->getBase64('test@example.com');

        $this->assertStringStartsWith('data:image/jpeg;base64,', $result);
        $this->assertStringContainsString(base64_encode($imageContent), $result);
    }

    public function testGetBase64UsesCacheWhenAvailable(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $service = new GravatarService($this->httpClient, cache: $cache, cacheTtl: 3600);

        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);

                return $callback($item);
            });

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('img');
        $response->method('getHeaders')->willReturn(['content-type' => ['image/png']]);

        $this->httpClient->method('request')->willReturn($response);

        $service->getBase64('test@example.com');
    }

    public function testExistsReturnsTrueWhenAvatarFound(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('HEAD', $this->stringContains('d=404'))
            ->willReturn($response);

        $this->assertTrue($this->service->exists('test@example.com'));
    }

    public function testExistsReturnsFalseWhenNoAvatar(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $this->httpClient->method('request')->willReturn($response);

        $this->assertFalse($this->service->exists('noavatar@example.com'));
    }

    public function testExistsReturnsFalseOnNetworkError(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('Network error'));

        $this->assertFalse($this->service->exists('test@example.com'));
    }

    public function testGetProfileReturnsProfileData(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'display_name' => 'John Doe',
            'description' => 'Developer',
            'links' => [['label' => 'Blog', 'url' => 'https://example.com']],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('api.gravatar.com/v3/profiles/'))
            ->willReturn($response);

        $profile = $this->service->getProfile('test@example.com');

        $this->assertSame('John Doe', $profile['display_name']);
        $this->assertSame('Developer', $profile['description']);
        $this->assertCount(1, $profile['links']);
    }

    public function testGetProfileSendsAuthHeaderWhenApiKeySet(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['display_name' => 'John']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->anything(), $this->callback(function (array $options): bool {
                return ($options['headers']['Authorization'] ?? '') === 'Bearer test-api-key';
            }))
            ->willReturn($response);

        $service = new GravatarService($this->httpClient, apiKey: 'test-api-key');
        $service->getProfile('test@example.com');
    }

    public function testGetProfileUsesNoAuthHeaderWithoutApiKey(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->anything(), [])
            ->willReturn($response);

        $this->service->getProfile('test@example.com');
    }

    public function testGetProfileReturnsNullWhenNotFound(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $this->httpClient->method('request')->willReturn($response);

        $this->assertNull($this->service->getProfile('noavatar@example.com'));
    }

    public function testGetProfileReturnsNullOnError(): void
    {
        $this->httpClient->method('request')->willThrowException(new \RuntimeException());

        $this->assertNull($this->service->getProfile('test@example.com'));
    }

    public function testGetCurrentUserEmailReturnsNullWithoutTokenStorage(): void
    {
        $this->assertNull($this->service->getCurrentUserEmail());
    }

    public function testGetCurrentUserEmailReturnsNullWithoutToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $service = new GravatarService($this->httpClient, tokenStorage: $tokenStorage);

        $this->assertNull($service->getCurrentUserEmail());
    }

    public function testGetCurrentUserEmailFromGetEmail(): void
    {
        $user = new class implements UserInterface {
            public function getEmail(): string { return 'user@example.com'; }
            public function getRoles(): array { return []; }
            public function eraseCredentials(): void {}
            public function getUserIdentifier(): string { return 'user@example.com'; }
        };

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $service = new GravatarService($this->httpClient, tokenStorage: $tokenStorage);

        $this->assertSame('user@example.com', $service->getCurrentUserEmail());
    }

    public function testGetCurrentUserEmailFromUserIdentifier(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('identifier@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $service = new GravatarService($this->httpClient, tokenStorage: $tokenStorage);

        $this->assertSame('identifier@example.com', $service->getCurrentUserEmail());
    }

    public function testGetBase64ReportsCacheMissToCollector(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $collector = $this->createMock(GravatarDataCollector::class);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);
            $item->method('expiresAfter');

            return $callback($item);
        });

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('img');
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);
        $this->httpClient->method('request')->willReturn($response);

        $collector->expects($this->once())
            ->method('addCall')
            ->with('getBase64', 'test@example.com', $this->anything(), false, true);

        $service = new GravatarService($this->httpClient, cache: $cache, collector: $collector);
        $service->getBase64('test@example.com');
    }

    public function testGetBase64ReportsCacheHitToCollector(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $collector = $this->createMock(GravatarDataCollector::class);

        $cache->method('get')->willReturn('data:image/jpeg;base64,cached');

        $collector->expects($this->once())
            ->method('addCall')
            ->with('getBase64', 'test@example.com', $this->anything(), true, true);

        $service = new GravatarService($this->httpClient, cache: $cache, collector: $collector);
        $service->getBase64('test@example.com');
    }

    public function testGetProfileReportsCacheMissToCollector(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $collector = $this->createMock(GravatarDataCollector::class);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);
            $item->method('expiresAfter');

            return $callback($item);
        });

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['display_name' => 'John']);
        $this->httpClient->method('request')->willReturn($response);

        $collector->expects($this->once())
            ->method('addCall')
            ->with('getProfile', 'test@example.com', $this->anything(), false, true);

        $service = new GravatarService($this->httpClient, cache: $cache, collector: $collector);
        $service->getProfile('test@example.com');
    }

    public function testGetProfileReportsCacheHitToCollector(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $collector = $this->createMock(GravatarDataCollector::class);

        $cache->method('get')->willReturn(['display_name' => 'John', 'description' => null, 'links' => []]);

        $collector->expects($this->once())
            ->method('addCall')
            ->with('getProfile', 'test@example.com', $this->anything(), true, true);

        $service = new GravatarService($this->httpClient, cache: $cache, collector: $collector);
        $service->getProfile('test@example.com');
    }
}
