<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Twig;

use KylianCodes\GravatarBundle\Service\GravatarService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides Gravatar-related Twig functions.
 */
class GravatarExtension extends AbstractExtension
{
    public function __construct(private readonly GravatarService $gravatarService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gravatar', $this->gravatar(...)),
            new TwigFunction('gravatar_tag', $this->gravatarTag(...), ['is_safe' => ['html']]),
            new TwigFunction('gravatar_exists', $this->gravatarExists(...)),
            new TwigFunction('gravatar_profile', $this->gravatarProfile(...)),
            new TwigFunction('gravatar_user', $this->gravatarUser(...)),
        ];
    }

    /**
     * Returns the Gravatar URL or base64-encoded image for the given email.
     */
    public function gravatar(
        string $email,
        ?int $size = null,
        ?string $rating = null,
        ?string $default = null,
        ?string $format = null,
    ): string {
        return $this->gravatarService->get($email, $size, $rating, $default, $format);
    }

    /**
     * Returns a full <img> HTML tag for the given email.
     *
     * @param array{
     *     size?: int,
     *     rating?: string,
     *     default?: string,
     *     format?: string,
     *     alt?: string,
     *     class?: string,
     *     id?: string,
     * } $options
     */
    public function gravatarTag(string $email, array $options = []): string
    {
        $src = $this->gravatarService->get(
            $email,
            isset($options['size']) ? (int) $options['size'] : null,
            $options['rating'] ?? null,
            $options['default'] ?? null,
            $options['format'] ?? null,
        );

        $alt = htmlspecialchars($options['alt'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attrs = 'src="'.htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        $attrs .= ' alt="'.$alt.'"';
        $attrs .= ' loading="lazy"';

        if (isset($options['class'])) {
            $attrs .= ' class="'.htmlspecialchars($options['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        if (isset($options['id'])) {
            $attrs .= ' id="'.htmlspecialchars($options['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return '<img '.$attrs.'>';
    }

    /**
     * Returns true if the given email has a Gravatar account.
     */
    public function gravatarExists(string $email): bool
    {
        return $this->gravatarService->exists($email);
    }

    /**
     * Returns profile data for the given email, or null.
     *
     * @return array{display_name?: string|null, description?: string|null, links?: array<mixed>}|null
     */
    public function gravatarProfile(string $email): ?array
    {
        return $this->gravatarService->getProfile($email);
    }

    /**
     * Returns the Gravatar URL or base64 for the currently authenticated user.
     */
    public function gravatarUser(
        ?int $size = null,
        ?string $rating = null,
        ?string $default = null,
        ?string $format = null,
    ): ?string {
        $email = $this->gravatarService->getCurrentUserEmail();

        if ($email === null) {
            return null;
        }

        return $this->gravatarService->get($email, $size, $rating, $default, $format);
    }
}
