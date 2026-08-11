---
sidebar_position: 180
title: Template Customization
---

# Code Generator Template Customization

Guide to customizing the code generator templates to match your project's specific needs.

## Table of Contents

- [Overview](#overview)
- [Template Structure](#template-structure)
- [Available Variables](#available-variables)
- [Customizing Templates](#customizing-templates)
- [Creating New Templates](#creating-new-templates)
- [Template Examples](#template-examples)

## Overview

The code generator uses [JinjaPhp](https://github.com/byjg/jinja_php) templates shipped inside **byjg/gluo-core** (`vendor/byjg/gluo-core/templates/codegen/`), so template improvements arrive with `composer update`.

**Template Engine**: JinjaPhp (Python Jinja2 syntax for PHP)
**Default location**: `vendor/byjg/gluo-core/templates/codegen/`

**Overriding**: create a `templates/codegen/` directory in your project and the
generator uses it instead of the package templates. To start customizing, copy
the package templates:

```bash
mkdir -p templates
cp -r vendor/byjg/gluo-core/templates/codegen templates/codegen
```

Delete the directory to go back to the package defaults.

### Template Types

| Template                  | Generates           | Pattern                   |
|---------------------------|---------------------|---------------------------|
| `model.php.jinja`         | Model class         | Repository & ActiveRecord (the `activerecord` variable switches the trait) |
| `repository.php.jinja`    | Repository class    | Repository only           |
| `service.php.jinja`       | Service class       | Repository only           |
| `controller.php.jinja`    | REST controller     | Repository pattern        |
| `controlleractiverecord.php.jinja` | REST controller | ActiveRecord pattern |
| `test.php.jinja`          | Test class          | Both patterns             |

## Template Structure

```
templates/
└── codegen/
    ├── model.php.jinja                  # Model class
    ├── repository.php.jinja             # Repository class
    ├── service.php.jinja                # Service class
    ├── controller.php.jinja             # Repository REST controller
    ├── controlleractiverecord.php.jinja # ActiveRecord REST controller
    └── test.php.jinja                   # Test class
```

## Available Variables

These are the variables produced by the generator (`BaseScripts::buildCodegenData()`)
and available in every template.

### Common Variables

```jinja
{{ namespace }}          # Project namespace (e.g., MyRest)
{{ className }}          # PascalCase class name (e.g., ProductItem)
{{ tableName }}          # Database table name (e.g., product_item)
{{ varTableName }}       # camelCase variable name (e.g., productItem)
{{ restPath }}           # REST route path (e.g., product/item)
{{ restTag }}            # OpenAPI tag (e.g., Product)
{{ fields }}             # Array of field definitions (see below)
{{ primaryKeys }}        # Array of primary key column names
{{ nullableFields }}     # camelCase property names of nullable, non-PK columns
{{ nonNullableFields }}  # camelCase property names of required columns
{{ indexes }}            # Table indexes (each with camelColumnName)
{{ autoIncrement }}      # "yes" when the PK auto-increments, "no" otherwise
{{ activerecord }}       # True in ActiveRecord mode
{{ hasCreatedAt }}       # True when the table has a created_at column
{{ hasUpdatedAt }}       # True when the table has an updated_at column
{{ hasDeletedAt }}       # True when the table has a deleted_at column
```

### Field Variables

Each field in `{{ fields }}` mirrors the MySQL `EXPLAIN` output plus derived keys:

```jinja
{{ field.field }}           # Raw column name (e.g., is_active)
{{ field.property }}        # camelCase property name (e.g., isActive)
{{ field.type }}            # Raw database type (e.g., varchar(120), tinyint(1))
{{ field.php_type }}        # PHP type (string, int, float)
{{ field.openapi_type }}    # OpenAPI type (string, integer, number)
{{ field.openapi_format }}  # OpenAPI format (string, int32, int64, double, date-time)
{{ field.null }}            # "YES" when NULL allowed, "NO" otherwise
{{ field.key }}             # "PRI" for primary key columns
{{ field.default }}         # Default value (or null)
{{ field.extra }}           # e.g., auto_increment
```

Common tests, as used by the package templates themselves:

```jinja
{% if field.null == "YES" %}nullable: true{% endif %}
{% if field.key == "PRI" %}primaryKey: true{% endif %}
{% if 'auto_increment' in field.extra %}...{% endif %}
{% if 'binary' in field.type %}...UUID handling...{% endif %}
```

## Customizing Templates

### Example: Adding Custom Header

Edit `templates/codegen/model.php.jinja`:

```jinja
<?php

/**
 * Auto-generated Model
 * Table: {{ tableName }}
 * Do not edit manually!
 */

namespace {{ namespace }}\Model;

// rest of template...
```

### Example: Adding Custom Methods

Add custom methods to `templates/codegen/model.php.jinja`:

```jinja
// ... existing getters/setters ...

{% if not activerecord %}
    /**
     * Custom validation method
     */
    public function validate(): bool
    {
        // Add validation logic
        return true;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
        {% for field in fields %}
            '{{ field.property }}' => $this->{{ field.property }},
        {% endfor %}
        ];
    }
{% endif %}
```

### Example: Customizing REST Endpoints

Edit `templates/codegen/controller.php.jinja` to add custom endpoints:

```jinja
// ... existing CRUD methods ...

    /**
     * Search {{ className }}
     */
    #[OA\Get(
        path: "/{{ restPath }}/search",
        security: [["jwt-token" => []]],
        tags: ["{{ restTag }}"]
    )]
    #[OA\Parameter(
        name: "q",
        in: "query",
        required: true,
        schema: new OA\Schema(type: "string")
    )]
    #[RequireAuthenticated]
    public function search{{ className }}(HttpResponse $response, HttpRequest $request): void
    {
        $searchTerm = $request->query('q');
        $service = Config::get({{ className }}Service::class);

        // Implement search logic
        $results = $service->search($searchTerm);
        $response->write($results);
    }
```

## Creating New Templates

### Custom Template Example

Create `templates/codegen/dto.php.jinja` for Data Transfer Objects:

```jinja
<?php

namespace {{ namespace }}\DTO;

/**
 * {{ className }} Data Transfer Object
 */
class {{ className }}DTO
{
{% for field in fields %}
    public {{ field.php_type }}|null ${{ field.property }} = null;
{% endfor %}

    public static function fromModel(\{{ namespace }}\Model\{{ className }} $model): self
    {
        $dto = new self();
{% for field in fields %}
        $dto->{{ field.property }} = $model->get{{ field.property | capitalize }}();
{% endfor %}
        return $dto;
    }

    public function toArray(): array
    {
        return [
{% for field in fields %}
            '{{ field.property }}' => $this->{{ field.property }},
{% endfor %}
        ];
    }
}
```

### Render Custom Templates

The rendering seams live in `ByJG\Gluo\Builder\BaseScripts` — your project's
`builder/Scripts.php` extends it. Use `renderCodegenTemplate()` (it already
resolves the local `templates/codegen/` override) from a custom entry point:

```php
// builder/Scripts.php
public function generateDto(string $table, array $data): void
{
    $code = $this->renderCodegenTemplate('dto.php', $data);
    file_put_contents($this->workdir . "/src/DTO/{$data['className']}DTO.php", $code);
}
```

## Template Examples

### Conditional Code Based on Field Type

```jinja
{% for field in fields %}
    {% if field.php_type == 'string' %}
    /**
     * @param {{ field.php_type }}|null ${{ field.property }}
     * @return $this
     */
    public function set{{ field.property | capitalize }}({{ field.php_type }}|null ${{ field.property }}): static
    {
        // Trim strings
        $this->{{ field.property }} = ${{ field.property }} !== null ? trim(${{ field.property }}) : null;
        return $this;
    }
    {% elif field.php_type == 'int' or field.php_type == 'float' %}
    /**
     * @param {{ field.php_type }}|null ${{ field.property }}
     * @return $this
     */
    public function set{{ field.property | capitalize }}({{ field.php_type }}|null ${{ field.property }}): static
    {
        // Validate positive numbers
        if (${{ field.property }} !== null && ${{ field.property }} < 0) {
            throw new \InvalidArgumentException('{{ field.property }} must be positive');
        }
        $this->{{ field.property }} = ${{ field.property }};
        return $this;
    }
    {% else %}
    public function set{{ field.property | capitalize }}({{ field.php_type }}|null ${{ field.property }}): static
    {
        $this->{{ field.property }} = ${{ field.property }};
        return $this;
    }
    {% endif %}
{% endfor %}
```

### Adding Timestamp Traits Based on Fields

The generator already detects the timestamp columns for you — use the switches directly
(this is exactly what the package `model.php.jinja` does):

```jinja
class {{ className }}
{
{% if hasCreatedAt %}
    use OaCreatedAt;
{% endif %}
{% if hasUpdatedAt %}
    use OaUpdatedAt;
{% endif %}
{% if hasDeletedAt %}
    use OaDeletedAt;
{% endif %}

    // ... rest of class
}
```

### Custom OpenAPI Documentation

```jinja
    /**
     * Create a new {{ className }}
     */
    #[OA\Post(
        path: "/{{ restPath }}",
        summary: "Create new {{ className }}",
        description: "Creates a new {{ className }} record with the provided data",
        security: [["jwt-token" => []]],
        tags: ["{{ restTag }}"]
    )]
    #[OA\RequestBody(
        description: "{{ className }} data",
        required: true,
        content: new OA\JsonContent(
            required: [{% for field in fields %}{% if field.null == "NO" and 'auto_increment' not in field.extra %}"{{ field.property }}"{% if not loop.last %}, {% endif %}{% endif %}{% endfor %}],
            properties: [
{% for field in fields %}
    {% if field.key != "PRI" %}
                new OA\Property(
                    property: "{{ field.property }}",
                    type: "{{ field.openapi_type }}",
                    format: "{{ field.openapi_format }}",
                    {% if field.null == "YES" %}nullable: true,{% endif %}
                    description: "The {{ field.property }} field"
                ){% if not loop.last %},{% endif %}
    {% endif %}
{% endfor %}
            ]
        )
    )]
```

## Best Practices

### 1. Keep Templates Maintainable

```jinja
{# Good - Clear and readable #}
{% for field in fields %}
    {% if field.key != "PRI" %}
    protected {{ field.php_type }} ${{ field.property }};
    {% endif %}
{% endfor %}

{# Bad - Complex nested logic #}
{% for field in fields %}{% if field.key != "PRI" %}protected {{ field.php_type }} ${{ field.property }};{% endif %}{% endfor %}
```

### 2. Use Comments in Templates

```jinja
{# Generate getters and setters for all non-primary fields #}
{% for field in fields %}
    {% if field.key != "PRI" %}
        {# Getter #}
        public function get{{ field.property | capitalize }}() { }

        {# Setter #}
        public function set{{ field.property | capitalize }}($value) { }
    {% endif %}
{% endfor %}
```

### 3. Maintain Consistent Formatting

```jinja
{# Always use proper indentation #}
class {{ className }}
{
    {% for field in fields %}
    protected {{ field.php_type }} ${{ field.property }};
    {% endfor %}

    {% for field in fields %}
    public function get{{ field.property | capitalize }}()
    {
        return $this->{{ field.property }};
    }
    {% endfor %}
}
```

### 4. Test Template Changes

After modifying templates, test generation:

```bash
# Generate with modified template
composer codegen -- --env=dev --table=test_table all --save

# Review generated code
cat src/Model/TestTable.php
cat src/Controller/TestTableController.php

# Run tests
composer test
```

## Related Documentation

- [Code Generator Usage](../reference/code-generator.md)
- [ORM Guide](orm.md)
- [REST API Development](rest-controllers.md)
- [Architecture Decisions](../concepts/architecture.md)
