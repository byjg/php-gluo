<?php

namespace RestReferenceArchitecture\Controller;

use ByJG\Config\Exception\ConfigException;
use ByJG\Config\Exception\ConfigNotFoundException;
use ByJG\Config\Exception\DependencyInjectionException;
use ByJG\Config\Exception\InvalidDateException;
use ByJG\Config\Exception\KeyNotFoundException;
use ByJG\Gluo\Attribute\RequireAuthenticated;
use ByJG\Gluo\Attribute\RequireRole;
use ByJG\Gluo\Attribute\ValidateRequest;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\RestServer\Exception\Error400Exception;
use ByJG\RestServer\Exception\Error401Exception;
use ByJG\RestServer\Exception\Error403Exception;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use OpenApi\Attributes as OA;
use ReflectionException;
use RestReferenceArchitecture\Model\User;
use RestReferenceArchitecture\Service\ProjectService;

class ProjectController
{
    public function __construct(protected ProjectService $projectService)
    {
    }

    /**
     * Get the Project by id
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @throws ConfigException
     * @throws ConfigNotFoundException
     * @throws DependencyInjectionException
     * @throws Error401Exception
     * @throws Error404Exception
     * @throws InvalidArgumentException
     * @throws InvalidDateException
     * @throws KeyNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws ReflectionException
     */
    #[OA\Get(
        path: "/project/{id}",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Project"],
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(
            type: "integer",
            format: "int32"
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object Project",
        content: new OA\JsonContent(ref: "#/components/schemas/Project")
    )]
    #[RequireAuthenticated]
    public function getProject(HttpResponse $response, HttpRequest $request): void
    {
        $result = $this->projectService->getOrFail($request->attribute('id'));
        $response->write($result);
    }

    /**
     * List Project
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     * @throws ConfigException
     * @throws ConfigNotFoundException
     * @throws DependencyInjectionException
     * @throws Error401Exception
     * @throws InvalidArgumentException
     * @throws InvalidDateException
     * @throws KeyNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws ReflectionException
     */
    #[OA\Get(
        path: "/project",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Project"]
    )]
    #[OA\Parameter(
        name: "page",
        description: "Page number",
        in: "query",
        required: false,
        schema: new OA\Schema(
            type: "integer",
        )
    )]
    #[OA\Parameter(
        name: "size",
        description: "Page size",
        in: "query",
        required: false,
        schema: new OA\Schema(
            type: "integer",
        )
    )]
    #[OA\Parameter(
        name: "orderBy",
        description: "Order by",
        in: "query",
        required: false,
        schema: new OA\Schema(
            type: "string",
        )
    )]
    #[OA\Parameter(
        name: "filter",
        description: "Filter",
        in: "query",
        required: false,
        schema: new OA\Schema(
            type: "string",
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object Project",
        content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Project"))
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    public function listProject(HttpResponse $response, HttpRequest $request): void
    {
        $result = $this->projectService->list((int)($request->queryString('page') ?? 0), (int)($request->queryString('size') ?? 50));
        $response->write($result);
    }


    /**
     * Create a new Project 
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     * @throws ConfigException
     * @throws ConfigNotFoundException
     * @throws DependencyInjectionException
     * @throws Error400Exception
     * @throws Error401Exception
     * @throws Error403Exception
     * @throws InvalidArgumentException
     * @throws InvalidDateException
     * @throws KeyNotFoundException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws ReflectionException
     */
    #[OA\Post(
        path: "/project",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Project"]
    )]
    #[OA\RequestBody(
        description: "The object Project to be created",
        required: true,
        content: new OA\JsonContent(
            required: [ "name" ],
            properties: [

                new OA\Property(property: "name", type: "string", format: "string"),
                new OA\Property(property: "description", type: "string", format: "string", nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object rto be created",
        content: new OA\JsonContent(
            required: [ "id" ],
            properties: [

                new OA\Property(property: "id", type: "integer", format: "int32")
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireRole(User::ROLE_ADMIN)]
    #[ValidateRequest]
    public function postProject(HttpResponse $response, HttpRequest $request): void
    {
        $model = $this->projectService->create(ValidateRequest::getPayload());
        $response->write(["id" => $model->getId()]);
    }


    /**
     * Update an existing Project 
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     * @throws Error401Exception
     * @throws Error404Exception
     * @throws ConfigException
     * @throws ConfigNotFoundException
     * @throws DependencyInjectionException
     * @throws InvalidDateException
     * @throws KeyNotFoundException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws Error400Exception
     * @throws Error403Exception
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws ReflectionException
     */
    #[OA\Put(
        path: "/project",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Project"]
    )]
    #[OA\RequestBody(
        description: "The object Project to be updated",
        required: true,
        content: new OA\JsonContent(ref: "#/components/schemas/Project")
    )]
    #[OA\Response(
        response: 200,
        description: "Nothing to return"
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireRole(User::ROLE_ADMIN)]
    #[ValidateRequest]
    public function putProject(HttpResponse $response, HttpRequest $request): void
    {
        $this->projectService->update(ValidateRequest::getPayload());
    }

}
