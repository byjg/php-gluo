<?php

namespace RestReferenceArchitecture\Controller;

use ByJG\Gluo\Attribute\RequireAuthenticated;
use ByJG\Gluo\Attribute\ValidateRequest;
use ByJG\Gluo\Controller\BaseLoginController;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use OpenApi\Attributes as OA;
use Override;

/**
 * The business logic lives in BaseLoginController (byjg/gluo).
 * This class owns the API contract: OpenAPI attributes and route bindings.
 */
class LoginController extends BaseLoginController
{
    /**
     * Do login
     *
     */
    #[OA\Post(
        path: "/login",
        tags: ["Login"],
    )]
    #[OA\RequestBody(
        description: "The Login Data",
        required: true,
        content: new OA\JsonContent(
            required: [ "username", "password" ],
            properties: [
                new OA\Property(property: "username", description: "The Username", type: "string", format: "string"),
                new OA\Property(property: "password", description: "The Password",  type: "string", format: "string")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object to be created",
        content: new OA\JsonContent(
            required: [ "token" ],
            properties: [
                new OA\Property(property: "token", type: "string"),
                new OA\Property(property: "data", properties: [
                    new OA\Property(property: "userid", type: "string"),
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "role", type: "string"),
                ])
            ]
        )
    )]
    #[ValidateRequest]
    #[Override]
    public function post(HttpResponse $response, HttpRequest $request): void
    {
        parent::post($response, $request);
    }

    /**
     * Refresh Token
     *
     */
    #[OA\Post(
        path: "/refreshtoken",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Login"]
    )]
    #[OA\Response(
        response: 200,
        description: "The object rto be created",
        content: new OA\JsonContent(
            required: [ "token" ],
            properties: [
                new OA\Property(property: "token", type: "string"),
                new OA\Property(property: "data", properties: [
                    new OA\Property(property: "role", type: "string"),
                    new OA\Property(property: "userid", type: "string"),
                    new OA\Property(property: "name", type: "string")
                ], type: "object")
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Não autorizado",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    #[Override]
    public function refreshToken(HttpResponse $response, HttpRequest $request): void
    {
        parent::refreshToken($response, $request);
    }

    /**
     * Initialize the Password Request
     *
     */
    #[OA\Post(
        path: "/login/resetrequest",
        tags: ["Login"]
    )]
    #[OA\RequestBody(
        description: "The email to have the password reset",
        content: new OA\JsonContent(
            required: [ "email" ],
            properties: [
                new OA\Property(property: "email", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The token for reset",
        content: new OA\JsonContent(
            required: [ "token" ],
            properties: [
                new OA\Property(property: "token", type: "string"),
            ]
        )
    )]
    #[ValidateRequest]
    #[Override]
    public function postResetRequest(HttpResponse $response, HttpRequest $request): void
    {
        parent::postResetRequest($response, $request);
    }

    /**
     * Initialize the Password Request
     *
     */
    #[OA\Post(
        path: "/login/confirmcode",
        tags: ["Login"]
    )]
    #[OA\RequestBody(
        description: "The email and code to confirm the password reset",
        content: new OA\JsonContent(
            required: [ "email", "token", "code" ],
            properties: [
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "token", type: "string"),
                new OA\Property(property: "code", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The token for reset",
        content: new OA\JsonContent(
            required: [ "token" ],
            properties: [
                new OA\Property(property: "token", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: "Invalid data",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[Override]
    public function postConfirmCode(HttpResponse $response, HttpRequest $request): void
    {
        parent::postConfirmCode($response, $request);
    }

    /**
     * Initialize the Password Request
     *
     */
    #[OA\Post(
        path: "/login/resetpassword",
        tags: ["Login"]
    )]
    #[OA\RequestBody(
        description: "The email and the new password",
        content: new OA\JsonContent(
            required: [ "email", "token", "password" ],
            properties: [
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "token", type: "string"),
                new OA\Property(property: "password", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The token for reset",
        content: new OA\JsonContent(
            required: [ "token" ],
            properties: [
                new OA\Property(property: "token", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: "Invalid data",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[Override]
    public function postResetPassword(HttpResponse $response, HttpRequest $request): void
    {
        parent::postResetPassword($response, $request);
    }
}
