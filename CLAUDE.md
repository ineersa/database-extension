# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony AI Mate extension providing MCP (Model Context Protocol) tools and resources for database-related workflows. Package: `matesofmate/database-extension`, namespace `MatesOfMate\DatabaseExtension\`. See [TEMPLATE.md](TEMPLATE.md) for generic extension scaffolding notes.

## Common Commands

### Development Workflow

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run specific test
vendor/bin/phpunit tests/Capability/DatabaseToolTest.php
vendor/bin/phpunit --filter testMethodName

# Check code quality (validates composer.json, runs Rector, PHP CS Fixer, PHPStan)
composer lint

# Auto-fix code style and apply automated refactorings
composer fix
```

### Individual Quality Tools

```bash
# PHP CS Fixer (code style)
vendor/bin/php-cs-fixer fix --dry-run --diff  # Check only
vendor/bin/php-cs-fixer fix                   # Apply fixes

# PHPStan (static analysis at level 8)
vendor/bin/phpstan analyse

# Rector (automated refactoring to PHP 8.2)
vendor/bin/rector process --dry-run           # Preview changes
vendor/bin/rector process                     # Apply changes
```

## Architecture

### Component Structure

**MCP Tools** (`src/Capability/`):
- `DatabaseTool` - Sample tool demonstrating MCP tool pattern with JSON output

**MCP Resources** (`src/Capability/`):
- `DatabaseResource` - Sample resource demonstrating MCP resource pattern with structured data

**Core Services**:
- Extensions will add domain-specific services (runners, parsers, formatters, etc.)

**Key Concepts**:
- **Tools** (`#[McpTool]`): Executable actions invoked by AI (e.g., list entities, analyze code)
- **Resources** (`#[McpResource]`): Static/semi-static data provided to AI (e.g., configuration, routes)

### Service Registration

All services registered in `config/config.php` with:
- Autowiring enabled
- Autoconfiguration enabled (discovers #[McpTool] and #[McpResource] attributes)

Sample registration:
```php
$services = $container->services()
    ->defaults()
    ->autowire()      // Auto-inject dependencies
    ->autoconfigure(); // Auto-register MCP attributes

$services->set(YourTool::class);
```

All classes in `src/Capability/` with `#[McpTool]` or `#[McpResource]` attributes are automatically discovered if registered as services.

## Code Quality Standards

### PHP Requirements
- PHP 8.2+ minimum
- No `declare(strict_types=1)` by convention (template convention - allows user customization)
- No final classes (extensibility)
- JSON encoding: Always use `\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT`

### Quality Tools Configuration
- **PHPStan**: Level 8, includes phpstan-phpunit extension
- **PHP CS Fixer**: `@Symfony` + `@Symfony:risky` rulesets with ordered class elements
- **Rector**: PHP 8.2, code quality, dead code removal, early return, type declarations
- **PHPUnit**: Version 10.0+

### File Header Template

All PHP files must include:
```php
<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
```

### DocBlock Annotations

**@author annotation**: Required on all class-level DocBlocks:
```php
/**
 * Description of the class.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class YourClass
```

**@internal annotation**: Mark implementation details not for external use:
```php
/**
 * Internal helper class for processing output.
 *
 * @internal
 * @author Johannes Wachter <johannes@sulu.io>
 */
class InternalHelper
```

Use @internal for:
- Parser, formatter, runner classes
- Helper traits
- Internal DTOs
- Classes not intended for extension consumers

## Discovery Mechanism

Symfony AI Mate auto-discovers tools and resources via `composer.json`:

```json
{
    "extra": {
        "ai-mate": {
            "scan-dirs": ["src/Capability"],
            "includes": ["config/config.php"]
        }
    }
}
```

## Testing Philosophy

### Test Structure
- Tests mirror `src/` structure in `tests/`
- Extend `PHPUnit\Framework\TestCase`
- Test method names: `testReturnsValidJson`, `testContainsExpectedKeys`, etc.

### Key Testing Areas
- Tool parameter validation and JSON output structure
- Resource return array structure (uri, mimeType, text)
- Service integration and dependency injection
- Error handling and edge cases

### Integration Testing
- Service registration and dependency injection
- Attribute-based discovery (#[McpTool], #[McpResource])
- Framework integration with Symfony AI Mate

## Common Development Patterns

### Adding New Tools

1. Create tool class in `src/Capability/` with `#[McpTool]` attribute
2. Use naming convention: `{framework}-{action}` (lowercase with hyphens)
3. Inject required services via constructor
4. Register service in `config/config.php`
5. Add corresponding test in `tests/Capability/`

**Tool Implementation Pattern**:
```php
use Mcp\Capability\Attribute\McpTool;

class YourTool
{
    public function __construct(
        private readonly SomeService $service,
    ) {
    }

    #[McpTool(
        name: 'framework-action-name',  // Format: {framework}-{action}
        description: 'Precise description of when AI should use this tool'
    )]
    public function execute(string $param): string
    {
        // Return JSON for structured data
        return json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
    }
}
```

**Key points**:
- Tool names use lowercase with hyphens: `database-list-entities`
- Descriptions are critical - AI uses them to decide when to invoke tools
- Return JSON strings for structured data
- Use constructor injection for dependencies

### Adding New Resources

1. Create resource class in `src/Capability/` with `#[McpResource]` attribute
2. Use custom URI scheme (e.g., `database://`, `symfony://`)
3. Return array with `uri`, `mimeType`, and `text` keys
4. Register service in `config/config.php`
5. Add corresponding test in `tests/Capability/`

**Resource Implementation Pattern**:
```php
use Mcp\Capability\Attribute\McpResource;

class YourResource
{
    #[McpResource(
        uri: 'myframework://config',    // Custom URI scheme
        name: 'framework_config',
        mimeType: 'application/json'
    )]
    public function getConfig(): array
    {
        return [
            'uri' => 'myframework://config',
            'mimeType' => 'application/json',
            'text' => json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        ];
    }
}
```

**Key points**:
- Must return array with `uri`, `mimeType`, and `text` keys
- URI uses custom scheme matching your framework
- Typically return `application/json` or `text/plain`

### When Adding Capabilities

1. Add tools and resources in `src/Capability/` with clear names (`database-{action}`, `database://…`)
2. Register all services in `config/config.php`
3. Write tests in `tests/Capability/`
4. Update README.md when behavior or installation changes
5. Ensure all quality checks pass: `composer lint && composer test`

Forking as a new extension type: start from [TEMPLATE.md](TEMPLATE.md), replace `database` naming with your framework, and update `composer.json` / namespaces accordingly.

## Commit Message Convention

Keep commit messages clean without AI attribution.

**Format:**
```
Short summary (50 chars or less)

- Conceptual change description
- Another concept or improvement
```

**Rules:**
- ❌ NO AI attribution (no "Co-Authored-By: Claude", etc.)
- ✅ Short, descriptive summary line
- ✅ Bullet list describing concepts/improvements
- ✅ Focus on the WHY and WHAT
