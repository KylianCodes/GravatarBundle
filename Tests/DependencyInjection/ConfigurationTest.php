<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Tests\DependencyInjection;

use KylianCodes\GravatarBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function testDefaultValues(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertSame(80, $config['size']);
        $this->assertSame('g', $config['rating']);
        $this->assertSame('mp', $config['default']);
        $this->assertSame('url', $config['format']);
        $this->assertFalse($config['cache']['enabled']);
        $this->assertSame(3600, $config['cache']['ttl']);
        $this->assertSame('cache.app', $config['cache']['pool']);
    }

    public function testCustomValues(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[
            'size' => 200,
            'rating' => 'pg',
            'default' => 'identicon',
            'format' => 'base64',
            'cache' => [
                'enabled' => true,
                'ttl' => 7200,
                'pool' => 'cache.redis',
            ],
        ]]);

        $this->assertSame(200, $config['size']);
        $this->assertSame('pg', $config['rating']);
        $this->assertSame('identicon', $config['default']);
        $this->assertSame('base64', $config['format']);
        $this->assertTrue($config['cache']['enabled']);
        $this->assertSame(7200, $config['cache']['ttl']);
        $this->assertSame('cache.redis', $config['cache']['pool']);
    }

    public function testInvalidRatingThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'rating' => 'invalid',
        ]]);
    }

    public function testInvalidFormatThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'format' => 'svg',
        ]]);
    }

    public function testSizeMinBoundary(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'size' => 0,
        ]]);
    }

    public function testSizeMaxBoundary(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'size' => 2049,
        ]]);
    }

    public function testAllValidRatings(): void
    {
        foreach (['g', 'pg', 'r', 'x'] as $rating) {
            $config = $this->processor->processConfiguration($this->configuration, [[
                'rating' => $rating,
            ]]);
            $this->assertSame($rating, $config['rating']);
        }
    }
}
