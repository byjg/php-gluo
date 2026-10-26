# Finite State Machine: byjg/statemachine

`byjg/statemachine` provides a lightweight stateless FSM. The machine itself holds no
mutable state — you store and load the current state name yourself (e.g., a `status` DB column).

Install: `composer require "byjg/statemachine"`

---

## Core concepts

```
State          — a named node; optionally runs an action on entry
Transition     — a directed edge from one State to another; optionally guarded by a condition
FiniteStateMachine — the graph; validates and drives transitions
```

The library is **stateless**. Your code must:
1. Persist the current state name (e.g., a VARCHAR `status` column)
2. Load it, ask the machine to act on it, get back the next state
3. Persist the new state name

---

## Building the machine

### Simple form (string pairs)

Pass `[from, to]` string pairs to `createMachine()` — `State` objects are created internally:

```php
use ByJG\StateMachine\FiniteStateMachine;

$machine = FiniteStateMachine::createMachine([
    ['DRAFT',    'PENDING'],
    ['PENDING',  'APPROVED'],
    ['PENDING',  'REJECTED'],
    ['APPROVED', 'CLOSED'],
    ['REJECTED', 'CLOSED'],
])->throwErrorIfCannotTransition();
```

Add a callable as the third element to guard a transition:

```php
$machine = FiniteStateMachine::createMachine([
    ['PENDING', 'APPROVED', fn(?array $data) => ($data['score'] ?? 0) >= 80],
    ['PENDING', 'REJECTED', fn(?array $data) => ($data['score'] ?? 0) <  80],
    ['APPROVED', 'CLOSED'],
]);
```

### Manual form (with entry actions or class conditions)

Use `StateActionInterface` when a state must trigger side effects (email, audit log):

```php
use ByJG\StateMachine\{FiniteStateMachine, State, Transition};
use ByJG\StateMachine\Interfaces\{StateActionInterface, TransitionConditionInterface};

$pending  = new State('PENDING');
$approved = new State('APPROVED', new class implements StateActionInterface {
    public function execute(?array $data): void
    {
        // Runs when APPROVED is entered — $data comes from transition()
    }
});
$rejected = new State('REJECTED');

$machine = FiniteStateMachine::createMachine()
    ->addTransition(Transition::create($pending, $approved,
        new class implements TransitionConditionInterface {
            public function canTransition(?array $data): bool {
                return ($data['manager_approved'] ?? false) === true;
            }
        }
    ))
    ->addTransition(Transition::create($pending, $rejected))
    ->throwErrorIfCannotTransition();
```

> `Transition::create($from, $to, $condition)` is a static factory — equivalent to `new Transition(...)`.

---

## Performing a transition (workflow case)

```php
// Load state name from DB
$current = $machine->state($order->getStatus());   // e.g. State('PENDING')
$target  = $machine->state('APPROVED');

if ($machine->canTransition($current, $target, $data)) {
    $next = $machine->transition($current, $target, $data);
    $order->setStatus($next->getState());   // persist 'APPROVED'
    $orderRepo->save($order);
}
```

`$state->getState()` returns the state name in uppercase.

---

## autoTransitionFrom — classification / routing

`autoTransitionFrom` is designed for **classification**: given data, determine which state
applies. The pattern uses a virtual **`__VOID__`** origin state. Every domain state is
reachable from it via a condition; the machine picks the first matching one.

```php
use ByJG\StateMachine\{FiniteStateMachine, State, Transition};
use ByJG\StateMachine\Interfaces\TransitionConditionInterface;

$origin      = new State('__VOID__');
$inStock     = new State('IN_STOCK');
$lastUnits   = new State('LAST_UNITS');
$outOfStock  = new State('OUT_OF_STOCK');

$machine = FiniteStateMachine::createMachine()
    ->addTransition(Transition::create($origin, $inStock, new class implements TransitionConditionInterface {
        public function canTransition(?array $data): bool {
            return $data['qty'] >= $data['min_stock'];
        }
    }))
    ->addTransition(Transition::create($origin, $lastUnits, new class implements TransitionConditionInterface {
        public function canTransition(?array $data): bool {
            return $data['qty'] > 0 && $data['qty'] < $data['min_stock'];
        }
    }))
    ->addTransition(Transition::create($origin, $outOfStock, new class implements TransitionConditionInterface {
        public function canTransition(?array $data): bool {
            return $data['qty'] === 0;
        }
    }));

// Classify stock status from current quantities
$origin = $machine->state('__VOID__');

$result = $machine->autoTransitionFrom($origin, ['qty' => 30, 'min_stock' => 20]);
// → State('IN_STOCK')

$result = $machine->autoTransitionFrom($origin, ['qty' => 10, 'min_stock' => 20]);
// → State('LAST_UNITS')

$result = $machine->autoTransitionFrom($origin, ['qty' => 0,  'min_stock' => 20]);
// → State('OUT_OF_STOCK')

// No matching transition → null
$lastUnitsState = $machine->state('LAST_UNITS');
$result = $machine->autoTransitionFrom($lastUnitsState, ['qty' => 0, 'min_stock' => 20]);
// → null  (no outgoing transitions from LAST_UNITS)
```

**When to use each:**

| Approach | Use when |
|---|---|
| `transition($from, $to, $data)` | You know the target state (workflow: approve, reject, close) |
| `autoTransitionFrom($from, $data)` | Data determines the next state (classification, routing, recalculation) |

---

## DI registration

Each machine is a named singleton. Use a descriptive string key — not the class name —
because a project can have multiple machines:

```php
// config/dev/05-services.php
use ByJG\Config\DependencyInjection as DI;
use ByJG\StateMachine\FiniteStateMachine;

'OrderStateMachine' => DI::bind(FiniteStateMachine::class)
    ->withFactoryMethod('createMachine', [
        [
            ['DRAFT',    'PENDING'],
            ['PENDING',  'APPROVED'],
            ['PENDING',  'REJECTED'],
            ['APPROVED', 'CLOSED'],
            ['REJECTED', 'CLOSED'],
        ]
    ])
    ->withMethodCall('throwErrorIfCannotTransition')
    ->toSingleton(),
```

> `withFactoryMethod('createMachine', $args)` passes `$args` as the argument list.
> Since `createMachine` takes one parameter (`$transitionList`), wrap the pairs in an outer array.
> `withMethodCall` calls an instance method on the result — here `throwErrorIfCannotTransition()`.

Retrieve it anywhere:

```php
use ByJG\Config\Config;

$machine = Config::get('OrderStateMachine');
```

---

## Inspecting the machine

```php
$machine->isInitialState($machine->state('DRAFT'));   // true — no incoming transitions
$machine->isFinalState($machine->state('CLOSED'));    // true — no outgoing transitions
$machine->possibleTransitions($machine->state('PENDING'));  // Transition[]
```

---

## Practical tip: use a backed Enum for state names

```php
enum OrderStatus: string
{
    case DRAFT    = 'DRAFT';
    case PENDING  = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CLOSED   = 'CLOSED';
}

// In DI config — use ->value to get the string:
'OrderStateMachine' => DI::bind(FiniteStateMachine::class)
    ->withFactoryMethod('createMachine', [
        [
            [OrderStatus::DRAFT->value,    OrderStatus::PENDING->value],
            [OrderStatus::PENDING->value,  OrderStatus::APPROVED->value],
            [OrderStatus::PENDING->value,  OrderStatus::REJECTED->value],
            [OrderStatus::APPROVED->value, OrderStatus::CLOSED->value],
            [OrderStatus::REJECTED->value, OrderStatus::CLOSED->value],
        ]
    ])
    ->withMethodCall('throwErrorIfCannotTransition')
    ->toSingleton(),
```

---

## Quick reference

| Goal | Code |
|---|---|
| Build from string pairs | `FiniteStateMachine::createMachine([['A','B'],['B','C']])` |
| Conditional pair | `['A', 'B', fn(?array $d) => $d['ok']]` |
| With entry action | `new State('S', new class implements StateActionInterface {...})` |
| Create transition | `Transition::create($from, $to, $condition)` |
| Multiple sources | `Transition::createMultiple([$s1, $s2], $dest, $cond)` |
| Throw on invalid | `->throwErrorIfCannotTransition()` |
| DI registration | `DI::bind(FiniteStateMachine::class)->withFactoryMethod('createMachine',[...])->withMethodCall('throwErrorIfCannotTransition')->toSingleton()` |
| Retrieve from DI | `Config::get('MyStateMachine')` |
| Get State object | `$machine->state('STATE_NAME')` |
| Get state name | `$state->getState()` (returns uppercase) |
| Perform transition | `$machine->transition($from, $to, $data)` → next `State` |
| Classify from data | `$machine->autoTransitionFrom($voidState, $data)` → `State\|null` |
| Check transition | `$machine->canTransition($from, $to, $data)` → `bool` |
| Is initial/final | `$machine->isInitialState($s)` / `$machine->isFinalState($s)` |