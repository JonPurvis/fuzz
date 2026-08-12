# Contributing

Thank you for considering contributing to `jonpurvis/fuzz`.

## Setup

```bash
composer install
composer test
```

## Guidelines

- PHP 8.4+, Pest 5
- Keep the fuzzer worker isolated from the Pest parent process
- Prefer small, focused pull requests
- Run `composer test` before opening a PR (Pint, PHPStan, Pest)
