# CLI Commands and Services: ./gluo and byjg/scriptify

A method of a service class becomes a command line, a cron job or a background service
without a CLI framework in your class. In this project that is a **project command** of
`./gluo`, run with the DI container. `byjg/scriptify` does the work underneath (it is a
direct dependency; it also powers `./gluo terminal`).

---

## Project commands (the default)

### 1. Write the method

A plain public method. The class gets its dependencies through the constructor, like any
service:

```php
namespace RestReferenceArchitecture\Command;

use RestReferenceArchitecture\Service\OrderService;

class OrderCommands
{
    public function __construct(private OrderService $orders)
    {
    }

    /**
     * Process the pending orders, at most $limit of them.
     */
    public function process(int $limit = 100): void
    {
        $this->orders->processPending($limit);
    }

    protected function log(string $line): void   // not a command: keep helpers protected
    {
    }
}
```

### 2. Register the class in the container

The class is built by the container, never with `new`:

```php
// config/dev/05-services.php
OrderCommands::class => DI::bind(OrderCommands::class)
    ->withInjectedConstructor()
    ->toSingleton(),
```

### 3. Name it in `src/Command/Commands.php`

The project's `CommandProvider`; the `./gluo` script passes it to the launcher:

```php
public function commands(): array
{
    return [
        'order' => OrderCommands::class,                              // order:process, ... every public method
        'cache:clear' => CacheService::class . '::clear',             // or one method
    ];
}
```

- Names are lowercase `group:action` (`a-z`, `0-9`, `:`, `-`); a class gives
  `<group>:<method-in-kebab-case>`. Built-in names (`migrate:update`, `install`, ...) are
  reserved; a name produced twice is an error.
- Use class references (`::class`): a typo fails in Psalm.

### 4. Run it

```bash
./gluo list                         # the commands, with the first line of each PHPDoc
./gluo --env=dev list --check       # validate every command without running it
./gluo order:process --help         # the PHPDoc and: ./gluo order:process [limit]
./gluo --env=dev order:process 50   # values map, in order, to the method parameters
./gluo --env=dev order:process -v   # stack trace on failure
```

`--env` (or `APP_ENV`) selects the configuration. The container is built only when a
project command runs, so `./gluo list` and the built-ins need none.

`list --check` statuses: `OK`, `INVALID_NAME`, `RESERVED`, `INVALID_TARGET`,
`MISSING_CLASS`, `MISSING_METHOD`, `NOT_PUBLIC`, `NOT_IN_CONTAINER` (step 2 is missing).

---

## Install a command as a service

```bash
sudo ./gluo --env=prod install order:process 50                       # systemd (default): runs it again every second
sudo ./gluo --env=prod install --template=crond order:process         # once a minute
sudo ./gluo --env=prod install --name=orders --description="Order processor" order:process
sudo ./gluo uninstall order:process                                   # (--name=orders if it was given)
```

- Templates: `systemd` (default), `crond`, `upstart`, `initd`.
- The service runs `./gluo` itself, so it builds the container exactly as the command line
  does. The command is written in full, nothing left to a default:
  `ExecStart=/usr/bin/php '/var/www/myapp/gluo' --env='prod' order:process '50' --daemon`,
  with `WorkingDirectory=/var/www/myapp`.
- The service is named after the project directory and the command (`myapp-order-process`);
  `--name` chooses another.
- `--env` is required, and the command must pass `list --check`.

For a cron schedule other than every minute, edit `/etc/cron.d/<name>` after installing.

```bash
sudo systemctl start|stop|restart|status myapp-order-process
sudo systemctl enable myapp-order-process      # start on boot
sudo journalctl -u myapp-order-process -f      # follow logs
php vendor/bin/scriptify services              # list the services installed by Scriptify
```

---

## `scriptify run` directly

For a class without constructor dependencies, outside `./gluo`:

```bash
php vendor/bin/scriptify run "App/Jobs/SendEmails::process" --arg "user@example.com" --arg "weekly"
```

| Option | Short | Default | Purpose |
|---|---|---|---|
| `--arg VALUE` | `-a` | — | Pass an argument (positional, repeatable) |
| `--bootstrap PATH` | `-b` | `vendor/autoload.php` | Relative to rootdir: the autoloader, or a file that returns a PSR-11 container |
| `--rootdir PATH` | `-r` | CWD | Project root directory |
| `--daemon` | `-d` | false | Loop indefinitely (1 s sleep between calls) |
| `--showdocs` | `-s` | false | Print method docblock and exit |

With the default bootstrap the class is built with `new`. A `--bootstrap` file that
returns a container has the class built by it, and the container must then hold the class.
The project ships no such file: a class with dependencies belongs in a project command.

`scriptify install --template=... <name> --class "..." --rootdir ...` installs such a
command as a service, the same way.

---

## Calling a REST endpoint from CLI (`scriptify call`)

`scriptify call` simulates an HTTP GET request to your REST application without a web
server. It sets up the `$_SERVER` superglobals and requires your entry-point file directly,
so the full routing pipeline runs exactly as if an HTTP request arrived.

```bash
php vendor/bin/scriptify call /product/1 \
    --controller "public/app.php"

# With query string parameters
php vendor/bin/scriptify call /reports/daily \
    --controller "public/app.php" \
    --http-get "format=csv" \
    --http-get "date=2024-01-31"
```

| Option | Short | Purpose |
|---|---|---|
| (first argument) | — | The endpoint path, e.g. `/product/1` |
| `--controller PATH` | `-c` | Entry-point PHP file, relative to the current directory (`public/app.php`) |
| `--http-get KEY=VALUE` | `-g` | Query string parameter (repeatable) |

Your controller, middleware, and JWT handling all run normally. Only GET requests are
supported — for work that is not a GET, write a project command.

---

## Message queue consumers

A consumer is a project command whose method blocks on the queue; install it as a systemd
service. Inject the connector:

```php
namespace RestReferenceArchitecture\Command;

use ByJG\MessageQueueClient\Connector\ConnectorInterface;
use ByJG\MessageQueueClient\Connector\Pipe;
use ByJG\MessageQueueClient\Envelope;
use ByJG\MessageQueueClient\Message;

class OrderConsumer
{
    public function __construct(private ConnectorInterface $connector)
    {
    }

    /**
     * Consume the orders queue until it is told to stop.
     */
    public function consume(): void
    {
        $pipe = (new Pipe('orders'))->withDeadLetter(new Pipe('orders-dlq'));

        $this->connector->consume(
            $pipe,
            function (Envelope $envelope): int {
                // process...
                return Message::ACK;
            },
            fn ($env, $ex) => Message::NACK
        );
    }
}
```

```bash
# 'queue' => OrderConsumer::class in Commands, registered in the container
sudo ./gluo --env=prod install queue:consume
```

---

## Interactive terminal (REPL)

```bash
./gluo --env=dev terminal      # preloads templates/scriptify/scriptify.php, with the container
```

---

## Quick reference

| Goal | Command |
|---|---|
| List / validate commands | `./gluo list`, `./gluo --env=dev list --check` |
| Run a project command | `./gluo --env=dev order:process 50` |
| Show its docs | `./gluo order:process --help` |
| Install as a service | `sudo ./gluo --env=prod install [--template=crond] order:process` |
| Remove the service | `sudo ./gluo uninstall order:process` |
| List services | `php vendor/bin/scriptify services` |
| Call a REST endpoint | `php vendor/bin/scriptify call /path --controller public/app.php --http-get "k=v"` |
| Start/stop/restart | `sudo systemctl start\|stop\|restart <name>` |
| View logs | `sudo journalctl -u <name> -f` |
| REPL | `./gluo --env=dev terminal` |
