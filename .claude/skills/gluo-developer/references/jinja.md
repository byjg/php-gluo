# Jinja Templates: byjg/jinja-php

`byjg/jinja-php` is a PHP implementation of a Jinja2 subset. It is used for **email
templates** (`templates/emails/`) and **codegen templates** (`templates/codegen/`).

It covers the most common Jinja2 syntax but is **not a full Python Jinja2 port** — several
features are absent. Knowing the gaps prevents writing templates that look valid but silently
fail or throw at render time.

---

## Loading and rendering

```php
use ByJG\JinjaPhp\Loader\FileSystemLoader;

// Load from a directory (strips the .html extension automatically)
$loader   = new FileSystemLoader('/path/to/templates', '.html');
$template = $loader->getTemplate('welcome');      // loads welcome.html
$html     = $template->render(['name' => 'Jane', 'code' => 42]);

// Or render an inline string
use ByJG\JinjaPhp\Template;
$result = (new Template('Hello {{ name }}!'))->render(['name' => 'World']);
```

---

## What works

### Variable output

```jinja
{{ name }}
{{ user.email }}
{{ items[0] }}
{{ user['data']['key'] }}
```

### Control structures

```jinja
{% if role == "admin" %}
    Admin panel
{% elif role == "user" %}
    Dashboard
{% else %}
    Guest view
{% endif %}

{% for item in items %}
    {{ loop.index }}. {{ item.name }}
{% else %}
    No items.
{% endfor %}
```

**Loop variables** inside `{% for %}`:

| Variable | Value |
|---|---|
| `loop.index` | 1-based counter |
| `loop.index0` | 0-based counter |
| `loop.first` | `true` on first iteration |
| `loop.last` | `true` on last iteration |
| `loop.length` | total item count |
| `loop.even` / `loop.odd` | parity of current index |

### Whitespace control

```jinja
{%- if condition -%}
    trimmed
{%- endif -%}
```

### Filters

| Filter | Example | Result |
|---|---|---|
| `upper` | `{{ "hello" \| upper }}` | `HELLO` |
| `lower` | `{{ "HELLO" \| lower }}` | `hello` |
| `capitalize` | `{{ "hello world" \| capitalize }}` | `Hello World` |
| `trim` | `{{ " hi " \| trim }}` | `hi` |
| `replace` | `{{ "hi" \| replace("h","j") }}` | `ji` |
| `length` | `{{ items \| length }}` | count |
| `default` | `{{ val \| default("n/a") }}` | fallback if undefined |
| `join` | `{{ list \| join(", ") }}` | `a, b, c` |
| `split` | `{{ "a-b" \| split("-") }}` | array |

Filters can be chained: `{{ name \| trim \| upper }}`.

### Operators

```jinja
{{ 2 + 3 }}         {# 5 #}
{{ "Hi" ~ " there" }}  {# concatenate with ~ #}
{{ a == b }}
{{ a != b }}   {{ a <> b }}   {# both work #}
{{ a > b }}    {{ a <= b }}
{{ true and false }}
{{ true or false }}
{{ !flag }}
{{ "x" in "xyz" }}     {# substring test #}
{{ 1 in [1,2,3] }}     {# array contains #}
```

### Comments

```jinja
{# This is a comment — not rendered #}
```

### Undefined variable handling

Default behaviour throws `TemplateParseException` for missing variables.
Use the `default` filter per variable or configure a global strategy:

```php
use ByJG\JinjaPhp\Undefined\DefaultUndefined;

$template->withUndefined(new DefaultUndefined(''));  // '' for any missing var
```

---

## What is NOT supported

These Python Jinja2 features **do not exist** in byjg/jinja-php. Do not use them —
they will throw or silently produce wrong output:

| Feature | Python Jinja2 | This library |
|---|---|---|
| Template inheritance | `{% extends "base.html" %}` | **Not supported** |
| Block definitions | `{% block content %}` | **Not supported** |
| Include other files | `{% include "header.html" %}` | **Not supported** |
| Macros | `{% macro render_input(name) %}` | **Not supported** |
| Variable assignment | `{% set x = 1 %}` | **Not supported** |
| Loop `break`/`continue` | `{% break %}` | **Not supported** |
| Custom filters | `environment.filters["myfilter"]` | **Not supported** |
| Built-in tests | `{% if x is defined %}` | **Not supported** |
| `{% raw %}` blocks | escape Jinja syntax | **Not supported** |
| Auto-escaping | `autoescape=True` | **Not supported** |
| Template caching | built into Jinja2 | **Not supported** |

**Practical consequences:**

- **No inheritance/include** → each template must be self-contained. Compose by rendering
  multiple templates in PHP and concatenating the strings.
- **No `{% set %}`** → compute everything in PHP before passing to `render()`.
- **No `is defined` test** → use `{{ var \| default('') }}` to guard optional variables.
- **No auto-escaping** → if a variable contains user-provided HTML, escape it in PHP before
  passing: `htmlspecialchars($value)`.

---

## Email template pattern

Templates live in `templates/emails/`. Keep them simple — output + light conditionals only:

```html
<!-- templates/emails/welcome.html -->
<h1>Hi {{ name }},</h1>
<p>
    {% if role == "admin" %}
        Your admin account is ready.
    {% else %}
        Your account is ready.
    {% endif %}
</p>
<p>Activation link: <a href="{{ link }}">{{ link }}</a></p>
```

Render via the DI factory (see `references/email.md`):

```php
$envelope = Config::get('MAIL_ENVELOPE', [
    'user@example.com',
    'Welcome!',
    'welcome',                          // template name without .html
    ['name' => 'Jane', 'role' => 'user', 'link' => $activationUrl],
]);
```

---

## Codegen template pattern

Codegen templates use loops and conditionals heavily to produce PHP class files:

```jinja
{# templates/codegen/model.php.jinja #}
namespace {{ namespace }}\Model;

class {{ className }}
{
    {% for field in fields %}
    {% if field.field != 'deleted_at' %}
    public function get{{ field.property | capitalize }}(): mixed
    {
        return $this->{{ field.property }};
    }
    {% endif %}
    {% endfor %}
}
```

Pass all data your template needs from PHP — compute derived values (class names, namespace
segments) before calling `render()`, since `{% set %}` is unavailable.

---

## Composing templates without `include`

Since `{% include %}` is not supported, render partials in PHP and inject the rendered string:

```php
$header = (new FileSystemLoader('/templates', '.html'))
    ->getTemplate('header')
    ->render(['title' => 'Invoice']);

$body = (new FileSystemLoader('/templates', '.html'))
    ->getTemplate('invoice')
    ->render(['items' => $items, 'header_html' => $header]);
```

```html
<!-- invoice.html -->
{{ header_html }}
{% for item in items %}...{% endfor %}
```

---

## Quick reference

| Goal | Code |
|---|---|
| Load from directory | `new FileSystemLoader($dir, '.html')` |
| Render template | `$loader->getTemplate('name')->render($vars)` |
| Inline template | `(new Template('{{ x }}'))->render(['x' => 1])` |
| Guard optional var | `{{ var \| default('fallback') }}` |
| Silence missing vars | `$template->withUndefined(new DefaultUndefined(''))` |
| Concatenate strings | `{{ "Hello" ~ " " ~ name }}` |
| Loop metadata | `loop.index`, `loop.first`, `loop.last`, `loop.length` |
| Escape HTML | Do it in PHP: `htmlspecialchars($val)` before `render()` |
| Compose templates | Render each part separately, pass HTML as a variable |