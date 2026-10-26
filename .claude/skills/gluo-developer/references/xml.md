# XML Input and Output

## Overview

XML is supported alongside JSON as both an input (request body) and output (response) format.
The same controller method handles both — content negotiation is driven by the `Content-Type`
and `Accept` headers. The framework selection happens automatically; you only need to document
both formats in your OpenAPI attributes.

**Key class:** `ByJG\XmlUtil\XmlDocument` — received from `ValidateRequest::getPayload()` when
the client sends `Content-Type: application/xml`.

> **Important:** XML request body schema validation is not enforced by `#[ValidateRequest]`
> (it's a documented TODO). The body is parsed and handed to you, but required-field checks
> don't run. Validate manually or accept that XML input is less strictly guarded than JSON.

---

## Receiving XML input

When a client sends `Content-Type: application/xml`, `ValidateRequest::getPayload()` returns
an `XmlDocument`. You cannot pass it to `ObjectCopy` directly — convert to array first.

```php
use ByJG\XmlUtil\XmlDocument;
use ByJG\Serializer\ObjectCopy;

#[OA\Post(path: "/product", tags: ["product"])]
#[OA\RequestBody(
    required: true,
    content: [
        new OA\JsonContent(ref: "#/components/schemas/Product"),
        new OA\XmlContent(
            xml: new OA\Xml(name: "Product"),
            ref: "#/components/schemas/Product"
        ),
    ]
)]
#[OA\Response(response: 200, description: "Created",
    content: new OA\JsonContent(ref: "#/components/schemas/Product"))]
#[RequireAuthenticated]
#[ValidateRequest]
public function postProduct(HttpResponse $response, HttpRequest $request): void
{
    $payload = ValidateRequest::getPayload();

    if ($payload instanceof XmlDocument) {
        // Convert XML to array before passing to service/ObjectCopy
        $data = $payload->toArray();
    } else {
        $data = $payload;   // already an array for JSON
    }

    $model = Config::get(ProductService::class)->create($data);
    $response->write($model);
}
```

---

## XmlDocument API

```php
// From getPayload():
$xml = ValidateRequest::getPayload();   // XmlDocument

// XPath queries
$node  = $xml->selectSingleNode("//name");       // first match → XmlNode|null
$nodes = $xml->selectNodes("//item");            // all matches → DOMNodeList

// Read a node's text content
$value = $node->innerText();                     // 'Widget'

// Convert to associative array (most common when feeding into ObjectCopy/Service)
$array = $xml->toArray();
// → ['name' => 'Widget', 'price' => '9.99']

// Validate against an XSD schema
$errors = $xml->validate('/path/to/schema.xsd'); // null if valid, array of errors if not

// Serialize back to a string
$xmlString = $xml->toString(format: true);       // pretty-printed

// Access underlying DOM
$dom = $xml->DOMDocument();                      // DOMDocument
```

---

## Sending XML output

Add `OA\XmlContent` to `#[OA\Response]`. When a client sends `Accept: application/xml` (or
`text/xml`), the framework automatically picks `XmlOutputProcessor`. No code change in the
controller is needed.

```php
#[OA\Get(path: "/product/{id}", tags: ["product"])]
#[OA\Parameter(name: "id", in: "path", required: true,
    schema: new OA\Schema(type: "integer"))]
#[OA\Response(response: 200, description: "Success",
    content: [
        new OA\JsonContent(ref: "#/components/schemas/Product"),
        new OA\XmlContent(
            xml: new OA\Xml(name: "Product"),
            ref: "#/components/schemas/Product"
        ),
    ])]
#[RequireAuthenticated]
public function getProduct(HttpResponse $response, HttpRequest $request): void
{
    $product = Config::get(ProductService::class)->getOrFail($request->attribute('id'));
    $response->write($product);
    // Accept: application/json → {"id":1,"name":"Widget"}
    // Accept: application/xml  → <root><id>1</id><name>Widget</name></root>
}
```

For a list response:

```php
#[OA\Response(response: 200, description: "List",
    content: [
        new OA\JsonContent(type: "array",
            items: new OA\Items(ref: "#/components/schemas/Product")),
        new OA\XmlContent(
            xml: new OA\Xml(name: "Products"),
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Product")
        ),
    ])]
```

---

## Accept header → output format

| Client `Accept` | Output format |
|---|---|
| `application/json` or `*/*` | JSON (default) |
| `application/xml` | XML |
| `text/xml` | XML |

Only advertise formats you actually support — the framework throws if a client requests a
content type not listed in the route's `responses.200.content`.

---

## XML-only endpoint

If an endpoint exclusively speaks XML (e.g., a SOAP-adjacent integration endpoint):

```php
#[OA\Post(path: "/legacy/import", tags: ["legacy"])]
#[OA\RequestBody(
    required: true,
    content: new OA\XmlContent(xml: new OA\Xml(name: "ImportRequest"))
)]
#[OA\Response(response: 200, description: "Imported",
    content: new OA\XmlContent(xml: new OA\Xml(name: "ImportResponse"),
        properties: [new OA\Property(property: "status", type: "string")]))]
#[ValidateRequest]
public function postImport(HttpResponse $response, HttpRequest $request): void
{
    $xml   = ValidateRequest::getPayload();           // XmlDocument
    $array = $xml->toArray();
    // ... process ...
    $response->write(['status' => 'ok']);
    // Client receives <root><status>ok</status></root>
}
```

---

## Pitfalls

- **`ObjectCopy` does not accept `XmlDocument`** — always call `->toArray()` before passing to
  `ObjectCopy::copy()`, `BaseService::create()`, or `BaseService::update()`.
- **Schema validation is not enforced for XML bodies.** Validate required fields manually if
  strict validation matters.
- **Root element name in output:** `XmlOutputProcessor` wraps the output in `<root>` by default.
  The `OA\Xml(name: "...")` attribute in your OpenAPI spec documents the expected name for clients
  but does not rename the `<root>` tag in the actual response automatically.
- **YAML is not supported** as either input or output format.