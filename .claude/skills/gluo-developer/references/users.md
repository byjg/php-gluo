# User System: Extension, Authentication, and JWT

The user system is built on `byjg/authuser`. The project extends it in
`src/Model/User.php`. Customization happens at three layers: database columns/properties,
password hashing, and JWT payload.

---

## User model — what's built in

The base `UserModel` (from the library) has these fields:

| Field | Column | Notes |
|---|---|---|
| `userid` | `BINARY(16)` | UUID PK — auto-generated |
| `name` | `VARCHAR(50)` | Display name |
| `email` | `VARCHAR(120)` | Unique |
| `username` | `VARCHAR(20)` | Unique login handle |
| `password` | `CHAR(40)` | SHA1 hash |
| `role` | `VARCHAR(50)` | Role string — use `User::ROLE_ADMIN` / `User::ROLE_USER` |
| `created_at` | `DATETIME` | Auto |
| `updated_at` | `DATETIME ON UPDATE` | Auto |
| `deleted_at` | `DATETIME` | Soft-delete |

In addition, each user has a flexible list of **key-value properties** stored in the
`users_property` table and loaded alongside the user object automatically.

---

## Working with a loaded user (the normal case)

`getBy*()` returns a fully populated `User` object — typed columns AND all properties
already loaded. Work directly on the object and call `save()`:

```php
$svc  = Config::get(UsersService::class);
$user = $svc->getById($userId);         // or getByEmail(), getByUsername()

// Read a typed column
$name = $user->getName();
$role = $user->getRole();

// Read a property (key-value, from users_property table)
$dept = $user->get('department');        // string|null

// Write a property
$user->set('department', 'Engineering');
$user->set('phone_number', '555-1234');

// Write a typed column
$user->setName('New Name');

// Persist all changes (columns + properties) in one call
$svc->save($user);
```

`$user->set()` / `$user->get()` are the normal way to handle flexible/extra data.
You only need the lower-level `UsersService::setProperty()` / `getProperty()` for
edge cases where you want to manipulate a property **without loading the full user
object** first (e.g., a background job that only needs to flip one flag).

---

## Option A: Flexible custom data via properties (no migration)

Properties are open-ended string key-value pairs. Use them for anything that doesn't
need to be indexed or have a DB-level type constraint:

```php
// Set when you already have the user object loaded
$user = $svc->getById($userId);
$user->set('last_ip', $request->getServerParam('REMOTE_ADDR'));
$user->set('onboarding_complete', '1');
$svc->save($user);

// Read back
$user = $svc->getById($userId);
$ip    = $user->get('last_ip');
$done  = $user->get('onboarding_complete') === '1';
```

---

## Option B: Custom typed columns on the users table

Use this when you need the data indexed, typed, or enforced at the DB level.

### 1. Migration

```sql
-- db/migrations/up/XXXX.sql
ALTER TABLE `users`
    ADD COLUMN `phone_number` VARCHAR(20) NULL,
    ADD COLUMN `department`   VARCHAR(100) NULL;
```

```sql
-- db/migrations/down/XXXX.sql
ALTER TABLE `users`
    DROP COLUMN `phone_number`,
    DROP COLUMN `department`;
```

Apply: `php gluo --env=dev migrate:update`

### 2. Extend the User model

```php
// src/Model/User.php — add to the existing class
#[OA\Property(type: "string", nullable: true)]
#[FieldAttribute(fieldName: "phone_number")]
protected string|null $phoneNumber = null;

#[OA\Property(type: "string", nullable: true)]
#[FieldAttribute(fieldName: "department")]
protected string|null $department = null;

public function getPhoneNumber(): string|null { return $this->phoneNumber; }
public function setPhoneNumber(string|null $v): static { $this->phoneNumber = $v; return $this; }

public function getDepartment(): string|null { return $this->department; }
public function setDepartment(string|null $v): static { $this->department = $v; return $this; }
```

No DI or repository changes needed — `UsersRepository` is already bound to `User::class`
in `config/dev/02-security.php`. The ORM picks up the new `#[FieldAttribute]` automatically.

---

## Password rules

Configured in `config/dev/02-security.php` (replicate to `config/prod/`):

```php
PasswordDefinition::class => DI::bind(PasswordDefinition::class)
    ->withConstructorArgs([[
        PasswordDefinition::MINIMUM_CHARS      => 12,
        PasswordDefinition::REQUIRE_UPPERCASE  => 1,
        PasswordDefinition::REQUIRE_LOWERCASE  => 1,
        PasswordDefinition::REQUIRE_SYMBOLS    => 1,
        PasswordDefinition::REQUIRE_NUMBERS    => 1,
        PasswordDefinition::ALLOW_WHITESPACE   => 0,
        PasswordDefinition::ALLOW_SEQUENTIAL   => 0,
        PasswordDefinition::ALLOW_REPEATED     => 0,
    ]])
    ->toSingleton(),
```

All rules default to 0 (not required) if omitted. `MINIMUM_CHARS` defaults to 8.

---

## Password hashing

Passwords are stored as **SHA1 hashes** (`CHAR(40)` column) via `PasswordSha1Mapper`.

### Changing password hashing

Changing only the field mapper is insufficient. The installed `UsersService::isValidUser()`
hashes the submitted password using that mapper and compares it with the stored string.
Salted hashes such as bcrypt require verification with `password_verify()` instead.

Before changing the algorithm, inspect the installed `PasswordMapperInterface`,
`UsersService::isValidUser()`, and the password-reset flow. A mapper must implement both
`processedValue()` and `isPasswordEncrypted()`. Adapt authentication to verify the stored
hash, widen the password column if necessary, and define how existing accounts migrate.
Test account creation, valid and invalid login, password changes, reset, and existing
accounts together. There is no drop-in bcrypt mapper recipe for the current login code.

---

## JWT payload

The JWT is built by `ByJG\Gluo\Util\JwtContext` (byjg/gluo-core). Default payload: `userid`, `name`, `role`.

### Adding fields to the token

Subclass `JwtContext` and override its hooks (`customTokenFields()`, `tokenExpiry()`, `defaultRole()`):

```php
namespace App\Util;

use ByJG\Authenticate\Enum\UserField;
use ByJG\Gluo\Util\JwtContext;

class CustomJwtContext extends JwtContext
{
    protected static function customTokenFields(): array
    {
        return [
            UserField::Email,   // built-in extra claim
            'department',       // custom property (must exist in your model/properties)
        ];
    }

    protected static function tokenExpiry(): int
    {
        return 7200; // seconds (default 3600)
    }
}
```

Then point the login controller at it:

```php
// src/Controller/LoginController.php
protected function getJwtContextClass(): string
{
    return CustomJwtContext::class;
}
```

### Reading custom JWT claims in a controller

```php
use ByJG\Gluo\Util\JwtContext;

$userId     = JwtContext::getUserId();   // UserField::Userid
$role       = JwtContext::getRole();     // UserField::Role
$name       = JwtContext::getName();     // UserField::Name

// Custom fields added via customTokenFields():
$jwtData    = $request->attribute('jwt.data');
$department = $jwtData['department'] ?? null;
```

See `docs/guides/jwt-advanced.md` for the full customization guide.

---

## Login configuration

Change whether users log in with email or username in `config/dev/02-security.php`:

```php
UsersService::class => DI::bind(UsersService::class)
    ->withInjectedConstructorOverrides([
        'loginField' => LoginField::Email,     // or LoginField::Username
    ])
    ->toSingleton(),
```

---

## UsersService quick reference

```php
$svc = Config::get(UsersService::class);

// Create
$svc->addUser($name, $username, $email, $password, $role);

// Fetch (returns fully populated User including properties)
$svc->getById($userid);
$svc->getByEmail($email);
$svc->getByUsername($username);

// Update (columns and properties together)
$user = $svc->getById($userid);
$user->setName('New Name');
$user->set('department', 'Engineering');
$svc->save($user);

// Validate login
$svc->isValidUser($login, $password);   // User|null

// Delete (soft)
$svc->removeById($userid);

// Property helpers — use only when you don't have the user object loaded
$svc->setProperty($userid, 'key', 'value');
$svc->getProperty($userid, 'key');
$svc->hasProperty($userid, 'key', 'value');
$svc->removeProperty($userid, 'key', null);
```

---

## Login flow (reference)

1. `POST /login` with `{username, password}`
2. `JwtContext::createUserMetadata()` calls `UsersService::createAuthToken()`
3. Credentials validated — password hash compared against stored hash
4. JWT issued with configured fields + optional `updateTokenInfo`
5. Token hash stored as user property `TOKEN_HASH` for invalidation support
6. Returns `{token, data: {userid, name, role, ...}}`
7. Subsequent requests: `Authorization: Bearer <token>` → `JwtMiddleware` validates + stores claims