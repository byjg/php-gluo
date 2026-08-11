<?php

namespace RestReferenceArchitecture\Controller;

use ByJG\Config\Config;
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
use RestReferenceArchitecture\Service\TaskService;

class TaskController
{
    /**
     * Get the Task by id
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
        path: "/task/{id}",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Task"],
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(
            type: "string",
            format: "string"
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object Task",
        content: new OA\JsonContent(ref: "#/components/schemas/Task")
    )]
    #[RequireAuthenticated]
    public function getTask(HttpResponse $response, HttpRequest $request): void
    {
        $taskService = Config::get(TaskService::class);
        $result = $taskService->getOrFail($request->attribute('id'));
        $response->write($result);
    }

    /**
     * List Task
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
        path: "/task",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Task"]
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
        description: "The object Task",
        content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Task"))
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    public function listTask(HttpResponse $response, HttpRequest $request): void
    {
        $taskService = Config::get(TaskService::class);
        $result = $taskService->list((int)($request->queryString('page') ?? 0), (int)($request->queryString('size') ?? 50));
        $response->write($result);
    }


    /**
     * Create a new Task 
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
        path: "/task",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Task"]
    )]
    #[OA\RequestBody(
        description: "The object Task to be created",
        required: true,
        content: new OA\JsonContent(
            required: [ "projectId", "title", "status" ],
            properties: [

                new OA\Property(property: "projectId", type: "integer", format: "int32"),
                new OA\Property(property: "title", type: "string", format: "string"),
                new OA\Property(property: "status", type: "string", format: "string")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object rto be created",
        content: new OA\JsonContent(
            required: [ "id" ],
            properties: [

                new OA\Property(property: "id", type: "string", format: "string")
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
    public function postTask(HttpResponse $response, HttpRequest $request): void
    {
        $taskService = Config::get(TaskService::class);
        $model = $taskService->create(ValidateRequest::getPayload());
        $response->write(["id" => $model->getId()]);
    }


    /**
     * Update an existing Task 
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
        path: "/task",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Task"]
    )]
    #[OA\RequestBody(
        description: "The object Task to be updated",
        required: true,
        content: new OA\JsonContent(ref: "#/components/schemas/Task")
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
    public function putTask(HttpResponse $response, HttpRequest $request): void
    {
        $taskService = Config::get(TaskService::class);
        $taskService->update(ValidateRequest::getPayload());
    }

}
