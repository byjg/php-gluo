---
sidebar_position: 430
title: Scriptify
---

# Scriptify - Interactive Terminal and Script Runner

[Scriptify](https://github.com/byjg/php-scriptify) is a powerful tool that transforms any PHP class into an executable script callable from the command line without changes or refactoring.

## Overview

Scriptify allows you to:

- **Interactive Terminal**: Open an interactive PHP REPL with your project's autoloader
- **Call Methods**: Execute any PHP method from the command line
- **Install Services**: Install PHP classes/methods as system services (daemon, cron, etc.)
- **Call REST Endpoints**: Execute REST endpoints from shell scripts
- **Environment Management**: Pass and manage environment variables

## Quick Start

### Interactive Terminal

Start an interactive PHP shell with your project loaded:

```bash
composer terminal
```

This gives you immediate access to test your code with preloaded helpers:

```php title="Interactive Session Example"
# Use preloaded database executor
php> qq("SELECT * FROM sample LIMIT 3")
array(3) { ... }

# Access your models and services
php> use RestReferenceArchitecture\Model\Sample;
php> $sample = new Sample();
php> $sample->setName("Test");
php> $sample->getName()
'Test'

# Use helper functions
php> dump($sample)
```

### Run a PHP Method

Execute any method from the command line:

```bash
scriptify run "\\RestReferenceArchitecture\\Service\\SampleService::someMethod" \
    --arg "value1" \
    --arg "value2"
```

## Interactive PHP Terminal

The interactive terminal provides a REPL (Read-Eval-Print Loop) environment with access to your entire project.

### Starting the Terminal

```bash
# Using composer shortcut (recommended - includes preload and environment)
composer terminal

# Or directly
./vendor/bin/scriptify terminal

# With custom environment
APP_ENV=prod composer terminal
```

The `composer terminal` command automatically:
- Loads your project's autoloader
- Sets up the environment from `APP_ENV` variable
- Preloads helper functions from `templates/scriptify/scriptify.php`
- Initializes a database executor instance (`$executor`)

### Features

- **Interactive Prompt**: Execute PHP code in real-time
- **Full Autoloader**: Access to all your project classes
- **Command History**: Navigate previous commands with arrow keys
- **Multi-line Support**: Automatically detects incomplete statements
- **Error Handling**: Parse errors don't crash the session
- **Auto-return**: Expression results are automatically displayed

### Preloaded Helpers

The terminal comes with helper functions and variables automatically loaded from `templates/scriptify/scriptify.php`:

#### Available Variables

- **`$executor`** - Pre-configured `DatabaseExecutor` instance for running queries

#### Available Functions

- **`dump($var)`** - Pretty-print variables with `var_dump()`
- **`qq(string $sql, array $params = [])`** - Quick query execution and display results
- **`uuid_to_bin($uuid)`** - Convert UUID to binary format

#### Helper Examples

```php title="Using Preloaded Helpers"
# Execute a quick SQL query
php> qq("SELECT * FROM project WHERE id = :id", ["id" => 1])
array(1) {
  [0]=>
  array(2) {
    ["id"]=> string(1) "1"
    ["name"]=> string(4) "Test"
  }
}

# Use the database executor directly
php> $result = $executor->getScalar("SELECT COUNT(*) FROM project")
php> dump($result)
int(10)

# Execute a raw query
php> $iterator = $executor->getIterator("SELECT * FROM project LIMIT 5")
php> foreach ($iterator as $row) { dump($row); }
```

### Common Use Cases

#### Debugging a Service Method

```php title="Testing Services Interactively"
php> use RestReferenceArchitecture\Service\SampleService;
php> use ByJG\Config\Config;
php> $service = Config::get(SampleService::class);
php> $service->someMethod("test");
```

#### Testing Database Queries

```php title="Query Testing"
php> use RestReferenceArchitecture\Repository\SampleRepository;
php> use ByJG\Config\Config;
php> $repo = Config::get(SampleRepository::class);
php> $results = $repo->list();
php> print_r($results);
```

#### Quick Prototyping

```php title="Rapid Experimentation"
php> function calculateDiscount($price, $percent) {
php*   return $price * (1 - $percent / 100);
php* }
php> calculateDiscount(100, 15)
85
```

### Customizing the Preload File

You can customize the preload file at `templates/scriptify/scriptify.php` to add your own helper functions and imports.

```php title="templates/scriptify/scriptify.php"
<?php

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Config\Config;
use RestReferenceArchitecture\Service\YourService;

// Pre-initialize common instances
$executor = Config::get(DatabaseExecutor::class);
$myService = Config::get(YourService::class);

// Add custom helper functions
function dump($var) {
    var_dump($var);
}

function qq(string $sql, array $params = []) {
    var_dump(Config::get(DatabaseExecutor::class)->getIterator($sql, $params)->toArray());
}

function dd($var) {
    dump($var);
    die();
}

// Add project-specific helpers
function getUser(int $id) {
    return Config::get(UserRepository::class)->get($id);
}
```

:::tip Restart Required
After modifying the preload file, restart your terminal session for changes to take effect.
:::

### Environment Variables

Load environment variables from a service:

```bash
scriptify terminal myservice
```

Or pass them directly:

```bash
scriptify terminal --env DEBUG=true --env LOG_LEVEL=verbose
```

## Running PHP Methods from the Command Line

Execute any public method of your classes directly from the command line.

### Basic Usage

```bash
scriptify run "\\RestReferenceArchitecture\\Service\\SampleService::someMethod" \
    --arg "value1" \
    --arg "value2"
```

### With Bootstrap and Root Directory

```bash
scriptify run "\\RestReferenceArchitecture\\Service\\SampleService::someMethod" \
    --bootstrap "vendor/autoload.php" \
    --rootdir "/path/to/project" \
    --arg "value1" \
    --arg "value2"
```

### Practical Examples

#### Send Email

```bash
scriptify run "\\RestReferenceArchitecture\\Service\\MailService::sendWelcomeEmail" \
    --arg "user@example.com" \
    --arg "John Doe"
```

#### Run Maintenance Task

```bash
scriptify run "\\RestReferenceArchitecture\\Service\\MaintenanceService::cleanOldRecords" \
    --arg "30"
```

## Installing Services and Daemons

Convert any PHP class method into a system service that runs as a daemon or cron job.

:::warning Root Access Required
Installing services requires root privileges. Use `sudo` when running installation commands.
:::

### Step 1: Test the Method Call

First, verify your method works from the command line:

```bash
scriptify run "\\RestReferenceArchitecture\\Service\\QueueProcessor::process" \
    --rootdir "/var/www/myapi" \
    --arg "default-queue"
```

### Step 2: Install as a Service

Install as a systemd service:

```bash
sudo scriptify install --template=systemd queue-processor \
    --class "\\RestReferenceArchitecture\\Service\\QueueProcessor::process" \
    --rootdir "/var/www/myapi" \
    --arg "default-queue"
```

### Available Templates

| Template  | Description                    | Use Case                      |
|-----------|--------------------------------|-------------------------------|
| `systemd` | Modern Linux init system       | Ubuntu 16.04+, Debian 8+      |
| `upstart` | Legacy Ubuntu init system      | Older Ubuntu/Debian systems   |
| `initd`   | Traditional SysV init          | Legacy systems                |
| `crond`   | Cron-based scheduled execution | Periodic tasks                |

### Managing Services

List all scriptify services:

```bash
scriptify services --only-names
```

Control the service:

```bash
sudo service queue-processor start
sudo service queue-processor stop
sudo service queue-processor status
sudo service queue-processor restart
```

### Uninstalling Services

```bash
sudo scriptify uninstall queue-processor
```

### Example: Queue Worker Setup

```bash
# Install as a daemon that processes queue continuously
sudo scriptify install --template=systemd queue-worker \
    --class "\\RestReferenceArchitecture\\Service\\QueueWorker::processLoop" \
    --rootdir "/var/www/myapi"

# Start the service
sudo service queue-worker start

# Check status
sudo service queue-worker status
```

## Calling REST Endpoints

Execute REST API endpoints directly from the command line.

### Basic GET Request

```bash
scriptify endpoint GET http://localhost:8080/api/sample/1
```

### POST with Headers and Body

```bash
scriptify endpoint POST http://localhost:8080/api/sample \
    --header "Content-Type: application/json" \
    --header "Authorization: Bearer token123" \
    --body '{"name": "Test", "value": 123}'
```

## Environment Variables

### Loading from Service Configuration

When you install a service, you can configure environment variables:

```bash
scriptify install --template=systemd myservice \
    --class "\\MyClass::myMethod" \
    --env DB_HOST=localhost \
    --env DB_NAME=mydb
```

:::info
Environment variables are stored in `/etc/scriptify/myservice.env` and loaded automatically when the service starts.
:::

### Command Line Environment Variables

Pass environment variables to any scriptify command:

```bash
scriptify run "\\MyClass::myMethod" \
    --env DEBUG=true \
    --env LOG_LEVEL=verbose
```

### Environment Variables in Terminal

Start a terminal with a specific environment:

```bash
scriptify terminal --env APP_ENV=development
```

Or load from a service:

```bash
scriptify terminal myservice
```

## Configuration

### Composer Terminal Command

The project is pre-configured with a `composer terminal` command in `composer.json`, so you can run it from the repository root:

```json title="composer.json"
{
  "scripts": {
    "terminal": "./vendor/bin/scriptify terminal --preload ./templates/scriptify/scriptify.php --env APP_ENV=$APP_ENV"
  }
}
```

This configuration:
- **`--preload`**: Loads helper functions from `templates/scriptify/scriptify.php`
- **`--env APP_ENV=$APP_ENV`**: Passes the current APP_ENV to the terminal

You can customize this command to add additional options:

```json
"terminal": "./vendor/bin/scriptify terminal --preload ./templates/scriptify/scriptify.php --env APP_ENV=$APP_ENV --env DEBUG=true"
```

### Preload File Location

The preload file is located at:
```
templates/scriptify/scriptify.php
```

This file is executed before the terminal starts, allowing you to:
- Import commonly used namespaces
- Initialize frequently used instances (like `$executor`)
- Define helper functions (like `dump()` and `qq()`)
- Set up project-specific shortcuts

## Requirements

- PHP 8.3 or higher
- `readline` extension (for interactive terminal)
- Root access (for installing system services)

:::tip Check readline Extension
To verify if readline is installed:
```bash
php -m | grep readline
```
:::

## Additional Resources

For more detailed information, see the official Scriptify documentation:

- [Scriptify GitHub Repository](https://github.com/byjg/php-scriptify)
- [Interactive Terminal Details](https://github.com/byjg/php-scriptify/blob/master/docs/terminal.md)
- [Script Execution Guide](https://github.com/byjg/php-scriptify/blob/master/docs/script.md)
- [Service Installation](https://github.com/byjg/php-scriptify/blob/master/docs/install.md)
- [REST Endpoints](https://github.com/byjg/php-scriptify/blob/master/docs/endpoint.md)
- [Environment Variables](https://github.com/byjg/php-scriptify/blob/master/docs/environment.md)
