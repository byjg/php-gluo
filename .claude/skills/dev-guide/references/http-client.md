# HTTP Client: byjg/uri and byjg/webrequest

Use `byjg/uri` and `byjg/webrequest` instead of Guzzle for outbound HTTP requests.
They are lightweight, 100% PSR compliant (PSR-7 messages, PSR-18 client), and have
features Guzzle doesn't implement (e.g. per-key query string manipulation on Uri).

---

## byjg/uri — Uri (PSR-7 UriInterface)

`Uri` is an immutable PSR-7 `UriInterface`. Every `with*` method returns a new clone.

```php
use ByJG\Util\Uri;

// Parse from a string
$uri = new Uri('https://api.example.com/v1/users?page=1');

// Build from scratch
$uri = (new Uri())
    ->withScheme('https')
    ->withHost('api.example.com')
    ->withPath('/v1/users');
```

### Query string helpers (beyond standard PSR-7)

```php
// Add/replace a single query param — no need to rebuild the full string
$uri = $uri->withQueryKeyValue('page', '2')
           ->withQueryKeyValue('size', '20');
// result: ?page=2&size=20

// Read a single param
$page = $uri->getQueryPart('page');        // '2' or null
$has  = $uri->hasQueryKey('page');         // bool

// Full query string (RFC 3986 encoded)
$qs = $uri->getQuery();                    // 'page=2&size=20'
```

### Credentials in URI

```php
$uri = (new Uri('https://api.example.com'))
    ->withUserInfo('myuser', 'mypassword');

$user = $uri->getUsername();   // 'myuser'   (custom — not in PSR-7 UriInterface)
$pass = $uri->getPassword();   // 'mypassword'
// HttpClient reads getUserInfo() automatically and sets CURLOPT_USERPWD
```

---

## byjg/webrequest — HttpClient (PSR-18 ClientInterface)

`HttpClient` is a PSR-18 `ClientInterface` backed by cURL. All configuration is set
on the client; the actual request/response objects are plain PSR-7.

### Simple GET

```php
use ByJG\Util\Uri;
use ByJG\WebRequest\HttpClient;
use ByJG\WebRequest\Psr7\Request;
use ByJG\WebRequest\ParseBody;

$uri     = new Uri('https://api.example.com/v1/products');
$request = Request::getInstance($uri);           // GET by default

$response = HttpClient::getInstance()->sendRequest($request);

// Read response
$status = $response->getStatusCode();            // 200
$body   = ParseBody::parse($response);           // array if JSON, string otherwise
```

### POST with JSON body

```php
use ByJG\WebRequest\Helper\RequestJson;
use ByJG\WebRequest\HttpMethod;

$request = RequestJson::build(
    new Uri('https://api.example.com/v1/products'),
    HttpMethod::Post,
    ['name' => 'Widget', 'price' => 9.99]       // array is json_encoded automatically
);

$response = HttpClient::getInstance()->sendRequest($request);
$data = ParseBody::parse($response);             // decoded array
```

### PUT / PATCH / DELETE

```php
use ByJG\WebRequest\Helper\RequestJson;

// PUT
$request = RequestJson::build($uri, HttpMethod::Put, $payload);

// DELETE (no body)
use ByJG\WebRequest\Psr7\Request;
$request = Request::getInstance($uri)->withMethod(HttpMethod::Delete);
```

### Adding headers (e.g. Authorization)

```php
$request = RequestJson::build($uri, HttpMethod::Post, $payload)
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withHeader('X-Api-Key', $apiKey);
```

### Form-encoded POST (application/x-www-form-urlencoded)

```php
use ByJG\WebRequest\Helper\RequestFormUrlEncoded;

$request = RequestFormUrlEncoded::build(
    new Uri('https://api.example.com/oauth/token'),
    ['grant_type' => 'client_credentials', 'client_id' => 'xxx', 'client_secret' => 'yyy']
);
```

### Multipart upload

```php
use ByJG\WebRequest\Helper\RequestMultiPart;
use ByJG\WebRequest\MultiPartItem;
use ByJG\WebRequest\ContentDisposition;

$items = [
    new MultiPartItem('file', file_get_contents('/tmp/report.pdf'), 'report.pdf', 'application/pdf'),
    new MultiPartItem('description', 'Monthly report'),
];

$request = RequestMultiPart::build(new Uri('https://api.example.com/upload'), HttpMethod::Post, $items);
```

### Client options

```php
$client = HttpClient::getInstance()
    ->withNoFollowRedirect()             // don't follow 3xx redirects
    ->withNoSSLVerification()            // skip SSL cert check (dev only!)
    ->withProxy(new Uri('http://proxy.internal:8080'))
    ->withCurlOption(CURLOPT_TIMEOUT, 60);  // raw CURLOPT override
```

Defaults: 30s connect/read timeout, SSL verification on, follows redirects, sets a
descriptive User-Agent.

### Reading the response

```php
$response->getStatusCode();                    // int, e.g. 200
$response->getHeaderLine('Content-Type');      // 'application/json'
$response->getBody()->getContents();           // raw string body

ParseBody::parse($response);
// → decoded array  if Content-Type contains 'application/json'
// → raw string     otherwise
```

---

## Parallel requests (HttpClientParallel)

Fire multiple requests concurrently using cURL multi. Useful for fan-out calls to
external services.

```php
use ByJG\WebRequest\HttpClientParallel;
use ByJG\WebRequest\HttpClient;
use ByJG\WebRequest\Psr7\Request;
use ByJG\Util\Uri;

$results = [];
$errors  = [];

$parallel = new HttpClientParallel(
    HttpClient::getInstance(),
    defaultOnSuccess: function ($response, $id) use (&$results) {
        $results[$id] = ParseBody::parse($response);
    },
    defaultOnError: function ($error, $id) use (&$errors) {
        $errors[$id] = $error;
    }
);

$parallel
    ->addRequest(Request::getInstance(new Uri('https://api.example.com/v1/a')))
    ->addRequest(Request::getInstance(new Uri('https://api.example.com/v1/b')))
    ->addRequest(Request::getInstance(new Uri('https://api.example.com/v1/c')));

$parallel->execute();   // all three fire simultaneously
// $results[0], $results[1], $results[2] are populated after execute()
```

Per-request callbacks override the defaults:

```php
$parallel->addRequest(
    $request,
    onSuccess: function ($response, $id) { /* specific handling */ }
);
```

---

## MockClient — for unit tests

`MockClient` extends `HttpClient` but never makes real network calls. Use it to test
service classes that depend on an HTTP client without hitting external APIs.

```php
use ByJG\WebRequest\MockClient;
use ByJG\WebRequest\Psr7\Response;
use ByJG\WebRequest\Psr7\MemoryStream;

// Arrange: mock a 200 JSON response
$mockBody = json_encode(['id' => 42, 'name' => 'Widget']);
$mockResponse = (new Response(200))
    ->withBody(new MemoryStream($mockBody))
    ->withHeader('Content-Type', 'application/json');

$client = new MockClient($mockResponse);

// Act: call your service under test using the mock client
$service = new ProductApiService($client);
$result  = $service->fetchProduct(42);

// Assert
$this->assertEquals('Widget', $result['name']);

// Optionally inspect the request that was built:
$sentRequest = $client->getRequestedObject();
$this->assertEquals('GET', $sentRequest->getMethod());
```

---

## DI registration pattern

External service classes live in `config/dev/06-external.php`. Use
`withInjectedConstructorOverrides()` for **partial injection**: the DI container
auto-resolves type-hinted class parameters from the container, while you only need
to provide scalar overrides explicitly. This is the key to environment swapping —
the service code never changes; only the `HttpClient` binding changes per environment.

```php
// src/Service/PaymentGatewayService.php
class PaymentGatewayService
{
    public function __construct(
        private readonly HttpClient $client,   // ← injected automatically from container
        private readonly string $baseUrl,      // ← scalar: must be overridden explicitly
    ) {}

    public function charge(array $payload): array
    {
        $request = RequestJson::build(
            new Uri($this->baseUrl . '/charge'),
            HttpMethod::Post,
            $payload
        )->withHeader('Authorization', 'Bearer ' . getenv('PAYMENT_API_KEY'));

        $response = $this->client->sendRequest($request);
        return ParseBody::parse($response);
    }
}
```

```php
// config/dev/06-external.php  (and config/prod/06-external.php)
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\WebRequest\HttpClient;

// Register the real HttpClient in the container
HttpClient::class => DI::bind(HttpClient::class)
    ->withNoConstructor()
    ->toSingleton(),

// Service: HttpClient is auto-injected; only the scalar baseUrl is overridden
PaymentGatewayService::class => DI::bind(PaymentGatewayService::class)
    ->withInjectedConstructorOverrides([
        'baseUrl' => Param::get('PAYMENT_GATEWAY_URL'),
    ])
    ->toSingleton(),
```

```php
// config/test/06-external.php
use ByJG\WebRequest\MockClient;
use ByJG\WebRequest\Psr7\Response;
use ByJG\WebRequest\Psr7\MemoryStream;

// Swap HttpClient for MockClient — PaymentGatewayService binding is unchanged
HttpClient::class => DI::bind(MockClient::class)
    ->withConstructorArgs([
        (new Response(200))->withBody(new MemoryStream('{"status":"ok"}'))
    ])
    ->toSingleton(),

PaymentGatewayService::class => DI::bind(PaymentGatewayService::class)
    ->withInjectedConstructorOverrides([
        'baseUrl' => Param::get('PAYMENT_GATEWAY_URL'),
    ])
    ->toSingleton(),
```

Because `PaymentGatewayService` only knows `HttpClient` by its class name, and
`MockClient extends HttpClient`, the container resolves the right implementation per
environment without touching the service at all.

### withInjectedConstructorOverrides vs withConstructorArgs

| Method | When to use |
|---|---|
| `withInjectedConstructor()` | All params are class types already in the container |
| `withInjectedConstructorOverrides(['param' => value])` | Mix: auto-inject classes, override scalars |
| `withConstructorArgs([...])` | Provide every argument explicitly (no auto-injection) |

`withInjectedConstructorOverrides` matches overrides by **parameter name**, so order
doesn't matter. Any parameter not listed is resolved from the container by its type hint.