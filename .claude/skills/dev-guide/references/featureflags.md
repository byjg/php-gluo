# Feature Flags: byjg/featureflag

Lightweight in-memory feature flag system. The preferred approach is PHP attributes —
annotate methods directly and let the dispatcher call only the ones whose flag is active.

Not installed by default: `composer require "byjg/featureflag"`

---

## Core concepts

```
FeatureFlags            — static registry: addFlag(), hasFlag(), getFlag()
#[FeatureFlagAttribute] — annotate a method to run only when a flag matches (preferred)
FeatureFlagDispatcher   — calls matching methods; addClass() scans attributes on a class
FeatureFlagSelector     — explicit single-condition selector (alternative to attributes)
FeatureFlagSelectorSet  — multiple conditions that ALL must match
```

---

## DI registration

### Flags — eager singleton, one `withMethodCall` per flag

```php
// config/dev/06-external.php
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\FeatureFlag\FeatureFlags;

FeatureFlags::class => DI::bind(FeatureFlags::class)
    ->withNoConstructor()
    ->withMethodCall('addFlag', ['payment-gateway',      Param::get('PAYMENT_GATEWAY')])
    ->withMethodCall('addFlag', ['notification-channel', Param::get('NOTIFICATION_CHANNEL')])
    ->withMethodCall('addFlag', ['api-version',          Param::get('API_VERSION')])
    ->toEagerSingleton(),
```

```ini
# config/dev/credentials.env
PAYMENT_GATEWAY=stripe
NOTIFICATION_CHANNEL=email
API_VERSION=v2
```

`toEagerSingleton()` ensures flags are populated at container boot before any request.

### Dispatchers — one per handler class, keyed by the class itself

Each handler class gets its own `FeatureFlagDispatcher` singleton registered under
the handler class name as the DI key:

```php
use ByJG\FeatureFlag\FeatureFlagDispatcher;

PaymentHandlers::class => DI::bind(FeatureFlagDispatcher::class)
    ->withConstructorNoArgs()
    ->withMethodCall('addClass', [PaymentHandlers::class])
    ->toSingleton(),

ShippingHandlers::class => DI::bind(FeatureFlagDispatcher::class)
    ->withConstructorNoArgs()
    ->withMethodCall('addClass', [ShippingHandlers::class])
    ->toSingleton(),

NotificationHandlers::class => DI::bind(FeatureFlagDispatcher::class)
    ->withConstructorNoArgs()
    ->withMethodCall('addClass', [NotificationHandlers::class])
    ->toSingleton(),
```

Usage is then clean and direct:

```php
Config::get(PaymentHandlers::class)->dispatch($orderId, $amount);
Config::get(ShippingHandlers::class)->dispatch($orderId);
Config::get(NotificationHandlers::class)->dispatch($userId, $message);
```

---

## Defining handler classes with attributes

Annotate each method with `#[FeatureFlagAttribute]`. The dispatcher calls every method
whose flag condition is currently active, forwarding all `dispatch()` arguments:

```php
use ByJG\FeatureFlag\Attributes\FeatureFlagAttribute;

class PaymentHandlers
{
    #[FeatureFlagAttribute('payment-gateway', 'stripe')]
    public function stripe(string $orderId, float $amount): void
    {
        // Runs when flag 'payment-gateway' = 'stripe'
    }

    #[FeatureFlagAttribute('payment-gateway', 'paypal')]
    public function paypal(string $orderId, float $amount): void
    {
        // Runs when flag 'payment-gateway' = 'paypal'
    }
}

class NotificationHandlers
{
    #[FeatureFlagAttribute('notification-channel', 'email')]
    public function sendEmail(string $userId, string $message): void { ... }

    #[FeatureFlagAttribute('notification-channel', 'sms')]
    public function sendSms(string $userId, string $message): void { ... }

    // Presence-only flag (no value check)
    #[FeatureFlagAttribute('debug-notifications')]
    public function logDebug(string $userId, string $message): void { ... }
}
```

---

## Using in a controller

```php
use ByJG\Config\Config;
use ByJG\FeatureFlag\FeatureFlags;

class CheckoutRest
{
    #[OA\Post(path: "/checkout", tags: ["checkout"])]
    #[RequireAuthenticated]
    #[ValidateRequest]
    public function postCheckout(HttpResponse $response, HttpRequest $request): void
    {
        // Simple gate — block if flag is absent
        if (!FeatureFlags::hasFlag('checkout-enabled')) {
            throw new Error503Exception('Checkout is currently disabled');
        }

        $payload = ValidateRequest::getPayload();

        // Dispatcher picks the active payment method
        $count = Config::get(PaymentHandlers::class)
            ->dispatch($payload['order_id'], $payload['amount']);

        if ($count === 0) {
            throw new Error422Exception('No payment handler matched');
        }

        $response->write(['status' => 'ok']);
    }
}
```

---

## Checking flags directly

For simple gates without a dispatcher:

```php
use ByJG\FeatureFlag\FeatureFlags;

if (FeatureFlags::hasFlag('beta-access')) { ... }          // presence check
if (FeatureFlags::getFlag('payment-gateway') === 'stripe') { ... }  // value check
```

---

## Explicit selectors and mixing with addClass

`addClass` and `add` both push selectors into the same internal list on the dispatcher.
**All matching selectors execute on `dispatch()`**, regardless of whether they came from
`addClass` or `add`. This means you can freely mix the two on the same dispatcher.

Handlers registered via `add` must implement `FeatureFlagHandlerInterface`:

```php
use ByJG\FeatureFlag\FeatureFlagHandlerInterface;

class StripeGateway implements FeatureFlagHandlerInterface
{
    public function __construct(private readonly StripeClient $client) {}

    public function execute(mixed ...$args): mixed
    {
        [$orderId, $amount] = $args;
        return $this->client->charge($orderId, $amount);
    }
}
```

Register everything in DI — mix `withMethodCall('addClass', [...])` and
`withMethodCall('add', [...])` on the same dispatcher. Use `Param::get()` (not
`Config::get()`) when referencing other DI-registered handlers inside the binding:

```php
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\FeatureFlag\FeatureFlagDispatcher;
use ByJG\FeatureFlag\FeatureFlagSelector;

PaymentHandlers::class => DI::bind(FeatureFlagDispatcher::class)
    ->withConstructorNoArgs()
    ->withMethodCall('addClass', [PaymentAuditHandlers::class])   // attribute-scanned audit
    ->withMethodCall('add', [
        FeatureFlagSelector::whenFlagIs('payment-gateway', 'stripe',
            Param::get(StripeGateway::class))
            ->stopPropagation()
    ])
    ->withMethodCall('add', [
        FeatureFlagSelector::whenFlagIs('payment-gateway', 'paypal',
            Param::get(PayPalGateway::class))
            ->stopPropagation()
    ])
    ->toSingleton(),
```

`stopPropagation()` halts the entire chain — once a matching handler stops propagation
no further selectors run, whether they came from `add()` or `addClass()`.

When all selectors should run (e.g. audit + active handler), omit `stopPropagation()`
on the audit selectors and use it only on the mutually exclusive ones.

---

## Multi-condition selectors

`FeatureFlagSelectorSet` requires **all** conditions to match. Register it via DI using
`Param::get()` for the handler:

```php
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\FeatureFlag\FeatureFlagDispatcher;
use ByJG\FeatureFlag\FeatureFlagSelectorSet;

CheckoutHandlers::class => DI::bind(FeatureFlagDispatcher::class)
    ->withConstructorNoArgs()
    ->withMethodCall('add', [
        FeatureFlagSelectorSet::instance(Param::get(EuCheckoutHandler::class))
            ->whenFlagIs('payment-gateway', 'stripe')
            ->whenFlagIsSet('gdpr-compliant')
            ->whenFlagIs('region', 'EU')
    ])
    ->toSingleton(),
```

---

## Testing

`FeatureFlags` is a static registry — always reset between tests:

```php
use ByJG\FeatureFlag\FeatureFlags;

class CheckoutTest extends BaseApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlags::clearFlags();   // mandatory — static state bleeds between tests
    }

    public function testWithStripe(): void
    {
        FeatureFlags::addFlag('checkout-enabled');
        FeatureFlags::addFlag('payment-gateway', 'stripe');
        // test the endpoint...
    }

    public function testCheckoutDisabled(): void
    {
        // no flags — gate should throw
        $this->expectException(Error503Exception::class);
        // ...
    }
}
```

---

## Quick reference

| Goal | Code |
|---|---|
| Register flags in DI | `DI::bind(FeatureFlags::class)->withNoConstructor()->withMethodCall('addFlag', ['name', Param::get('NAME')])->toEagerSingleton()` |
| Register dispatcher in DI | `DI::bind(FeatureFlagDispatcher::class)->withConstructorNoArgs()->withMethodCall('addClass', [MyHandlers::class])->toSingleton()` |
| Get and dispatch | `Config::get(MyHandlers::class)->dispatch(...$args)` |
| Annotate a method | `#[FeatureFlagAttribute('flag', 'value')]` or `#[FeatureFlagAttribute('flag')]` |
| Check presence | `FeatureFlags::hasFlag('name')` |
| Read value | `FeatureFlags::getFlag('name')` |
| Reset in tests | `FeatureFlags::clearFlags()` |
| Selector: presence | `FeatureFlagSelector::whenFlagIsSet('name', $handler)` |
| Selector: value | `FeatureFlagSelector::whenFlagIs('name', 'value', $handler)` |
| Stop after match | `->stopPropagation()` |
| Multi-condition | `FeatureFlagSelectorSet::instance($h)->whenFlagIs(...)->whenFlagIsSet(...)` |
| Dispatch count | returns int — number of handlers executed |