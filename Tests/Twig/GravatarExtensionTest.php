<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Tests\Twig;

use KylianCodes\GravatarBundle\Service\GravatarService;
use KylianCodes\GravatarBundle\Twig\GravatarExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GravatarExtensionTest extends TestCase
{
    private GravatarService&MockObject $gravatarService;
    private GravatarExtension $extension;

    protected function setUp(): void
    {
        $this->gravatarService = $this->createMock(GravatarService::class);
        $this->extension = new GravatarExtension($this->gravatarService);
    }

    public function testGetFunctionsReturnsAllFunctions(): void
    {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn ($f) => $f->getName(), $functions);

        $this->assertContains('gravatar', $names);
        $this->assertContains('gravatar_tag', $names);
        $this->assertContains('gravatar_exists', $names);
        $this->assertContains('gravatar_profile', $names);
        $this->assertContains('gravatar_user', $names);
    }

    public function testGravatarDelegatesToService(): void
    {
        $this->gravatarService->expects($this->once())
            ->method('get')
            ->with('test@example.com', 100, 'g', 'mp', 'url')
            ->willReturn('https://gravatar.com/avatar/hash');

        $result = $this->extension->gravatar('test@example.com', 100, 'g', 'mp', 'url');

        $this->assertSame('https://gravatar.com/avatar/hash', $result);
    }

    public function testGravatarTagReturnsImgTag(): void
    {
        $this->gravatarService->method('get')->willReturn('https://gravatar.com/avatar/hash?s=80');

        $html = $this->extension->gravatarTag('test@example.com', ['alt' => 'Avatar']);

        $this->assertStringStartsWith('<img ', $html);
        $this->assertStringContainsString('src="https://gravatar.com/avatar/hash', $html);
        $this->assertStringContainsString('alt="Avatar"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function testGravatarTagIncludesClassAndId(): void
    {
        $this->gravatarService->method('get')->willReturn('https://gravatar.com/avatar/hash');

        $html = $this->extension->gravatarTag('test@example.com', [
            'class' => 'avatar rounded',
            'id' => 'user-avatar',
        ]);

        $this->assertStringContainsString('class="avatar rounded"', $html);
        $this->assertStringContainsString('id="user-avatar"', $html);
    }

    public function testGravatarTagEscapesXss(): void
    {
        $this->gravatarService->method('get')->willReturn('https://gravatar.com/avatar/hash');

        $html = $this->extension->gravatarTag('test@example.com', [
            'alt' => '<script>alert("xss")</script>',
            'class' => '" onload="evil()',
        ]);

        // Raw < and " must not appear unescaped inside attribute values
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&quot;', $html);
        // The injected " is escaped so the attribute boundary is intact
        $this->assertStringNotContainsString('class="" onload=', $html);
    }

    public function testGravatarExistsDelegatesToService(): void
    {
        $this->gravatarService->expects($this->once())
            ->method('exists')
            ->with('test@example.com')
            ->willReturn(true);

        $this->assertTrue($this->extension->gravatarExists('test@example.com'));
    }

    public function testGravatarProfileDelegatesToService(): void
    {
        $profile = ['display_name' => 'John', 'description' => 'Dev', 'links' => []];

        $this->gravatarService->expects($this->once())
            ->method('getProfile')
            ->with('test@example.com')
            ->willReturn($profile);

        $this->assertSame($profile, $this->extension->gravatarProfile('test@example.com'));
    }

    public function testGravatarUserReturnsNullWhenNoUser(): void
    {
        $this->gravatarService->method('getCurrentUserEmail')->willReturn(null);

        $this->assertNull($this->extension->gravatarUser());
    }

    public function testGravatarUserDelegatesToService(): void
    {
        $this->gravatarService->method('getCurrentUserEmail')->willReturn('user@example.com');
        $this->gravatarService->expects($this->once())
            ->method('get')
            ->with('user@example.com', null, null, null, null)
            ->willReturn('https://gravatar.com/avatar/userhash');

        $result = $this->extension->gravatarUser();

        $this->assertSame('https://gravatar.com/avatar/userhash', $result);
    }
}
