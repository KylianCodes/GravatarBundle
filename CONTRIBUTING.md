# Contributing to GravatarBundle

Thank you for considering contributing! Here is everything you need to get started.

## Reporting a Bug

Use the **Bug Report** issue template on GitHub. Please include:

- Your PHP and Symfony versions
- The full error message or stack trace
- A minimal reproduction case

## Suggesting a Feature

Use the **Feature Request** issue template. Describe the use case and the expected behavior.

## Submitting a Pull Request

1. Fork the repository and create a branch from `main`
2. Write your changes — follow the coding standards below
3. Add or update tests to cover your changes
4. Make sure all tests pass: `vendor/bin/phpunit`
5. Open a pull request against `main` with a clear description

## Coding Standards

- PHP **8.1+** syntax
- `declare(strict_types=1)` in every file
- Follow PSR-12
- No comments explaining *what* the code does — only *why* when non-obvious
- No added features or abstractions beyond what the fix or feature requires

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

## Compatibility

All changes must remain compatible with:

- PHP 8.1, 8.2, 8.3, 8.4, 8.5
- Symfony 5.4, 6.4, 7.2, 7.4, 8.0

The CI matrix will validate this automatically on every pull request.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
