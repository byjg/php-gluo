<?php

namespace RestReferenceArchitecture\Controller;

use ByJG\Authenticate\Service\UsersService;
use ByJG\Config\Exception\ConfigException;
use ByJG\Config\Exception\ConfigNotFoundException;
use ByJG\Config\Exception\DependencyInjectionException;
use ByJG\Config\Exception\KeyNotFoundException;
use ByJG\Gluo\Attribute\RequireAuthenticated;
use ByJG\Gluo\Attribute\ValidateRequest;
use ByJG\Gluo\Util\JwtContext;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\RestServer\Exception\Error422Exception;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use OpenApi\Attributes as OA;

/**
 * Profile of the currently authenticated user (the app shell — not an example).
 */
class ProfileController
{
    public function __construct(protected UsersService $usersService)
    {
    }

    /** Languages allowed for the `language` user property. */
    private const LANGUAGES = ['en', 'fr', 'pt'];

    /**
     * Return the profile of the logged-in user (from the JWT).
     *
     * @throws ConfigException
     * @throws ConfigNotFoundException
     * @throws DependencyInjectionException
     * @throws Error401Exception
     * @throws Error404Exception
     * @throws KeyNotFoundException
     */
    #[OA\Get(
        path: "/profile",
        security: [["jwt-token" => []]],
        tags: ["Profile"]
    )]
    #[OA\Response(
        response: 200,
        description: "The profile of the authenticated user",
        content: new OA\JsonContent(
            required: ["userid", "name", "email"],
            properties: [
                new OA\Property(property: "userid", type: "string"),
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "username", type: "string"),
                new OA\Property(property: "language", type: "string", nullable: true, enum: ["en", "fr", "pt"]),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    public function getProfile(HttpResponse $response, HttpRequest $request): void
    {
        $user = JwtContext::getUser();
        // `language` is stored in the users_property table, loaded with the user.
        $language = $user->get('language');
        $response->write([
            "userid" => (string)$user->getUserid(),
            "name" => $user->getName(),
            "email" => $user->getEmail(),
            "username" => $user->getUsername(),
            "language" => is_string($language) ? $language : null,
        ]);
    }

    /**
     * Update the name/email of the logged-in user.
     *
     * @throws ConfigException
     * @throws ConfigNotFoundException
     * @throws DependencyInjectionException
     * @throws Error401Exception
     * @throws Error404Exception
     * @throws KeyNotFoundException
     */
    #[OA\Put(
        path: "/profile",
        security: [["jwt-token" => []]],
        tags: ["Profile"]
    )]
    #[OA\RequestBody(
        description: "The profile fields to update",
        required: true,
        content: new OA\JsonContent(
            required: ["name", "email"],
            properties: [
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "language", type: "string", nullable: true, enum: ["en", "fr", "pt"]),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Profile updated",
        content: new OA\JsonContent(
            required: ["userid", "name", "email"],
            properties: [
                new OA\Property(property: "userid", type: "string"),
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "email", type: "string"),
                new OA\Property(property: "language", type: "string", nullable: true, enum: ["en", "fr", "pt"]),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    #[ValidateRequest]
    public function putProfile(HttpResponse $response, HttpRequest $request): void
    {
        $payload = ValidateRequest::getPayload();
        $user = JwtContext::getUser();
        $user->setName($payload['name']);
        $user->setEmail($payload['email']);

        if (array_key_exists('language', $payload) && $payload['language'] !== null) {
            if (!in_array($payload['language'], self::LANGUAGES, true)) {
                throw new Error422Exception('language must be one of: ' . implode(', ', self::LANGUAGES));
            }
            // Stored in the users_property table.
            $user->set('language', $payload['language']);
        }

        $this->usersService->save($user);

        $language = $user->get('language');
        $response->write([
            "userid" => (string)$user->getUserid(),
            "name" => $user->getName(),
            "email" => $user->getEmail(),
            "language" => is_string($language) ? $language : null,
        ]);
    }
}
