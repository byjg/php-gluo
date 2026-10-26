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
use ByJG\Gluo\Util\OrderBy;
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
use RestReferenceArchitecture\Model\Task;
use RestReferenceArchitecture\Model\User;
use RestReferenceArchitecture\Service\TaskService;

class TaskController
{
    public function __construct(protected TaskService $taskService)
    {
    }

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
        $result = $this->taskService->getOrFail($request->attribute('id'));
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
        description: "Fields to sort by, separated by commas; add ' desc' after a field for descending order. Example: name desc,id",
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
        $result = $this->taskService->list(
            (int)($request->queryString('page') ?? 0),
            (int)($request->queryString('size') ?? 50),
            OrderBy::fromQueryString($request->queryString('orderBy'), Task::class)
        );
        $response->write($result);
    }

    /**
     * List the tasks of a project, filtered by the database instead of by the client.
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     */
    #[OA\Get(
        path: "/project/{projectId}/task",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Task"]
    )]
    #[OA\Parameter(
        name: "projectId",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "integer", format: "int32")
    )]
    #[OA\Response(
        response: 200,
        description: "The tasks belonging to the project",
        content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Task"))
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    public function listTasksByProject(HttpResponse $response, HttpRequest $request): void
    {
        $response->write($this->taskService->getByProjectId((int)$request->attribute('projectId')));
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
                new OA\Property(property: "title", type: "string", format: "string", maxLength: 150, minLength: 1),
                new OA\Property(property: "status", type: "string", format: "string", maxLength: 20)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The created object",
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
        $model = $this->taskService->create(ValidateRequest::getPayload());
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
        path: "/task/{id}",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Task"]
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
    #[OA\RequestBody(
        description: "The object Task to be updated",
        required: true,
        content: new OA\JsonContent(
            required: [ "projectId", "title", "status" ],
            properties: [

                new OA\Property(property: "projectId", type: "integer", format: "int32"),
                new OA\Property(property: "title", type: "string", format: "string", maxLength: 150, minLength: 1),
                new OA\Property(property: "status", type: "string", format: "string", maxLength: 20)
            ]
        )
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
        $this->taskService->update($request->attribute('id'), ValidateRequest::getPayload());
    }

}
