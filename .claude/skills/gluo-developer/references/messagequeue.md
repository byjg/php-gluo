# Message Queues: byjg/message-queue-client

Provides a single unified interface for publishing and consuming messages across multiple
backends (RabbitMQ, Redis, in-memory mock). Backend is selected by a URI — no code changes
when switching.

## Packages

| Package | URI scheme | Install |
|---|---|---|
| `byjg/message-queue-client` | `mock://` | Core — install always |
| `byjg/rabbitmq-client` | `amqp://`, `amqps://` | `composer require "byjg/rabbitmq-client"` |
| `byjg/redis-queue-client` | `redis://` | `composer require "byjg/redis-queue-client"` |

---

## Core objects

```
Pipe       — the queue/topic destination (name + optional properties + optional DLQ)
Message    — the payload (body string + optional headers/properties)
Envelope   — combines Pipe + Message for publishing
Connector  — the backend driver; publish and consume via the same interface
```

---

## Dead Letter Queue (DLQ) — what it is and why

A DLQ is a second queue that catches messages your consumer fails to process. When a
consumer returns `Message::NACK`, the broker moves the message to the DLQ instead of
discarding it. This lets you:
- Preserve failing messages for inspection and retry
- Keep the main queue unblocked
- Alert on failures by monitoring DLQ depth

A Pipe knows about its DLQ via `withDeadLetter()`. Set this up before first publish —
the broker creates the binding at declaration time.

```
Producer → [email_send] → Consumer (fails) → NACK → [dlq_email_send]
                                                             ↓
                                              Inspector / retry job later
```

---

## DI registration

Register the connector, pipes, and consumers in `config/dev/06-external.php`.

### Connector

```php
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\MessageQueueClient\Connector\ConnectorFactory;
use ByJG\MessageQueueClient\Connector\ConnectorInterface;
use ByJG\MessageQueueClient\RabbitMQ\RabbitMQConnector;
use ByJG\MessageQueueClient\MockConnector;
use ByJG\Util\Uri;

// config/dev/06-external.php
ConnectorInterface::class => DI::bind(ConnectorInterface::class)
    ->withFactoryFunction(function (string $uri) {
        ConnectorFactory::registerConnector(RabbitMQConnector::class);
        return ConnectorFactory::create(new Uri($uri));
    }, [Param::get('QUEUE_CONNECTION')])
    ->toSingleton(),

// config/test/06-external.php  — mock, no broker needed
ConnectorInterface::class => DI::bind(ConnectorInterface::class)
    ->withFactoryFunction(function () {
        ConnectorFactory::registerConnector(MockConnector::class);
        return ConnectorFactory::create(new Uri('mock://local'));
    })
    ->toSingleton(),
```

```ini
# config/dev/credentials.env
QUEUE_CONNECTION=amqp://user:pass@rabbitmq.local:5672/
```

### Pipes (with DLQ)

Register each queue and its DLQ as named instances. The DLQ must be registered first
so it can be referenced via `Param::get()`:

```php
use ByJG\MessageQueueClient\Connector\Pipe;

// DLQ first — plain queue, no dead letter of its own
'DLQ_EMAIL_TRANSACTIONAL_QUEUE' => DI::bind(Pipe::class)
    ->withConstructorArgs(['dlq_email_send'])
    ->toInstance(),

// Main queue — points its dead letter at the DLQ
'EMAIL_TRANSACTIONAL_QUEUE' => DI::bind(Pipe::class)
    ->withConstructorArgs(['email_send'])
    ->withMethodCall('withDeadLetter', [Param::get('DLQ_EMAIL_TRANSACTIONAL_QUEUE')])
    ->toInstance(),
```

Retrieve anywhere:
```php
$pipe    = Config::get('EMAIL_TRANSACTIONAL_QUEUE');
$dlqPipe = Config::get('DLQ_EMAIL_TRANSACTIONAL_QUEUE');
```

---

## Publishing a message

```php
use ByJG\Config\Config;
use ByJG\MessageQueueClient\Connector\ConnectorInterface;
use ByJG\MessageQueueClient\Message;
use ByJG\MessageQueueClient\Envelope;

$connector = Config::get(ConnectorInterface::class);
$pipe      = Config::get('EMAIL_TRANSACTIONAL_QUEUE');

$message = new Message(json_encode(['to' => 'user@example.com', 'subject' => 'Welcome']));
$message->withProperty('content_type', 'application/json')
        ->withProperty('delivery_mode', 2);   // 2 = persistent

$connector->publish(new Envelope($pipe, $message));
```

---

## Building a consumer — the standard pattern

**Preferred:** extend `QueueHandlerBase` (create this abstract class in your project once
and reuse for every queue):

```php
// src/Queue/QueueHandlerBase.php
namespace App\Queue;

use ByJG\MessageQueueClient\ConsumerClientInterface;
use ByJG\MessageQueueClient\ConsumerClientTrait;
use ByJG\MessageQueueClient\Connector\ConnectorInterface;
use ByJG\MessageQueueClient\Connector\Pipe;
use ByJG\MessageQueueClient\Message;
use ByJG\Config\Config;
use Psr\Log\LoggerInterface;

abstract class QueueHandlerBase implements ConsumerClientInterface
{
    use ConsumerClientTrait;

    abstract public function getPipe(): Pipe;

    public function getConnector(): ConnectorInterface
    {
        return Config::get(ConnectorInterface::class);
    }

    public function getLogger(): LoggerInterface
    {
        return Config::get(LoggerInterface::class);
    }

    public function getLogOutputStart(Message $message): string
    {
        $hash = md5($message->getBody());
        return "[$hash] Processing: " . $message->getBody();
    }

    public function getLogOutputException(\Throwable $exception, Message $message): string
    {
        $hash = md5($message->getBody());
        return "[$hash] Error: " . $exception->getMessage();
    }

    public function getLogOutputSuccess(Message $message): string
    {
        $hash = md5($message->getBody());
        return "[$hash] Success";
    }

    abstract public function processMessage(Message $message): void;
}
```

**Each queue handler** only implements `getPipe()` and `processMessage()`:

```php
// src/Queue/EmailTransactionalConsumer.php
namespace App\Queue;

use ByJG\MessageQueueClient\Connector\Pipe;
use ByJG\MessageQueueClient\Message;
use ByJG\Config\Config;

class EmailTransactionalConsumer extends QueueHandlerBase
{
    public function getPipe(): Pipe
    {
        return Config::get('EMAIL_TRANSACTIONAL_QUEUE');
    }

    public function processMessage(Message $message): void
    {
        $data = json_decode($message->getBody(), true);
        // send the email — throw on failure (ConsumerClientTrait catches and NACKs)
        $mailer = Config::get(MailWrapperInterface::class);
        $mailer->send(/* ... */);
    }
}
```

**How `ConsumerClientTrait` works:** it wraps `processMessage()` in a try/catch. On
success it returns `Message::ACK`. On exception it logs via `getLogOutputException()` and
returns `Message::NACK`, which routes the message to the DLQ if one is configured.

**Running the consumer** (called from a scriptify service or cron):

```php
(new EmailTransactionalConsumer())->consume();  // blocks until EXIT or error
```

---

## Return values (for low-level consumers)

When calling `$connector->consume()` directly, your callback must return one of:

| Constant | Effect |
|---|---|
| `Message::ACK` | Remove from queue (success) |
| `Message::NACK` | Remove from queue → route to DLQ if configured |
| `Message::REQUEUE` | Put back at the end of the queue |
| `Message::EXIT` | Stop consuming and return |
| `Message::ACK \| Message::EXIT` | ACK then stop |

---

## RabbitMQ connector

Install: `composer require "byjg/rabbitmq-client"`

### Connection URI

```
amqp://[user]:[pass]@[host]:[port]/[vhost][?params]
amqps://[user]:[pass]@[host]:[port]/[vhost]?capath=/etc/ssl/certs
```

| Query param | Default | Purpose |
|---|---|---|
| `heartbeat` | 30 | Keepalive interval (seconds) |
| `connection_timeout` | 40 | Connect timeout (seconds) |
| `max_attempts` | 10 | Reconnect retries |
| `pre_fetch` | 0 | QoS prefetch count (`1` = fair dispatch) |
| `timeout` | 600 | Wait-for-message timeout (seconds) |
| `single_run` | false | Exit after one batch (useful for cron) |

### RabbitMQ-specific Pipe properties

```php
use ByJG\MessageQueueClient\RabbitMQ\RabbitMQConnector;

$pipe = new Pipe('orders');
$pipe->withProperty('exchange_type', 'fanout');                       // default: direct
$pipe->withProperty(RabbitMQConnector::EXCHANGE,    'my-exchange');   // default: queue name
$pipe->withProperty(RabbitMQConnector::ROUTING_KEY, 'my-key');        // default: queue name
$pipe->withProperty('x-max-priority', 10);                            // enable priority queue
```

### RabbitMQ-specific Message properties

```php
$message->withProperty('content_type',  'application/json');
$message->withProperty('delivery_mode', 2);       // persistent (survives restart)
$message->withProperty('priority',      8);        // requires x-max-priority on pipe
$message->withProperty('expiration',    60000);    // TTL in ms
```

### Delayed message pattern (DLQ as delay queue)

```php
// Message expires in the delay queue → automatically moved to the work queue (its DLQ)
$workQueue  = new Pipe('process');
$delayQueue = new Pipe('process-delay');
$delayQueue->withDeadLetter($workQueue);

$message = new Message($payload);
$message->withProperty('expiration', 5000);   // 5 s delay

$connector->publish(new Envelope($delayQueue, $message));
```

---

## Testing with MockConnector

`MockConnector` stores messages in memory — no broker needed in the test environment:

```php
// config/test/06-external.php already wires mock://local
// In a test:
$connector = Config::get(ConnectorInterface::class);   // MockConnector
$pipe      = Config::get('EMAIL_TRANSACTIONAL_QUEUE');

// Publish
$connector->publish(new Envelope($pipe, new Message(json_encode(['to' => 'a@b.com']))));

// Verify consumer processes it
(new EmailTransactionalConsumer())->consume();
// Assert your side-effects (DB record created, etc.)
```

---

## Quick reference

| Goal | Code |
|---|---|
| Register connector | `ConnectorFactory::registerConnector(RabbitMQConnector::class)` |
| Create from URI | `ConnectorFactory::create(new Uri($connectionString))` |
| Register pipe in DI | `DI::bind(Pipe::class)->withConstructorArgs(['queue_name'])->toInstance()` |
| Register DLQ binding | `->withMethodCall('withDeadLetter', [Param::get('DLQ_...')])` |
| Get pipe from DI | `Config::get('MY_QUEUE')` |
| Create message | `new Message($body)` |
| Set message header | `$message->withProperty('content_type', 'application/json')` |
| Publish | `$connector->publish(new Envelope($pipe, $message))` |
| Consumer base class | Extend `QueueHandlerBase`, implement `getPipe()` + `processMessage()` |
| Run consumer | `(new MyConsumer())->consume()` |
| ACK / NACK / stop | `return Message::ACK` / `Message::NACK` / `Message::EXIT` |
| Requeue | `return Message::REQUEUE` |
| Mock in tests | `config/test/06-external.php` → `mock://local` URI |