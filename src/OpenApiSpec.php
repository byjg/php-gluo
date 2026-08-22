<?php

namespace RestReferenceArchitecture;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Information about the API',
    title: 'API Title',
    termsOfService: 'http://localhost:8080/terms/',
)]
#[OA\Contact(email: 'someone@example.com')]
#[OA\License(name: 'Proprietary')]
#[OA\ExternalDocumentation(
    description: 'Find out more about Swagger',
    url: 'http://localhost:8080/docs'
)]
#[OA\SecurityScheme(
    securityScheme: 'jwt-token',
    type: 'apiKey',
    name: 'Authorization',
    in: 'header'
)]
#[OA\Schema(
    schema: 'error',
    properties: [
        new OA\Property(
            'error',
            properties: [
                new OA\Property(property: 'type', description: 'A class de Exceção', type: 'string'),
                new OA\Property(property: 'message', description: 'A mensagem de erro', type: 'string'),
                new OA\Property(property: 'file', description: 'O arquivo que gerou o erro', type: 'string'),
                new OA\Property(property: 'line', description: 'A linha do erro', type: 'integer'),
            ]
        )
    ],
    type: 'object'
)]
class OpenApiSpec
{
    // Do not delete this class. Necessary for OpenAPI.
}
