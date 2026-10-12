# CLI Scripts and Services: byjg/scriptify

`byjg/scriptify` turns any `ClassName::methodName` into a runnable command, cron job, or
background service — no CLI framework required in your own classes.

Already a direct dependency of this project (`byjg/scriptify` in composer.json — it powers `./gluo terminal`).

---

## How it works

Scriptify:
1. Loads your project's autoloader (`vendor/autoload.php`)
2. Instantiates the class with `new ClassName()` — **constructor must take no arguments**
3. Calls the method, passing `--arg` values positionally
4. In daemon mode, loops with a 1-second sleep between calls

Your class stays plain PHP. All the service/cron wiring is outside it.

---

## Running a command once

```bash
php vendor/bin/scriptify run "\\App\\Jobs\\SendEmails::process" \
    --arg "user@example.com" \
    --arg "weekly"
```

| Option | Short | Default | Purpose |
|---|---|---|---|
| `--arg VALUE` | `-a` | — | Pass an argument (positional, repeatable) |
| `--bootstrap PATH` | `-b` | `vendor/autoload.php` | Autoloader path, relative to rootdir |
| `--rootdir PATH` | `-r` | CWD | Project root directory |
| `--daemon` | `-d` | false | Loop indefinitely (1 s sleep between calls) |
| `--showdocs` | `-s` | false | Print method docblock and exit |

---

## Your class

No special interface needed. The only constraint is a **no-argument constructor**:

```php
namespace App\Jobs;

use ByJG\Config\Definition as Config;

class SendEmails
{
    // ✓ no-arg constructor — scriptify can instantiate this
    public function process(string $recipient = 'all'): void
    {
        // Use DI container normally inside the method
        $mailer = Config::get(MailWrapperInterface::class);
        // ...
    }
}
```

> The DI container is available inside the method body because scriptify loads your
> bootstrap/autoloader before calling the method. `Config::get()` works as long as
> `ConfigBootstrap::init()` (or equivalent) is called somewhere in the autoload chain.
> If it isn't, call it explicitly at the start of the method.

---

## Setting up the DI container in scriptify context

If the project bootstrap doesn't auto-initialize the DI container, create a thin
entry-point class:

```php
namespace App\Console;

use ByJG\Config\Definition as Config;

class Bootstrap
{
    public function init(): void
    {
        // Only do this once — boot the DI container
        require_once __DIR__ . '/../../config/bootstrap.php';
    }
}
```

Or call `ConfigBootstrap::init()` at the top of every command method:

```php
public function run(): void
{
    \App\Config\AppConfig::boot();   // idempotent — safe to call multiple times

    $service = Config::get(OrderService::class);
    $service->processNext();
}
```

---

## Calling a REST endpoint from CLI (`scriptify call`)

`scriptify call` simulates an HTTP GET request to your REST application without a web
server. It sets up the `$_SERVER` superglobals and requires your entry-point file directly,
so the full routing pipeline runs exactly as if an HTTP request arrived.

```bash
php vendor/bin/scriptify call /product/1 \
    --controller "public/index.php" \
    --rootdir "/var/www/myapp"

# With query string parameters
php vendor/bin/scriptify call /reports/daily \
    --controller "public/index.php" \
    --rootdir "/var/www/myapp" \
    --http-get "format=csv" \
    --http-get "date=2024-01-31"
```

| Option | Short | Purpose |
|---|---|---|
| (first argument) | — | The endpoint path, e.g. `/product/1` |
| `--controller PATH` | `-c` | Entry-point PHP file, relative to rootdir (e.g. `public/index.php`) |
| `--rootdir PATH` | `-r` | Project root directory |
| `--http-get KEY=VALUE` | `-g` | Query string parameter (repeatable) |

**What it does internally:**

```php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/product/1';
$_SERVER['QUERY_STRING']   = 'format=csv&date=2024-01-31';
parse_str($_SERVER['QUERY_STRING'], $_GET);
require_once 'public/index.php';   // triggers your full router
```

Your controller, middleware, and JWT handling all run normally. Only GET requests are
supported — for queue-based triggers or POST-equivalent work use `scriptify run` instead.

**Useful for:** cron-triggered reports, scheduled data exports, or any endpoint that
performs work when hit with GET.

---

## Install as a cron job

Runs the method on a schedule via `/etc/cron.d/`:

```bash
sudo php vendor/bin/scriptify install --template=crond my-job \
    --class "\\App\\Jobs\\SyncInventory::run" \
    --rootdir "/var/www/myapp" \
    --bootstrap "vendor/autoload.php"
```

This writes `/etc/cron.d/my-job` running the command every minute (`* * * * *`).
Edit the file to change the schedule:

```bash
sudo nano /etc/cron.d/my-job
# Change "* * * * *" to "*/5 * * * *" for every 5 minutes
```

---

## Install as a systemd daemon

Runs the method in a loop (`--daemon` flag passed automatically):

```bash
sudo php vendor/bin/scriptify install --template=systemd order-consumer \
    --class "\\App\\Services\\OrderConsumer::consume" \
    --rootdir "/var/www/myapp" \
    --bootstrap "vendor/autoload.php" \
    --description "Order Queue Consumer" \
    --env APP_ENV=production \
    --env QUEUE_CONNECTION="amqp://user:pass@rabbitmq.local:5672/"
```

This creates:
- `/etc/systemd/system/order-consumer.service`
- `/etc/scriptify/order-consumer.env` (environment variables)

```bash
sudo systemctl start   order-consumer
sudo systemctl stop    order-consumer
sudo systemctl restart order-consumer
sudo systemctl status  order-consumer
sudo systemctl enable  order-consumer   # start on boot
sudo journalctl -u order-consumer -f    # follow logs
```

---

## Other service types

```bash
# Init.d (legacy Linux)
sudo php vendor/bin/scriptify install --template=initd myservice \
    --class "\\App\\Service::run" --rootdir "/var/www/myapp"
# → /etc/init.d/myservice

# Upstart (Ubuntu legacy)
sudo php vendor/bin/scriptify install --template=upstart myservice \
    --class "\\App\\Service::run" --rootdir "/var/www/myapp"
# → /etc/init/myservice.conf
```

---

## Passing environment variables to services

```bash
sudo php vendor/bin/scriptify install --template=systemd myservice \
    --class "\\App\\Service::run" \
    --rootdir "/var/www/myapp" \
    --env APP_ENV=production \
    --env DB_URL="mysql://user:pass@localhost/db"
```

Variables are written to `/etc/scriptify/myservice.env` and available as `getenv()` inside
your method.

---

## Managing installed services

```bash
# List all services installed by scriptify
php vendor/bin/scriptify services

# Uninstall (removes service file + env file)
sudo php vendor/bin/scriptify uninstall myservice
```

---

## Combining with message queue consumers

A typical pattern: scriptify starts the consumer, which blocks on the queue:

```php
namespace App\Services;

use ByJG\Config\Definition as Config;
use ByJG\MessageQueueClient\Connector\ConnectorInterface;
use ByJG\MessageQueueClient\Connector\Pipe;
use ByJG\MessageQueueClient\Message;

class OrderConsumer
{
    public function consume(): void
    {
        $connector = Config::get(ConnectorInterface::class);
        $pipe = (new Pipe('orders'))->withDeadLetter(new Pipe('orders-dlq'));

        // This blocks until EXIT is returned
        $connector->consume(
            $pipe,
            function (Envelope $envelope): int {
                // process...
                return Message::ACK;
            },
            fn($env, $ex) => Message::NACK
        );
    }
}
```

Install as a systemd service:

```bash
sudo php vendor/bin/scriptify install --template=systemd order-consumer \
    --class "\\App\\Services\\OrderConsumer::consume" \
    --rootdir "/var/www/myapp" \
    --env APP_ENV=production
```

Systemd restarts the process automatically if it exits unexpectedly.

---

## Interactive terminal (REPL)

```bash
php vendor/bin/scriptify terminal
php vendor/bin/scriptify terminal myservice    # loads /etc/scriptify/myservice.env
```

Optionally pre-load helpers:

```php
// .scriptify-preload.php
use ByJG\Config\Definition as Config;
AppConfig::boot();   // init DI so Config::get() works in the REPL
```

```bash
php vendor/bin/scriptify terminal --preload .scriptify-preload.php
```

---

## Quick reference

| Goal | Command |
|---|---|
| Run once | `php vendor/bin/scriptify run "\\Ns\\Class::method" --arg value` |
| Show method docs | `php vendor/bin/scriptify run "\\Ns\\Class::method" --showdocs` |
| Call REST endpoint | `php vendor/bin/scriptify call /path --controller public/index.php --http-get "k=v"` |
| Install cron | `sudo php vendor/bin/scriptify install --template=crond name --class "..." --rootdir ...` |
| Install systemd | `sudo php vendor/bin/scriptify install --template=systemd name --class "..." --rootdir ...` |
| List services | `php vendor/bin/scriptify services` |
| Remove service | `sudo php vendor/bin/scriptify uninstall name` |
| Start/stop/restart | `sudo systemctl start\|stop\|restart name` |
| View logs | `sudo journalctl -u name -f` |
| REPL | `php vendor/bin/scriptify terminal` |