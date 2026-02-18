# Contributing

Contributions are welcome! Please follow these guidelines.

## Development Setup

```bash
git clone https://github.com/moneo/laravel-markdown-for-agents.git
cd markdown-for-agents
composer install
```

## Coding Standards

This project uses **Laravel Pint** for code style and **PHPStan** at level 8 for static analysis.

```bash
# Fix code style
vendor/bin/pint

# Run static analysis
vendor/bin/phpstan analyse

# Run tests
vendor/bin/phpunit
```

## Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass and code style/static analysis are clean
5. Commit your changes
6. Push to your fork and open a pull request

## Testing

All tests must mock HTTP responses. Never hit real Cloudflare APIs in tests.

```bash
vendor/bin/phpunit
```
