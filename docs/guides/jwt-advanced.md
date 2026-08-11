---
sidebar_position: 200
title: JWT Advanced
---

# JWT Authentication - Advanced Guide

Complete guide to JWT (JSON Web Token) authentication including token generation, validation, refresh tokens, and custom claims.

## Table of Contents

- [Overview](#overview)
- [JwtContext Utility](#jwtcontext-utility)
- [Login Flow](#login-flow)
- [Token Structure](#token-structure)
- [Custom JWT Claims](#custom-jwt-claims)
- [Token Refresh](#token-refresh)
- [Protecting Endpoints](#protecting-endpoints)
- [Accessing User Information](#accessing-user-information)
- [Token Expiration](#token-expiration)
- [Security Best Practices](#security-best-practices)

## Overview

The reference architecture uses JWT tokens for stateless authentication. Tokens are:

- **Self-contained**: Contain user information and permissions
- **Stateless**: No server-side session storage required
- **Secure**: Cryptographically signed to prevent tampering
- **Expirable**: Time-limited validity

### Key Components

| Component              | Purpose                    | Location                         |
|------------------------|----------------------------|----------------------------------|
| `JwtContext`           | Token creation and parsing | `ByJG\Gluo\Util\JwtContext` (byjg/gluo-core)        |
| `LoginController`      | Login and token endpoints  | `src/Controller/LoginController.php` (contract) extending `ByJG\Gluo\Controller\BaseLoginController` (logic) |
| `RequireAuthenticated` | Endpoint authentication    | `ByJG\Gluo\Attribute\RequireAuthenticated` (byjg/gluo-core) |
| `RequireRole`          | Role-based authorization   | `ByJG\Gluo\Attribute\RequireRole` (byjg/gluo-core) |

## JwtContext Utility

The `JwtContext` class provides methods for creating tokens and extracting user information.

**Location**: `ByJG\Gluo\Util\JwtContext` (byjg/gluo-core)

### Available Methods

```php
// Create a UserToken (token + claims) from a UserModel instance or login string
JwtContext::createUserMetadata(UserModel|string $user, string $password = ""): ?UserToken

// Create JWT token with custom data
JwtContext::createToken(array $properties): string

// Store the request (called automatically by RequireAuthenticated/RequireRole)
JwtContext::setRequest(HttpRequest $request): void

// Extract user information from token
JwtContext::getUserId(): ?string
JwtContext::getRole(): ?string
JwtContext::getName(): ?string
JwtContext::getUser(): UserModel

// Clear the stored request/user (long-running workers, test setUp)
JwtContext::reset(): void
```

### Customization Hooks

`JwtContext` is designed to be subclassed. Override these `protected static` methods:

```php
protected static function customTokenFields(): array  // extra claims, default []
protected static function tokenExpiry(): int          // seconds, default 3600
protected static function defaultRole(): string       // role when unset, default 'user'
```

## Login Flow

### Login Endpoint

**Location**: `src/Controller/LoginController.php` (contract) + `ByJG\Gluo\Controller\BaseLoginController` (logic)

Your controller only declares the OpenAPI contract and delegates to the framework:

```php
#[OA\Post(path: "/login", tags: ["Login"])]
#[ValidateRequest]
#[Override]
public function post(HttpResponse $response, HttpRequest $request): void
{
    parent::post($response, $request);
}
```

The parent (`BaseLoginController::post`) validates the credentials through
AuthUser via `JwtContext::createUserMetadata()` and writes `token` + `data`
to the response. Improvements to this logic arrive with `composer update`.

### Client Login Request

```bash
POST /login
Content-Type: application/json

{
    "username": "john@example.com",
    "password": "secret123"
}
```

### Login Response

```json
{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "data": {
        "userid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "John Doe",
        "role": "admin"
    }
}
```

### Using the Token

```bash
GET /products
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

## Token Structure

### Default JWT Payload

**Location**: `ByJG\Gluo\Util\JwtContext` (byjg/gluo-core)

The default token contains the fields below. `UserToken::$data` is what ends up
inside the JWT:

```php
$tokenFields = array_merge(
    [
        UserField::Userid,
        UserField::Name,
        UserField::Role->value => static::defaultRole(),  // 'user' when the role is empty
    ],
    static::customTokenFields()   // [] by default — your subclass adds claims here
);
```

You never edit this code — it lives in `vendor/`. To change the payload,
subclass `JwtContext` and override `customTokenFields()` (see
[Custom JWT Claims](#custom-jwt-claims) below).

### Decoded Token Example

```json
{
    "userid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "role": "admin",
    "iat": 1704067200,
    "exp": 1704672000
}
```

### Token Components

- **`userid`**: Unique user identifier (UUID)
- **`name`**: User's display name
- **`role`**: User's role (admin/user)
- **`iat`**: Issued At timestamp
- **`exp`**: Expiration timestamp

## Custom JWT Claims

### Adding Custom Claims

Subclass `JwtContext` and override the hooks — no need to copy the token
creation logic:

```php
<?php

namespace RestReferenceArchitecture\Util;

use ByJG\Authenticate\Enum\UserField;
use ByJG\Gluo\Util\JwtContext;

class CustomJwtContext extends JwtContext
{
    protected static function customTokenFields(): array
    {
        return [
            UserField::Email,      // built-in extra claim
            'department',          // custom property (must exist in your model/properties)
        ];
    }

    protected static function tokenExpiry(): int
    {
        return 3600; // 1 hour (this is already the default)
    }

    // Add getter methods for your claims
    public static function getEmail(): ?string
    {
        return self::getRequestParam("email");
    }

    public static function getDepartment(): ?string
    {
        return self::getRequestParam("department");
    }

    public static function getPermissions(): ?array
    {
        $perms = self::getRequestParam("permissions");
        return $perms ? json_decode($perms, true) : null;
    }

    public static function getTenantId(): ?string
    {
        return self::getRequestParam("tenant_id");
    }
}
```

Use `UserField` enum values for built-in columns (userid, name, email, etc.) and literal strings for custom fields exposed by your `User` model or `users_property` table.

### Wire It into the Login Flow

`BaseLoginController` asks for the JwtContext class through a hook. Override it
in your `src/Controller/LoginController.php`:

```php
use ByJG\Gluo\Util\JwtContext;
use Override;
use RestReferenceArchitecture\Util\CustomJwtContext;

class LoginController extends BaseLoginController
{
    /**
     * @return class-string<JwtContext>
     */
    #[Override]
    protected function getJwtContextClass(): string
    {
        return CustomJwtContext::class;
    }

    // ... OA-annotated endpoint stubs ...
}
```

Both `/login` and `/refreshtoken` now issue tokens with your custom claims.

### Using Custom Claims

```php
#[RequireAuthenticated]
public function getMyData(HttpResponse $response, HttpRequest $request): void
{
    $userId = CustomJwtContext::getUserId();
    $email = CustomJwtContext::getEmail();
    $department = CustomJwtContext::getDepartment();
    $permissions = CustomJwtContext::getPermissions();
    $tenantId = CustomJwtContext::getTenantId();

    $response->write([
        'userId' => $userId,
        'email' => $email,
        'department' => $department,
        'permissions' => $permissions,
        'tenantId' => $tenantId
    ]);
}
```

## Token Refresh

### Refresh Token Endpoint

**Location**: `src/Controller/LoginController.php` (contract) + `ByJG\Gluo\Controller\BaseLoginController` (logic)

Like `/login`, your controller declares the contract and delegates:

```php
#[OA\Post(path: "/refreshtoken", tags: ["Login"])]
#[RequireAuthenticated]
#[Override]
public function refreshToken(HttpResponse $response, HttpRequest $request): void
{
    parent::refreshToken($response, $request);
}
```

`BaseLoginController::refreshToken` only allows the refresh in the final
5 minutes before expiration, reloads the user, and issues a fresh token
with the same claim set (including your custom claims, if you overrode
`getJwtContextClass()`).

### Client Refresh Request

```bash
POST /refreshtoken
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Refresh Response

```json
{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "data": {
        "userid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "John Doe",
        "role": "admin"
    }
}
```

### Automatic Token Refresh

Implement client-side automatic refresh:

```javascript
// JavaScript example
let token = localStorage.getItem('jwt_token');
let refreshTimer;

async function refreshToken() {
    const response = await fetch('/refreshtoken', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });

    const data = await response.json();
    token = data.token;
    localStorage.setItem('jwt_token', token);

    // Schedule next refresh (e.g., 1 hour before expiration)
    scheduleRefresh();
}

function scheduleRefresh() {
    // Refresh shortly before expiration (login tokens last 1 hour by default — see tokenExpiry())
    const refreshIn = (55 * 60 * 1000); // 55 minutes in ms
    refreshTimer = setTimeout(refreshToken, refreshIn);
}

// Start refresh cycle
scheduleRefresh();
```

## Protecting Endpoints

### Require Authentication

```php
use ByJG\Gluo\Attribute\RequireAuthenticated;

#[OA\Get(path: "/profile", tags: ["User"])]
#[RequireAuthenticated]
public function getProfile(HttpResponse $response, HttpRequest $request): void
{
    // Only authenticated users can access
    $userId = JwtContext::getUserId();
    $name = JwtContext::getName();

    $response->write([
        'userId' => $userId,
        'name' => $name
    ]);
}
```

### Require Specific Role

```php
use ByJG\Gluo\Attribute\RequireRole;
use RestReferenceArchitecture\Model\User;

#[OA\Delete(path: "/users/{id}", tags: ["Admin"])]
#[RequireRole(User::ROLE_ADMIN)]
public function deleteUser(HttpResponse $response, HttpRequest $request): void
{
    // Only admins can access
    $id = $request->attribute('id');
    // Delete user logic...
}
```

### Multiple Authorization Levels

```php
// Public endpoint - No authentication
#[OA\Get(path: "/products", tags: ["Products"])]
public function listProducts(...) { }

// Authenticated - Any logged-in user
#[OA\Post(path: "/orders", tags: ["Orders"])]
#[RequireAuthenticated]
public function createOrder(...) { }

// Admin only
#[OA\Delete(path: "/products/{id}", tags: ["Products"])]
#[RequireRole(User::ROLE_ADMIN)]
public function deleteProduct(...) { }
```

## Accessing User Information

### In REST Controllers

```php
#[RequireAuthenticated]
public function getCurrentUser(HttpResponse $response, HttpRequest $request): void
{
    $userId = JwtContext::getUserId();
    $name = JwtContext::getName();
    $role = JwtContext::getRole();

    $response->write([
        'id' => $userId,
        'name' => $name,
        'role' => $role
    ]);
}
```

### In Services

```php
class OrderService extends BaseService
{
    public function createOrder(array $orderData): Order
    {
        // Get current user from JWT
        $userId = JwtContext::getUserId();

        // Add user to order
        $orderData['user_id'] = $userId;
        $orderData['created_by'] = JwtContext::getName();

        return $this->create($orderData);
    }

    public function listMyOrders(): array
    {
        $userId = JwtContext::getUserId();

        $query = $this->repository->listQuery(
            filter: [
                ['user_id = :user_id', ['user_id' => $userId]]
            ]
        );

        return $this->repository->getRepository()->getByQuery($query);
    }
}
```

### Checking Permissions

```php
class ProductService extends BaseService
{
    public function delete(int $productId): void
    {
        $role = JwtContext::getRole();

        // Business rule: Only admins can delete
        if ($role !== User::ROLE_ADMIN) {
            throw new Error403Exception('Only administrators can delete products');
        }

        // Additional check: users can only delete their own products
        $product = $this->getOrFail($productId);
        $userId = JwtContext::getUserId();

        if ($role !== User::ROLE_ADMIN && $product->getUserId() !== $userId) {
            throw new Error403Exception('You can only delete your own products');
        }

        parent::delete($productId);
    }
}
```

## Token Expiration

### Default Expiration

**Location**: `ByJG\Gluo\Util\JwtContext` (byjg/gluo-core)

```php
public static function createToken(array $properties = []): mixed
{
    $jwt = Config::get(JwtWrapper::class);

    // Token valid for 7 days (in seconds)
    $expirationTime = 60 * 60 * 24 * 7;

    $jwtData = $jwt->createJwtData($properties, $expirationTime);
    return $jwt->generateToken($jwtData);
}
```

### Custom Expiration

```php
class CustomJwtContext extends JwtContext
{
    public static function createToken(
        array $properties = [],
        ?int $expirationSeconds = null
    ): mixed {
        $jwt = Config::get(JwtWrapper::class);

        // Default to 24 hours if not specified
        $expirationSeconds = $expirationSeconds ?? (60 * 60 * 24);

        $jwtData = $jwt->createJwtData($properties, $expirationSeconds);
        return $jwt->generateToken($jwtData);
    }

    // Short-lived token for sensitive operations
    public static function createShortLivedToken(array $properties): mixed
    {
        return self::createToken($properties, 60 * 15); // 15 minutes
    }

    // Long-lived token for remember-me
    public static function createLongLivedToken(array $properties): mixed
    {
        return self::createToken($properties, 60 * 60 * 24 * 30); // 30 days
    }
}
```

### Handling Expired Tokens

```php
try {
    // Token validation happens automatically in RequireAuthenticated
    $this->assertRequest($request);
} catch (Error401Exception $e) {
    // Token expired or invalid
    if (strpos($e->getMessage(), 'expired') !== false) {
        // Redirect to refresh token endpoint or login
        return ['error' => 'Token expired', 'action' => 'refresh'];
    }

    return ['error' => 'Unauthorized'];
}
```

## Security Best Practices

### 1. Store JWT Secret Securely

`JWT_SECRET` must be a **base64-encoded** string whose decoded value is at least **64 bytes** (required by HS512).
Generate one with `composer terminal`:

```bash
APP_ENV=dev composer terminal
php> \ByJG\JwtWrapper\JwtWrapper::generateSecret(64)
# => 'OFbOmC2VxlgQHNrBLa/wyj7/fFkgPnLpckbXMVuIU7Sqb3RTztNx3xzEYaoeA31JUpvBjkD7FRKBFGQ0+fnTig=='
```

Copy the output into the appropriate `config/<env>/credentials.env`:

```ini
JWT_SECRET=OFbOmC2VxlgQHNrBLa/wyj7/fFkgPnLpckbXMVuIU7Sqb3RTztNx3xzEYaoeA31JUpvBjkD7FRKBFGQ0+fnTig==
```

Never commit secrets to version control.

### 2. Use HTTPS Only

Always transmit tokens over HTTPS:

```php
// In production environment config
if (Config::get('environment') === 'prod') {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        throw new Error400Exception('HTTPS required');
    }
}
```

### 3. Validate Token on Every Request

The `RequireAuthenticated` attribute handles this automatically:

```php
#[RequireAuthenticated]  // Validates token automatically
public function protectedEndpoint(...) { }
```

### 4. Short Token Expiration

Use shorter expiration times for sensitive operations:

```php
$metadata = [
    'userid' => JwtContext::getUserId(),
    'name' => JwtContext::getName(),
    'role' => JwtContext::getRole(),
];

// Regular operations: 7 days
$regularToken = JwtContext::createToken($metadata);

// Admin operations: 1 hour
$adminToken = CustomJwtContext::createToken($metadata, 60 * 60);

// Financial operations: 15 minutes
$financialToken = CustomJwtContext::createToken($metadata, 60 * 15);
```

### 5. Implement Token Blacklist (Optional)

For logout or compromised tokens:

```php
class TokenBlacklist
{
    protected CacheInterface $cache;

    public function blacklist(string $token, int $expirationTime): void
    {
        // Store token in cache until it would expire anyway
        $this->cache->set("blacklist:{$token}", true, $expirationTime);
    }

    public function isBlacklisted(string $token): bool
    {
        return $this->cache->has("blacklist:{$token}");
    }
}

// Custom authentication attribute
class RequireValidToken extends RequireAuthenticated
{
    public function processBefore(HttpResponse $response, HttpRequest $request): void
    {
        parent::processBefore($response, $request);

        $token = $this->extractToken($request);
        $blacklist = Config::get(TokenBlacklist::class);

        if ($blacklist->isBlacklisted($token)) {
            throw new Error401Exception('Token has been revoked');
        }
    }
}
```

### 6. Rotate Tokens Regularly

Encourage clients to refresh tokens:

Since your `LoginController` owns the endpoint, you can replace the response
shape instead of delegating to the parent:

```php
class LoginController extends BaseLoginController
{
    #[Override]
    public function post(HttpResponse $response, HttpRequest $request): void
    {
        $payload = ValidateRequest::getPayload();
        $userToken = JwtContext::createUserMetadata($payload['username'], $payload['password']);

        $response->write([
            'token' => $userToken->token,
            'expires_in' => 60 * 60, // 1 hour (JwtContext default)
            'refresh_after' => 55 * 60, // Suggest refresh in the last 5 minutes
            'data' => $userToken->data
        ]);
    }
}
```

### 7. Validate User Still Exists

Check user validity on critical operations:

```php
#[RequireAuthenticated]
public function deleteAccount(HttpResponse $response, HttpRequest $request): void
{
    $userId = JwtContext::getUserId();

    // Verify user still exists and is active
    $usersService = Config::get(UsersService::class);
    $user = $usersService->getById($userId);

    if ($user === null || $user->getDeletedAt() !== null) {
        throw new Error401Exception('Account is no longer active');
    }

    // Proceed with deletion...
}
```

### 8. Rate Limit Authentication Endpoints

```php
use RestReferenceArchitecture\Attribute\RateLimit; // your custom attribute (not part of gluo-core)

#[OA\Post(path: "/login", tags: ["Login"])]
#[RateLimit(maxRequests: 5, windowSeconds: 60)]  // 5 attempts per minute
#[ValidateRequest]
public function post(HttpResponse $response, HttpRequest $request)
{
    // Login logic...
}
```

### 9. Log Authentication Events

```php
public function post(HttpResponse $response, HttpRequest $request)
{
    $json = ValidateRequest::getPayload();
    $usersService = Config::get(UsersService::class);

    try {
        $user = $usersService->isValidUser($json["username"], $json["password"]);

        if ($user === null) {
            throw new Error401Exception('Invalid credentials');
        }

        // Log successful login
        $logger->info('User logged in', [
            'user_id' => $user->getUserid(),
            'username' => $json["username"],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);

        // Generate token...

    } catch (Error401Exception $e) {
        // Log failed login attempt
        $logger->warning('Failed login attempt', [
            'username' => $json["username"],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);

        throw $e;
    }
}
```

### 10. Multi-Factor Authentication (Optional)

```php
public function post(HttpResponse $response, HttpRequest $request)
{
    $json = ValidateRequest::getPayload();
    $usersService = Config::get(UsersService::class);
    $user = $usersService->isValidUser($json["username"], $json["password"]);

    if ($user === null) {
        throw new Error401Exception('Invalid credentials');
    }

    // Check if MFA is enabled for user via properties table
    if ($usersService->hasProperty($user->getUserid(), 'mfa_enabled', 'yes')) {
        $tempToken = $this->createTempToken($user);

        $response->write([
            'mfa_required' => true,
            'temp_token' => $tempToken,
            'message' => 'Please provide MFA code'
        ]);
        return;
    }

    $userToken = JwtContext::createUserMetadata($user);
    $response->write(['token' => $userToken->token, 'data' => $userToken->data]);
}

#[OA\Post(path: "/login/verify-mfa", tags: ["Login"])]
public function verifyMfa(HttpResponse $response, HttpRequest $request)
{
    $json = ValidateRequest::getPayload();

    // Verify MFA code
    if ($this->verifyMfaCode($json['temp_token'], $json['mfa_code'])) {
        $user = $this->getUserFromTempToken($json['temp_token']);
        $userToken = JwtContext::createUserMetadata($user);

        $response->write(['token' => $userToken->token, 'data' => $userToken->data]);
    } else {
        throw new Error401Exception('Invalid MFA code');
    }
}
```

## Related Documentation

- [Attribute System](../reference/attributes.md) - RequireAuthenticated and RequireRole
- [REST API Development](rest-controllers.md) - Protecting endpoints
- [Error Handling](error-handling.md) - Authentication errors
- [Testing Guide](testing.md) - Testing authentication
- [Configuration](configuration.md) - JWT configuration
