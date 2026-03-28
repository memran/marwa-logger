# Repository Guidelines

## Project Structure & Module Organization
`src/` contains the library code under the `Marwa\Logger\` namespace. Core entry points live in `src/Logger.php` and `src/SimpleLogger.php`, contracts in `src/Contracts/`, storage backends in `src/Storage/`, and helpers such as sensitive-data filtering in `src/Support/`. Usage samples live in `example/`. There is no committed `tests/` directory yet; add new PHPUnit tests under `tests/` with namespaces mirroring `src/`.

## Build, Test, and Development Commands
Install dependencies with `composer install`. Regenerate autoload metadata with `composer dump-autoload` after changing namespaces or file locations. Run the example locally with `php example/example.php` or `php example/index.php` to validate basic logger bootstrapping. PHPUnit is available through Composer, so run `vendor/bin/phpunit` once tests are added.

## Coding Style & Naming Conventions
Target PHP 8.1+ and keep `declare(strict_types=1);` at the top of PHP files. Follow the existing PSR-12 style: 4-space indentation, one class per file, `final` where extension is not intended, and typed properties/method signatures whenever practical. Use `PascalCase` for classes, `camelCase` for methods and properties, and keep namespace paths aligned with file paths, for example `Marwa\Logger\Storage\FileSink`.

## Testing Guidelines
Use PHPUnit 10 for all new tests. Name test files `*Test.php`, for example `tests/Storage/FileSinkTest.php`. Cover both expected output and edge cases, especially filtering, log-level gating, request ID propagation, and file rotation behavior. Prefer small, isolated tests over broad integration scripts, and run `vendor/bin/phpunit` before opening a PR.

## Commit & Pull Request Guidelines
Recent commits use short, imperative summaries such as `Added requestId() for request logging` and `Upgrade new Logger system`. Keep commit messages concise, focused on one change, and easy to scan from `git log`. PRs should include a clear description, note any API or behavior changes, link related issues when applicable, and include sample log output or usage snippets for changes that affect developer-facing behavior.

## Security & Configuration Tips
Do not commit generated log files or secrets. When adding context fields, make sure `SensitiveDataFilter` still scrubs credentials, tokens, and personal data before anything reaches a sink.
