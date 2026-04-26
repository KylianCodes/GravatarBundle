# GravatarBundle

A Symfony bundle to integrate [Gravatar](https://gravatar.com) into your application.  
Supports avatar URLs, base64 privacy mode, profile data, form validation, CLI tooling, and Symfony Profiler integration.

[![Latest Version](https://img.shields.io/packagist/v/kyliancodes/gravatar-bundle.svg)](https://packagist.org/packages/kyliancodes/gravatar-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/kyliancodes/gravatar-bundle.svg)](https://packagist.org/packages/kyliancodes/gravatar-bundle)
[![License](https://img.shields.io/packagist/l/kyliancodes/gravatar-bundle.svg)](LICENSE)

---

## Quick Start

```bash
composer require kyliancodes/gravatar-bundle
```

Create `config/packages/gravatar.yaml`:

```yaml
gravatar:
    size: 80
    default: mp
```

Use in Twig:

```twig
<img src="{{ gravatar('user@example.com') }}" alt="Avatar">

{{ gravatar_tag('user@example.com', {size: 120, alt: 'Avatar', class: 'rounded'}) }}
```

That's it. See below for all available options and features.

---

## Requirements

- PHP **8.1** or higher
- Symfony **5.4**, **6.x**, **7.x**, or **8.x**

---

## Installation

```bash
composer require kyliancodes/gravatar-bundle
```

If you are not using Symfony Flex, register the bundle manually in `config/bundles.php`:

```php
return [
    KylianCodes\GravatarBundle\GravatarBundle::class => ['all' => true],
];
```

> **Note:** No Flex recipe is provided. You must create `config/packages/gravatar.yaml` manually (see Configuration below).

---

## Configuration

Create `config/packages/gravatar.yaml` with your desired defaults:

```yaml
gravatar:
    api_key: null         # Gravatar API key (recommended for profile data)
    size: 80              # Avatar size in pixels (1–2048). Default: 80
    rating: g             # Maximum content rating: g | pg | r | x. Default: g
    default: mp           # Fallback image: mp | identicon | monsterid | wavatar | retro | robohash | blank | 404
    format: url           # Output format: url | base64. Default: url
    cache:
        enabled: false    # Enable Symfony Cache for avatars and profiles. Default: false
        ttl: 3600         # Cache lifetime in seconds. Default: 3600
        pool: cache.app   # Symfony cache pool service ID. Default: cache.app
```

> All options are optional. The bundle works out of the box with no configuration.

---

## Usage

### PHP Service

Inject `GravatarService` into any controller, service, or command:

```php
use KylianCodes\GravatarBundle\Service\GravatarService;

class MyController extends AbstractController
{
    public function __construct(private GravatarService $gravatar) {}

    public function profile(): Response
    {
        // Avatar URL
        $url = $this->gravatar->getUrl('user@example.com');
        $url = $this->gravatar->getUrl('user@example.com', size: 120, rating: 'pg');

        // Avatar as base64 data URI (privacy mode — hash never exposed to browser)
        $img = $this->gravatar->getBase64('user@example.com');

        // Auto-detect format from configuration
        $src = $this->gravatar->get('user@example.com', size: 100);

        // Check if an email has a Gravatar account
        $exists = $this->gravatar->exists('user@example.com'); // bool

        // Fetch profile data (requires API key for full details)
        $profile = $this->gravatar->getProfile('user@example.com');
        // [
        //     'display_name' => 'John Doe',
        //     'description'  => 'PHP Developer',
        //     'links'        => [['label' => 'Blog', 'url' => 'https://...']],
        // ]

        // Get the avatar URL of the currently authenticated user
        $email = $this->gravatar->getCurrentUserEmail();
        $src   = $this->gravatar->get($email);
    }
}
```

---

### Twig

Five functions are available in any Twig template.

#### `gravatar(email, size, rating, default, format)`

Returns the avatar URL or base64 data URI.

```twig
<img src="{{ gravatar('user@example.com') }}" alt="Avatar">
<img src="{{ gravatar('user@example.com', 120, 'g', 'identicon') }}" alt="Avatar">
```

---

#### `gravatar_tag(email, options)`

Returns a complete `<img>` tag with `loading="lazy"` and XSS-safe attributes.

```twig
{{ gravatar_tag('user@example.com', {
    size: 80,
    alt: 'User avatar',
    class: 'avatar rounded',
    id: 'user-pic',
}) }}
```

Output:
```html
<img src="https://www.gravatar.com/avatar/...?s=80&r=g&d=mp" alt="User avatar" loading="lazy" class="avatar rounded" id="user-pic">
```

---

#### `gravatar_exists(email)`

Returns `true` if the email has a Gravatar account.

```twig
{% if gravatar_exists('user@example.com') %}
    <img src="{{ gravatar('user@example.com') }}" alt="Avatar">
{% else %}
    <img src="{{ asset('images/default-avatar.png') }}" alt="Avatar">
{% endif %}
```

---

#### `gravatar_profile(email)`

Returns profile data as an array, or `null` if no profile exists.

```twig
{% set profile = gravatar_profile('user@example.com') %}

{% if profile %}
    <h2>{{ profile.display_name }}</h2>
    <p>{{ profile.description }}</p>

    {% for link in profile.links %}
        <a href="{{ link.url }}">{{ link.label }}</a>
    {% endfor %}
{% endif %}
```

---

#### `gravatar_user(size, rating, default, format)`

Returns the avatar for the currently authenticated Symfony user. Returns `null` if no user is logged in.

```twig
{% set avatar = gravatar_user() %}

{% if avatar %}
    <img src="{{ avatar }}" alt="My avatar" loading="lazy">
{% endif %}

{# Or with a custom size #}
{% set avatar = gravatar_user(40) %}
{% if avatar %}
    <img src="{{ avatar }}" alt="My avatar" loading="lazy">
{% endif %}
```

---

### CLI Command

Check whether an email address has a Gravatar account from the terminal:

```bash
php bin/console gravatar:check user@example.com
```

**Output with a Gravatar account:**
```
 Gravatar Check
================

 Email: user@example.com

 [OK] Gravatar account found!

 URL: https://www.gravatar.com/avatar/55502f40dc8b7c769880b10874abc9d0?s=80&r=g&d=mp

 Profile
--------
 Name:  John Doe
 Bio:   PHP Developer
 Links:
   - Blog: https://example.com
```

**Output without a Gravatar account:**
```
 [WARNING] No Gravatar account found for this email.
```

---

### Form Validation

Use `#[GravatarExists]` on any email property to validate that it has an associated Gravatar account:

```php
use KylianCodes\GravatarBundle\Validator\GravatarExists;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileType
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[GravatarExists]
    public string $email = '';
}
```

The constraint skips `null` and empty string values — combine it with `#[Assert\NotBlank]` as needed.

---

## Privacy Mode

By default (`format: url`), the avatar URL containing the MD5 hash of the email is rendered directly in the HTML:

```html
<img src="https://www.gravatar.com/avatar/55502f40dc8b7c769880b10874abc9d0?s=80">
```

Setting `format: base64` fetches the image server-side and inlines it as a data URI. The hash never reaches the browser:

```html
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRg...">
```

Enable globally:

```yaml
gravatar:
    format: base64
    cache:
        enabled: true   # Strongly recommended with base64 to avoid fetching on every request
        ttl: 3600
```

Or per call:

```twig
{{ gravatar('user@example.com', format='base64') }}
```

---

## Cache

Enable caching to avoid repeated HTTP requests to Gravatar (especially useful with `base64` format or frequent profile lookups):

```yaml
gravatar:
    cache:
        enabled: true
        ttl: 3600         # seconds
        pool: cache.app   # any Symfony cache pool
```

Example with a dedicated Redis pool:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.gravatar:
                adapter: cache.adapter.redis

# config/packages/gravatar.yaml
gravatar:
    cache:
        enabled: true
        ttl: 7200
        pool: cache.gravatar
```

**What is cached:** `getBase64()` and `getProfile()` responses.  
**What is not cached:** `getUrl()` (no HTTP call) and `exists()` (HEAD request, fast by design).

---

## Symfony Profiler

When `kernel.debug` is `true`, a **Gravatar** panel appears in the Symfony Web Profiler toolbar showing:

- Total number of Gravatar calls per request
- Cache hit / miss per call
- Duration of each call in milliseconds
- Error status per call

No overhead in production — the collector is only wired in debug mode.

---

## Content Security Policy (CSP)

The required CSP directive depends on the configured `format`.

### `format: url` (default)

The browser fetches the avatar image directly from Gravatar. Allow the Gravatar CDN in your `img-src`:

```
Content-Security-Policy: img-src 'self' https://www.gravatar.com https://secure.gravatar.com https://*.gravatar.com;
```

### `format: base64` (privacy mode)

The image is fetched server-side and inlined as a `data:` URI. The browser makes no external request — no Gravatar domain needed:

```
Content-Security-Policy: img-src 'self' data:;
```

---

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a pull request.

Found a bug? [Open an issue](https://github.com/KylianCodes/GravatarBundle/issues/new/choose) — bug report and feature request templates are available.

---

## Credits

This bundle was created and is maintained by **[Kylian](https://github.com/kyliancodes)**.

It was inspired by [Pyrrah/GravatarBundle](https://github.com/Pyrrah/GravatarBundle). The original project could not be used directly in a Symfony 8 application, so this bundle was created to modernize the idea with full compatibility for Symfony 5.4 through 8.x, PHP 8.1+, and the Gravatar REST API v3.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
