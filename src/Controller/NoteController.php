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
use RestReferenceArchitecture\Model\Note;
use RestReferenceArchitecture\Model\User;

class NoteController
{
    /**
     * Get the Note by id
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @throws Error404Exception
     */
    #[OA\Get(
        path: "/note/{id}",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Note"],
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
        description: "The object Note",
        content: new OA\JsonContent(ref: "#/components/schemas/Note")
    )]
    #[RequireAuthenticated]
    public function getNote(HttpResponse $response, HttpRequest $request): void
    {
        $model = Note::get($request->attribute('id'));

        if (is_null($model)) {
            throw new Error404Exception("Note not found");
        }

        $response->write($model);
    }

    /**
     * List Note
     *
     * @param mixed $response
     * @param mixed $request
     * @return void
     */
    #[OA\Get(
        path: "/note",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Note"]
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
        description: "The object Note",
        content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Note"))
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    public function listNote(HttpResponse $response, HttpRequest $request): void
    {
        // Get all records with pagination (default is page 0, limit 50)
        $models = Note::all((int)($request->queryString('page') ?? 0), (int)($request->queryString('size') ?? 50));
        $response->write($models);
    }

    /**
     * List every note across a whole project (note -> task -> project). A note only
     * carries a task_id, so this spans two relationships; Note::getByProjectId() uses
     * joinWith(Task::class, Project::class), naming the through-entity so its mapper
     * registers on demand and both JOINs resolve.
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     */
    #[OA\Get(
        path: "/project/{projectId}/note",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Note"]
    )]
    #[OA\Parameter(
        name: "projectId",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "integer", format: "int32")
    )]
    #[OA\Response(
        response: 200,
        description: "The notes belonging to the project",
        content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Note"))
    )]
    #[OA\Response(
        response: 401,
        description: "Not Authorized",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[RequireAuthenticated]
    public function listNotesByProject(HttpResponse $response, HttpRequest $request): void
    {
        $response->write(Note::getByProjectId($request->attribute('projectId')));
    }


    /**
     * Create a new Note
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     */
    #[OA\Post(
        path: "/note",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Note"]
    )]
    #[OA\RequestBody(
        description: "The object Note to be created",
        required: true,
        content: new OA\JsonContent(
            required: [ "taskId", "body" ],
            properties: [

                new OA\Property(property: "taskId", type: "string", format: "string"),
                new OA\Property(property: "body", type: "string", format: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The object to be created",
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
    public function postNote(HttpResponse $response, HttpRequest $request): void
    {
        $payload = ValidateRequest::getPayload();

        // Create a new ActiveRecord instance with payload
        $model = Note::new($payload);
        $model->save();

        $response->write(["id" => $model->getId()]);
    }


    /**
     * Update an existing Note
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
        path: "/note",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Note"]
    )]
    #[OA\RequestBody(
        description: "The object Note to be updated",
        required: true,
        content: new OA\JsonContent(ref: "#/components/schemas/Note")
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
    public function putNote(HttpResponse $response, HttpRequest $request): void
    {
        $payload = ValidateRequest::getPayload();

        $model = Note::get($payload['id'] ?? null);

        if (is_null($model)) {
            throw new Error404Exception("Note not found");
        }

        // Update model with payload using fill() method
        $model->fill($payload);
        $model->save();
    }

    /**
     * Soft-delete a Note. Because the model uses the OaDeletedAt trait, delete()
     * sets deleted_at instead of removing the row, and the record then disappears
     * from get()/all() while remaining in the table.
     *
     * @param HttpResponse $response
     * @param HttpRequest $request
     * @return void
     * @throws Error404Exception
     */
    #[OA\Delete(
        path: "/note/{id}",
        security: [
            ["jwt-token" => []]
        ],
        tags: ["Note"]
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "integer", format: "int32")
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
    public function deleteNote(HttpResponse $response, HttpRequest $request): void
    {
        $model = Note::get($request->attribute('id'));

        if (is_null($model)) {
            throw new Error404Exception("Note not found");
        }

        $model->delete();
    }

}
