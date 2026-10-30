# AGENTS.md

Symfony 8.x on PHP 8.4+. Check `composer.json` and `symfony.lock` for what is installed; don't assume Doctrine, Twig, API Platform, Messenger, or Lock.

## Ask first

If the task doesn't specify, ask: persistence (Doctrine ORM, ODM, or none), interface (Twig, API, or both), auth (SecurityBundle and which authenticator). If you can't ask, state the assumption and pick the smallest option.

## Adding features

Use `composer require <package>` and let the Flex recipe wire it. Don't hand-edit `config/bundles.php` or hand-write base bundle config. Don't skip a good-fit component because it isn't installed yet.

## Conventions

- Follow https://symfony.com/doc/current/best_practices.html.
- Use PHP attributes, not YAML/XML: `#[Route]`, `#[MapRequestPayload]`, `#[MapQueryString]`, `#[IsGranted]`, `#[Assert\...]`, `#[AsCommand]`, `#[AsEventListener]`, `#[AsEntityListener]`, `#[AsMessageHandler]`, `#[AsAlias]`, `#[AsTaggedItem]`, `#[Autoconfigure]`.
- Constructor injection and autowiring only. Never call the container inside a service. Use `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` when a type-hint isn't enough. YAML service definitions are a last resort.
- Controllers extend `AbstractController`, stay thin, and delegate to services or Messenger handlers. Use Messenger + a handler for complex or async work.
- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]`, not `json_decode()` or manual parsing. If Serializer or Validator isn't installed, `composer require` it.
- Use the framework first: Form, Validator, Serializer, Security voters, Twig `path()`/`url()`. Check for a Symfony component before hand-writing caches, queues, locks, HTTP clients, mailers, or schedulers.
- For mutual exclusion, use `symfony/lock` (`LockFactory`), never a hand-built flag or lock file.
- Repositories for database access. Domain-Driven Design where it makes sense. DTOs for input/output where appropriate.
- Use constructor property promotion. Use `readonly` for DTOs and value objects.
- Prefer fluent setters for entities and value objects.
- Don't write PHPDoc unless it adds information.

## Code style

Follow the `@Symfony` php-cs-fixer ruleset. If `friendsofphp/php-cs-fixer` is installed, run `vendor/bin/php-cs-fixer fix`. If `phpstan/phpstan` is installed, run `php vendor/bin/phpstan analyse <changed files> --no-progress`.

- `declare(strict_types=1);` in every PHP file. One class per file.
- Use `final` classes unless the class is meant to be extended.
- Keep methods short (ideally under 30–40 lines). Prefer early returns.
- Type all class constants, e.g. `private const string ROUTE_PREFIX = 'app_';`.
- New model and entity classes use `private` constructor-promoted properties (never `public`) and expose them through getters.
- Never leave `dd()`, `dump()`, or `exit()` in committed code.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...` (or `bin/console`).
- Common checks: `php bin/console cache:clear`, `APP_ENV=prod php bin/console lint:container` (after major changes), `php bin/console lint:twig <file>`, `php bin/console lint:yaml config`.
- When something fails, read `var/log/dev.log` and `/_profiler` before changing code.
- If `maker-bundle` is installed, use `bin/console make:*` with all arguments and `--no-interaction`. Makers prompt by default and hang a non-interactive shell. If a maker still needs input, hand-write the code.
- If Doctrine ORM is installed, schema changes go through `make:migration` then `doctrine:migrations:migrate`. Never `doctrine:schema:update` or hand-written SQL.
- `created_at` columns use `DEFAULT CURRENT_TIMESTAMP` in the migration. The entity maps `created_at` read-only, and app code never writes it.
- `.env` is committed and holds defaults only. Real secrets go in `.env.local` (git-ignored) or `bin/console secrets:set`, read via `%env(...)%`.
- Never commit `.env.local` or files containing secrets.
- Validate all user input. Use the Security component properly. Escape output in templates.
- Never run `git add` or `git commit`. Leave staging and committing to the user.

## Testing

Install `symfony/test-pack` if it isn't already. Use `WebTestCase` for HTTP and controller tests, and `KernelTestCase` for service tests. Run `php bin/phpunit` (falls back to `vendor/bin/phpunit`), scoped to the changed test file and piped through `tail -30`.

A feature isn't done until a test exercises it the way a caller would: an HTTP request for a controller, or a service call for a service. Always add or update tests for new or changed features. Mock HTTP clients and external services. Prefer PHPUnit `assert*()` methods.

## Discover, don't guess

Framework APIs change between versions, so check the project instead of relying on memory:

- `bin/console about`, `debug:router`, `debug:container`, `debug:autowiring <name>`, `debug:config <bundle>`, `config:dump-reference <bundle>`.
- `bin/console lint:container`, plus `lint:twig templates/` and `lint:yaml config/` where those packages are installed.
- Read the installed source and docblocks under `vendor/`. Docs: https://symfony.com/doc/current/ (switch to the version in `composer.json`).
