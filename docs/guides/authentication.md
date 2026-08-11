---
sidebar_position: 130
title: Authentication
---

# Login Integration with JWT

Authentication, password management, and JWT issuance are powered by [`byjg/authuser`](https://github.com/byjg/authuser). This section describes how the default wiring works and what you need to change when you customize users or credentials.

## Database Schema

Two tables ship with the reference architecture:

```sql
CREATE TABLE `users` (
    `userid`     BINARY(16) DEFAULT (uuid_to_bin(uuid())) NOT NULL,
    `name`       VARCHAR(50),
    `email`      VARCHAR(120),
    `username`   VARCHAR(20) NOT NULL,
    `password`   CHAR(40) NOT NULL,
    `role`       VARCHAR(50),
    `created_at` DATETIME DEFAULT (NOW()),
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME,
    PRIMARY KEY (`userid`),
    UNIQUE KEY `ix_username` (`username`),
    UNIQUE KEY `ix_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `users_property` (
    `id`      INT AUTO_INCREMENT PRIMARY KEY,
    `name`    VARCHAR(50),
    `value`   VARCHAR(250),
    `userid`  BINARY(16) NOT NULL,
    CONSTRAINT `fk_user_property` FOREIGN KEY (`userid`) REFERENCES `users` (`userid`)
) ENGINE=InnoDB;
```

- `users.role` drives the built-in RBAC helpers (`User::ROLE_ADMIN` / `User::ROLE_USER`). Add more roles if needed.
- `users_property` stores arbitrary metadata (profile picture, MFA flags, etc.) and is automatically loaded by `UsersService`.

## Model Mapping

Models live in `src/Model` and already extend the AuthUser abstractions:

- `RestReferenceArchitecture\Model\User` extends `ByJG\Authenticate\Model\UserModel`. It uses `FieldUuidAttribute` and OpenAPI attributes to sync the schema.
- `RestReferenceArchitecture\Model\UserProperties` extends `ByJG\Authenticate\Model\UserPropertiesModel`.

To customize fields, either modify these models or create your own classes and update the container bindings described below.

## AuthUser Service Configuration

`config/dev/02-security.php` wires the repositories and service:

```php title="config/dev/02-security.php"
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use RestReferenceArchitecture\Model\User;
use RestReferenceArchitecture\Model\UserProperties;

return [
    UsersRepository::class => DI::bind(UsersRepository::class)
        ->withInjectedConstructorOverrides([
            'usersClass' => User::class,
        ])
        ->toSingleton(),

    UserPropertiesRepository::class => DI::bind(UserPropertiesRepository::class)
        ->withInjectedConstructorOverrides([
            'propertiesClass' => UserProperties::class,
        ])
        ->toSingleton(),

    UsersService::class => DI::bind(UsersService::class)
        ->withInjectedConstructorOverrides([
            'loginField' => LoginField::Email, // or LoginField::Username
        ])
        ->toSingleton(),
];
```

- Swap `LoginField::Email` for `LoginField::Username` if you prefer username-based logins.
- If you add custom user models, update the `usersClass` / `propertiesClass` overrides.

## Password Policy Configuration

The password policy also lives in `config/dev/02-security.php`:

```php title="config/dev/02-security.php"
use ByJG\Authenticate\Definition\PasswordDefinition;

return [
    PasswordDefinition::class => DI::bind(PasswordDefinition::class)
        ->withConstructorArgs([[
            PasswordDefinition::MINIMUM_CHARS    => 12,
            PasswordDefinition::REQUIRE_UPPERCASE => 1,
            PasswordDefinition::REQUIRE_LOWERCASE => 1,
            PasswordDefinition::REQUIRE_SYMBOLS   => 1,
            PasswordDefinition::REQUIRE_NUMBERS   => 1,
            PasswordDefinition::ALLOW_WHITESPACE  => 0,
            PasswordDefinition::ALLOW_SEQUENTIAL  => 0,
            PasswordDefinition::ALLOW_REPEATED    => 0,
        ]])
        ->toSingleton(),
];
```

AuthUser uses the mapper configured on `User::$password` to hash passwords (`PasswordSha1Mapper` by default). Override the mapper if you need a different algorithm.

## JWT Configuration

### Secret & Wrapper

`JwtWrapper` bindings read the secret from each environment’s `credentials.env` file.

```ini title="config/dev/credentials.env"
JWT_SECRET=ZGV2LS1qd3Qtc2VjcmV0LWtleS1mb3ItbG9jYWwtZGV2ZWxvcG1lbnQtb25seS1wYWQtQUJDREVGR0hJSktMTU5P
```

:::info JWT_SECRET format
`JWT_SECRET` must be a **base64-encoded** string whose decoded value is at least **64 bytes** (required by HS512).
`composer create-project` generates a fresh secret for every environment automatically.

To regenerate manually, use `composer terminal`:

```bash
APP_ENV=dev composer terminal
php> \ByJG\JwtWrapper\JwtWrapper::generateSecret(64)
# => 'OFbOmC2VxlgQHNrBLa/wyj7/fFkgPnLpckbXMVuIU7Sqb3RTztNx3xzEYaoeA31JUpvBjkD7FRKBFGQ0+fnTig=='
```

Copy the output and replace `JWT_SECRET` in the appropriate `credentials.env`.
:::

:::caution Never commit secrets
Each environment gets its own `config/<env>/credentials.env` — keep this file out of version control (it is already listed in `.gitignore`).
:::

```php title="config/dev/02-security.php"
use ByJG\Config\Param;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtKeyInterface;
use ByJG\JwtWrapper\JwtWrapper;

return [
    JwtKeyInterface::class => DI::bind(JwtHashHmacSecret::class)
        ->withConstructorArgs([Param::get('JWT_SECRET')])
        ->toSingleton(),

    JwtWrapper::class => DI::bind(JwtWrapper::class)
        ->withConstructorArgs([Param::get('API_SERVER'), Param::get(JwtKeyInterface::class)])
        ->toSingleton(),
];
```

See the caution above — never commit `credentials.env`.

### Available Endpoints

`src/Controller/LoginController.php` and `src/Controller/ProfileController.php` provide:

| Endpoint                      | Description                                 |
|-------------------------------|---------------------------------------------|
| `POST /login`                 | Validate credentials, return JWT            |
| `POST /refreshtoken`          | Refresh an expiring token (last 5 minutes)  |
| `POST /login/resetrequest`    | Send password reset token + email code      |
| `POST /login/confirmcode`     | Confirm the email + code pair               |
| `POST /login/resetpassword`   | Set a new password after confirmation       |
| `GET /profile`                | Profile of the authenticated user (from the JWT) |
| `PUT /profile`                | Update the authenticated user's name, email, and language (en/fr/pt) |
| `GET /sampleprotected/ping`   | Sample endpoint requiring authentication    |

The frontend consumes these directly — its `frontend/src/lib/api.js` stores the JWT and
transparently calls `POST /refreshtoken` when the token is close to expiring. See the
[Frontend guide](frontend.md).

The password reset flow sends emails through `ByJG\Mail\Wrapper\MailWrapperInterface`. Customize the sender/template in `config/dev/06-external.php`, which defines the `MAIL_ENVELOPE` factory.

## Testing Authentication

```bash
# Obtain a token
curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin@example.com","password":"!P4ssw0rdstr!"}'

# Use the token
curl -X GET http://localhost:8080/sampleprotected/ping \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Using JWT in Your Code

### Protect Endpoints

```php
use ByJG\Gluo\Attribute\RequireAuthenticated;
use ByJG\Gluo\Attribute\RequireRole;
use RestReferenceArchitecture\Model\User;

class MyProtectedRest
{
    #[RequireAuthenticated]
    public function getProtectedData(HttpResponse $response): void
    {
        $response->write(['message' => 'You are authenticated!']);
    }

    #[RequireRole(User::ROLE_ADMIN)]
    public function getAdminData(HttpResponse $response): void
    {
        $response->write(['message' => 'You are an admin!']);
    }
}
```

### Read JWT Claims

```php
use ByJG\Gluo\Util\JwtContext;

// JwtContext::setRequest() is called by #[RequireAuthenticated] and #[RequireRole]
$userId   = JwtContext::getUserId();
$userName = JwtContext::getName();
$userRole = JwtContext::getRole(); // e.g., "admin" or "user"
```

Need the full payload? Access `HttpRequest::attribute('jwt.data')` directly, but prefer `JwtContext` helpers to keep controllers focused.

## Additional Resources

- `src/Controller/LoginController.php` – Reference implementation for all endpoints.
- `ByJG\Gluo\Util\JwtContext` (byjg/gluo-core) – Helper used by controllers and tests.
- `config/dev/02-security.php` – Central location for auth wiring.
