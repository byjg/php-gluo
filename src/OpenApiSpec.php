<?php

namespace RestReferenceArchitecture;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'REST API built on Gluo',
    title: 'Gluo API',
    termsOfService: 'http://localhost:8080/terms/',
)]
#[OA\Contact(email: 'someone@example.com')]
#[OA\License(name: 'Proprietary')]
#[OA\ExternalDocumentation(
    description: 'API reference',
    url: 'http://localhost:8080/docs'
)]
#[OA\SecurityScheme(
    securityScheme: 'jwt-token',
    type: 'http',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
#[OA\Schema(
    schema: 'error',
    properties: [
        new OA\Property(
            'error',
            properties: [
                new OA\Property(property: 'type', description: 'The exception class', type: 'string'),
                new OA\Property(property: 'message', description: 'The error message', type: 'string'),
                new OA\Property(property: 'file', description: 'The file that raised the error', type: 'string'),
                new OA\Property(property: 'line', description: 'The line of the error', type: 'integer'),
            ]
        )
    ],
    type: 'object'
)]
class OpenApiSpec
{
    // Do not delete this class. Necessary for OpenAPI.
}
