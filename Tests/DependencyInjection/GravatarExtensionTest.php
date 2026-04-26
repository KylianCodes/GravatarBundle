<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Tests\DependencyInjection;

use KylianCodes\GravatarBundle\DependencyInjection\GravatarExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GravatarExtensionTest extends TestCase
{
    public function testPrependRegistersTwigPath(): void
    {
        $container = new ContainerBuilder();
        $extension = new GravatarExtension();
        $extension->prepend($container);

        $twigConfigs = $container->getExtensionConfig('twig');

        $this->assertNotEmpty($twigConfigs);
        $this->assertArrayHasKey('paths', $twigConfigs[0]);
        $this->assertContains('GravatarBundle', $twigConfigs[0]['paths']);
    }

    public function testPrependTwigPathPointsToExistingDirectory(): void
    {
        $container = new ContainerBuilder();
        $extension = new GravatarExtension();
        $extension->prepend($container);

        $twigConfigs = $container->getExtensionConfig('twig');
        $path = array_search('GravatarBundle', $twigConfigs[0]['paths']);

        $this->assertDirectoryExists($path);
    }
}
