# Contributing to lphenom/redis

Thank you for considering contributing to `lphenom/redis`!

## Getting started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR-USERNAME/redis`
3. Start the development environment: `make up`
4. Install dependencies: `make install`

## Development workflow

```bash
# Run tests
make test

# Run linter (dry-run)
make lint

# Auto-fix code style
make lint-fix

# Run static analysis
make analyse

# Run all checks
make check
```

## Code style

- PHP 8.1+ syntax
- `declare(strict_types=1);` in every file
- PSR-12 code style (enforced by PHP CS Fixer)
- PHPDoc on all public methods

## KPHP compatibility

All code must be **KPHP-compatible**. See [docs/kphp-compatibility.md](docs/kphp-compatibility.md).

**Forbidden:**
- `Reflection` API
- `eval()`
- `new $className()` (dynamic class instantiation)
- `$$varName` (variable variables)
- `callable` in typed arrays — use interfaces instead
- Constructor property promotion
- `readonly` properties
- `str_starts_with()`, `str_ends_with()`, `str_contains()` — use `substr()`/`strpos()`

Verify KPHP compatibility before submitting:

```bash
make kphp-check
```

## Commit style

Small, focused commits following the convention:

```
type(scope): description
```

Types: `feat`, `fix`, `test`, `docs`, `chore`, `refactor`

Examples:
- `feat(client): add hset/hget support`
- `fix(pipeline): handle Redis::PIPELINE false return`
- `test(client): add integration test for blpop`
- `docs(pubsub): add psubscribe usage example`

## Pull requests

- All CI checks must pass
- Tests must cover new functionality
- Update docs if API changes

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

