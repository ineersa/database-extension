# Extension Template for Symfony AI Mate

A starter template for creating [MatesOfMate](https://github.com/matesofmate) extensions.

## Quick Start

1. **Use this template**: Click "Use this template" on GitHub
2. **Rename everything**: Replace `example` with your framework name
3. **Add your tools**: Create tools in `src/Capability/`
4. **Test it**: Run `composer test`
5. **Publish**: Submit to Packagist

## Structure

```
extension-template/
├── .github/
│   ├── workflows/
│   │   └── ci.yml             # GitHub Actions workflow
│   ├── ISSUE_TEMPLATE/
│   │   ├── 1-bug_report.md    # Bug report template
│   │   ├── 2-feature_request.md # Feature request template
│   │   └── config.yml         # Issue template configuration
│   ├── CODEOWNERS             # Code ownership configuration
│   └── PULL_REQUEST_TEMPLATE.md # PR template
├── composer.json              # Package configuration
├── README.md                  # This file (replace with your docs)
├── LICENSE                    # MIT license
├── .gitignore                 # Git ignore rules
├── phpunit.xml.dist           # Test configuration
├── phpstan.dist.neon          # PHPStan configuration
├── rector.php                 # Rector configuration
├── .php-cs-fixer.php          # PHP CS Fixer configuration
├── src/
│   └── Capability/
│       ├── ExampleTool.php    # Sample tool implementation
│       └── ExampleResource.php # Sample resource implementation
├── config/
│   └── services.php           # Service registration
└── tests/
    └── Capability/
        ├── ExampleToolTest.php
        └── ExampleResourceTest.php
```

## Installation (for users of your extension)

```bash
composer require --dev matesofmate/your-extension

# Discover the new tools
vendor/bin/mate discover
```

## Creating Tools

Tools are PHP classes with methods marked with the `#[McpTool]` attribute:

```php
<?php

namespace MatesOfMate\ExampleExtension\Capability;

use Mcp\Capability\Attribute\McpTool;

final class ListEntitiesTool
{
    public function __construct(
        private readonly SomeService $service,
    ) {
    }

    #[McpTool(
        name: 'example-list-entities',
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

- **name**: Use `{framework}-{action}` format, lowercase with hyphens
- **description**: Be specific! The AI uses this to decide when to call your tool
- **Return**: JSON strings work well for structured data
- **Dependencies**: Use constructor injection, configure in `services.php`

## Creating Resources

Resources provide static context or configuration data to the AI. They return structured data with a URI, MIME type, and content:

```php
<?php

namespace MatesOfMate\ExampleExtension\Capability;

use Mcp\Capability\Attribute\McpResource;

final class ConfigurationResource
{
    #[McpResource(
        uri: 'example://config',
        name: 'example_config',
        mimeType: 'application/json'
    )]
    public function getConfiguration(): array
    {
        return [
            'uri' => 'example://config',
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

- **uri**: Use custom URI scheme (e.g., `example://config`, `myframework://routes`)
- **name**: Descriptive name for the resource
- **mimeType**: Usually `application/json` or `text/plain`
- **Return structure**: Must include `uri`, `mimeType`, and `text` keys

## Registering Services

In `config/services.php`:

```php
<?php

use MatesOfMate\ExampleExtension\Capability\ListEntitiesTool;
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

The template includes a GitHub Actions workflow that automatically runs on every push and pull request:

- **Lint job**: Validates composer.json, runs Rector, PHP CS Fixer, and PHPStan
- **Test job**: Runs PHPUnit tests on PHP 8.2 and 8.3

The workflow is configured in `.github/workflows/ci.yml`.

### GitHub Templates

The template includes GitHub configuration files to streamline your development workflow:

- **CODEOWNERS**: Define code ownership for automatic review requests (update with your GitHub username)
- **PULL_REQUEST_TEMPLATE.md**: Standardized PR description format
- **Issue Templates**: Bug reports and feature requests with structured formats
- **config.yml**: Links to documentation and community resources

Remember to update CODEOWNERS with your actual GitHub username or team names.

## Checklist Before Publishing

- [ ] Replace all `example`/`Example` references with your framework name
- [ ] Update `composer.json` with correct package name and description
- [ ] Update `.github/CODEOWNERS` with your GitHub username/team
- [ ] Write meaningful tool descriptions
- [ ] Add installation instructions to README
- [ ] Add tests for your tools
- [ ] Update LICENSE with your name/org
- [ ] Tag a release (e.g., `v0.1.0`)
- [ ] Submit to Packagist

## Resources

- [Symfony AI Mate Docs](https://symfony.com/doc/current/ai/components/mate.html)
- [Creating MCP Extensions](https://symfony.com/doc/current/ai/components/mate/extensions.html)
- [MatesOfMate Contributing Guide](https://github.com/matesofmate/.github/blob/main/CONTRIBUTING.md)

---

*"Because every Mate needs Mates"*
