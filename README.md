# Database Extension for Symfony AI Mate

[MatesOfMate](https://github.com/matesofmate) extension that adds MCP (Model Context Protocol) tools and resources for database-related workflows in Symfony AI Mate.

Generic fork/customization instructions for new extensions live in [TEMPLATE.md](TEMPLATE.md) (copied from the original template).

## Quick Start

1. **Install**: `composer require --dev matesofmate/database-extension`
2. **Discover tools**: `vendor/bin/mate discover`
3. **Develop**: Add tools in `src/Capability/` and register them in `config/config.php`
4. **Test**: `composer test`
5. **Quality**: `composer lint`

## Structure

```
database-extension/
├── .github/
│   ├── workflows/
│   │   └── ci.yml             # GitHub Actions workflow
│   ├── ISSUE_TEMPLATE/
│   │   ├── 1-bug_report.md
│   │   ├── 2-feature_request.md
│   │   └── config.yml
│   ├── CODEOWNERS
│   └── PULL_REQUEST_TEMPLATE.md
├── composer.json
├── README.md                  # This file
├── TEMPLATE.md                # Generic extension template documentation
├── LICENSE
├── .gitignore
├── phpunit.xml.dist
├── phpstan.dist.neon
├── rector.php
├── .php-cs-fixer.php
├── src/
│   └── Capability/
│       ├── DatabaseTool.php    # Sample tool implementation
│       └── DatabaseResource.php # Sample resource implementation
├── config/
│   └── config.php             # Service registration
└── tests/
    └── Capability/
        ├── DatabaseToolTest.php
        └── DatabaseResourceTest.php
```

## Installation

```bash
composer require --dev matesofmate/database-extension

# Discover the new tools
vendor/bin/mate discover
```

## Creating Tools

Tools are PHP classes with methods marked with the `#[McpTool]` attribute:

```php
<?php

namespace MatesOfMate\DatabaseExtension\Capability;

use Mcp\Capability\Attribute\McpTool;

final class ListEntitiesTool
{
    public function __construct(
        private readonly SomeService $service,
    ) {
    }

    #[McpTool(
        name: 'database-list-entities',
        description: 'Lists all entities in the application. Use when the user asks about available entities, models, or database tables.'
    )]
    public function execute(): string
    {
        $entities = $this->service->getEntities();

        return json_encode([
            'entities' => $entities,
            'count' => count($entities),
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
    }
}
```

### Tool Tips

- **name**: Use `{framework}-{action}` format, lowercase with hyphens (e.g. `database-…`)
- **description**: Be specific so the model knows when to call the tool
- **Return**: JSON strings work well for structured data
- **Dependencies**: Constructor injection; wire in `config/config.php`

## Creating Resources

Resources provide static context or configuration data to the AI. They return structured data with a URI, MIME type, and content:

```php
<?php

namespace MatesOfMate\DatabaseExtension\Capability;

use Mcp\Capability\Attribute\McpResource;

final class ConfigurationResource
{
    #[McpResource(
        uri: 'database://config',
        name: 'database_config',
        mimeType: 'application/json'
    )]
    public function getConfiguration(): array
    {
        return [
            'uri' => 'database://config',
            'mimeType' => 'application/json',
            'text' => json_encode([
                'version' => '1.0.0',
                'features' => ['feature_a' => true],
            ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        ];
    }
}
```

### Resource Tips

- **uri**: Custom scheme (e.g. `database://config`, `database://schema`)
- **name**: Descriptive resource identifier
- **mimeType**: Usually `application/json` or `text/plain`
- **Return structure**: Must include `uri`, `mimeType`, and `text` keys

## Registering Services

In `config/config.php`:

```php
<?php

use MatesOfMate\DatabaseExtension\Capability\ListEntitiesTool;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ListEntitiesTool::class);
};
```

## Testing & Code Quality

```bash
# Run tests
composer test

# With coverage
composer test -- --coverage-html coverage/

# Check code style and static analysis
composer lint

# Auto-fix code style and apply automated refactorings
composer fix
```

### Individual Tools

```bash
# PHP CS Fixer only
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/php-cs-fixer fix

# PHPStan only
vendor/bin/phpstan analyse

# Rector only
vendor/bin/rector process --dry-run
vendor/bin/rector process
```

### Continuous Integration

GitHub Actions runs on push and pull request:

- **Lint job**: Validates `composer.json`, Rector, PHP CS Fixer, PHPStan
- **Test job**: PHPUnit on PHP 8.2 and 8.3

Configured in `.github/workflows/ci.yml`.

### GitHub Templates

- **CODEOWNERS**: Code ownership (update with your GitHub username)
- **PULL_REQUEST_TEMPLATE.md**: PR format
- **Issue templates**: Bug reports and feature requests

## Checklist Before Publishing

- [x] Replace template `example` / `ExampleExtension` naming with `database` / `DatabaseExtension`
- [ ] Keep `composer.json` package name and description accurate as features grow
- [ ] Update `.github/CODEOWNERS` with maintainers
- [ ] Write meaningful tool descriptions
- [ ] Keep installation and usage documented in this README
- [ ] Add tests for new tools and resources
- [ ] Update LICENSE name/org if needed
- [ ] Tag releases (e.g. `v0.1.0`) and publish to Packagist

## Resources

- [Symfony AI Mate Docs](https://symfony.com/doc/current/ai/components/mate.html)
- [Creating MCP Extensions](https://symfony.com/doc/current/ai/components/mate/extensions.html)
- [MatesOfMate Contributing Guide](https://github.com/matesofmate/.github/blob/main/CONTRIBUTING.md)

---

*"Because every Mate needs Mates"*
