<?php

namespace Test\Util;

use ByJG\ApiTools\Base\Schema;
use ByJG\Gluo\Util\OpenApiContext;
use ByJG\RestServer\Exception\Error400Exception;
use ByJG\RestServer\HttpRequest;
use ByJG\XmlUtil\XmlDocument;
use PHPUnit\Framework\TestCase;

class OpenApiContextTest extends TestCase
{
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->request = $this->createStub(HttpRequest::class);
    }

    private function schema(string $fixture): Schema
    {
        return Schema::fromFile(__DIR__ . '/' . $fixture);
    }

    private function setupRequest(
        string $path,
        string $method,
        string $contentType,
        string $payload = '',
        array  $post = []
    ): void {
        $this->request->method('getRequestPath')->willReturn($path);
        $this->request->method('serverString')->willReturnMap([
            ['REQUEST_METHOD', null, $method],
        ]);
        $this->request->method('getHeader')->willReturnMap([
            ['Content-Type', $contentType],
        ]);
        $this->request->method('payload')->willReturn($payload);
        $this->request->method('body')->willReturn($post);
    }

    // =========================================================================
    // JSON
    // =========================================================================

    public function testValidJsonReturnsArray(): void
    {
        $this->setupRequest('/test/json', 'POST', 'application/json', json_encode([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]));

        $result = OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-json.json'));

        $this->assertIsArray($result);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function testMissingRequiredFieldThrowsError400(): void
    {
        $this->expectException(Error400Exception::class);

        $this->setupRequest('/test/json', 'POST', 'application/json', json_encode([
            'name' => 'John Doe',
            // email missing
        ]));

        OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-json.json'));
    }

    public function testEmptyBodyThrowsError400(): void
    {
        $this->expectException(Error400Exception::class);

        $this->setupRequest('/test/json', 'POST', 'application/json', '');

        OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-json.json'));
    }

    public function testNullValuesRemovedByDefault(): void
    {
        $this->setupRequest('/test/json', 'POST', 'application/json', json_encode([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'role'  => null,
        ]));

        $result = OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-json.json'));

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('role', $result);
    }

    public function testNullValuesPreservedWhenFlagSet(): void
    {
        $this->setupRequest('/test/json', 'POST', 'application/json', json_encode([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'role'  => null,
        ]));

        $result = OpenApiContext::validateRequest($this->request, true, $this->schema('openapi-json.json'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('role', $result);
        $this->assertNull($result['role']);
    }

    // =========================================================================
    // XML
    // =========================================================================

    public function testXmlContentTypeReturnsXmlDocument(): void
    {
        $xml = '<?xml version="1.0"?><root><name>John</name></root>';

        $this->setupRequest('/test/xml', 'POST', 'application/xml', $xml);

        $result = OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-xml.json'));

        $this->assertInstanceOf(XmlDocument::class, $result);
    }

    public function testXmlContentTypeWithTextXmlHeader(): void
    {
        $xml = '<?xml version="1.0"?><root><name>John</name></root>';

        $this->setupRequest('/test/xml', 'POST', 'text/xml', $xml);

        $result = OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-xml.json'));

        $this->assertInstanceOf(XmlDocument::class, $result);
    }

    // =========================================================================
    // Multipart
    // =========================================================================

    public function testMultipartReadsFromPost(): void
    {
        $this->setupRequest(
            '/test/multipart',
            'POST',
            'multipart/form-data',
            '',
            ['name' => 'John Doe']
        );

        $result = OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-multipart.json'));

        $this->assertIsArray($result);
        $this->assertEquals('John Doe', $result['name']);
    }

    public function testMultipartMissingRequiredFieldThrowsError400(): void
    {
        $this->expectException(Error400Exception::class);

        $this->setupRequest('/test/multipart', 'POST', 'multipart/form-data', '', []);

        OpenApiContext::validateRequest($this->request, false, $this->schema('openapi-multipart.json'));
    }
}
