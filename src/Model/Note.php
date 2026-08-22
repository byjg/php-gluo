<?php

namespace RestReferenceArchitecture\Model;

use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaDeletedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\LiteralInterface;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Trait\ActiveRecord;
use OpenApi\Attributes as OA;
use RuntimeException;


/**
 * Class Note
 * @package RestReferenceArchitecture\Model
 */
#[OA\Schema(required: ["id", "taskId", "body"], type: "object", xml: new OA\Xml(name: "Note"))]
#[TableAttribute("note")]
class Note
{
    // Add the ActiveRecord trait to enable Active Record pattern
    use ActiveRecord;

    // Add timestamp traits for automatic timestamp handling
    use OaCreatedAt;
    use OaUpdatedAt;
    use OaDeletedAt;


    /**
     * @var int|null
     */
    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    /**
     * @var string|LiteralInterface|null
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldUuidAttribute(fieldName: "task_id", parentTable: "task")]
    protected string|LiteralInterface|null $taskId = null;

    /**
     * @var string|null
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute(fieldName: "body")]
    protected string|null $body = null;

    /**
     * Read-only DB VIRTUAL GENERATED column: the database computes char_length(body)
     * on every read. Mapped with syncWithDb:false so the app never tries to write it.
     * This is the canonical "computed in the database" example.
     *
     * @var int|null
     */
    #[OA\Property(type: "integer", format: "int32", nullable: true)]
    #[FieldAttribute(fieldName: "body_length", syncWithDb: false)]
    protected int|null $bodyLength = null;

    /**
     * Read-only computed field: whole days elapsed since the note was created.
     * Unlike bodyLength, this canNOT be a DB generated column (it depends on NOW(),
     * which MySQL rejects in a generated expression), so getDays() derives it in PHP
     * from `created_at`. The FieldAttribute(syncWithDb:false) only keeps it out of
     * writes; the value itself is produced by the getter, not hydrated from a column.
     *
     * @var int|null
     */
    #[OA\Property(type: "integer", format: "int32", nullable: true)]
    #[FieldAttribute(fieldName: "created_at", syncWithDb: false)]
    protected int|null $days = null;



    /**
     * @return int|null
     */
    public function getId(): int|null
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return $this
     */
    public function setId(int|null $id): static
    {
        
        $this->id = $id;
        return $this;
    }

    /**
     * @return string|LiteralInterface|null
     */
    public function getTaskId(): string|LiteralInterface|null
    {
        return $this->taskId;
    }

    /**
     * @param string|LiteralInterface|null $taskId
     * @return $this
     */
    public function setTaskId(string|LiteralInterface|null $taskId): static
    {
        if ($taskId instanceof LiteralInterface) {
            $taskId = new HexUuidLiteral($taskId);
        }
        $this->taskId = $taskId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBody(): string|null
    {
        return $this->body;
    }

    /**
     * @param string|null $body
     * @return $this
     */
    public function setBody(string|null $body): static
    {

        $this->body = $body;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getBodyLength(): int|null
    {
        return $this->bodyLength;
    }

    /**
     * Setter used only for hydration from the DB (the value is a generated column,
     * never written back). ObjectCopy needs it to populate the property.
     *
     * @param int|null $bodyLength
     * @return $this
     */
    public function setBodyLength(int|null $bodyLength): static
    {
        $this->bodyLength = $bodyLength;
        return $this;
    }

    /**
     * Whole days between created_at and now (computed in PHP, read-only).
     *
     * @return int|null
     */
    public function getDays(): int|null
    {
        if (empty($this->createdAt)) {
            return null;
        }
        $timestamp = strtotime($this->createdAt);
        return $timestamp === false ? null : (int)floor((time() - $timestamp) / 86400);
    }



    /**
     * @param mixed $taskId
     * @return null|Note[]
     */
    public static function getByTaskId($taskId): ?array
    {
        if (self::$repository === null) {
            throw new RuntimeException("Repository not initialized");
        }
        $query = Query::getInstance()
            ->table(self::$repository->getMapper()->getTable(), 'alias')
            ->where('alias.task_id = :value', ['value' => $taskId]);
        return self::query($query);
    }

    /**
     * All notes belonging to a project, across the note -> task -> project relationship.
     * A note only carries a task_id, so this cannot be a plain WHERE. We name each entity
     * in the path (Task is the through-entity, like Eloquent's hasManyThrough): passing
     * the classes registers Task's and Project's mappers on demand — reflection only, no
     * DB connection — so the parentTable relationships (Note::taskId -> task,
     * Task::projectId -> project) resolve on a request that only touched Note. joinWith()
     * then derives both ON conditions; `note.*` keeps the rows hydrating cleanly into Note.
     *
     * @param mixed $projectId
     * @return Note[]
     */
    public static function getByProjectId($projectId): array
    {
        $query = self::joinWith(Task::class, Project::class)
            ->field('note.*')
            ->where('project.id = :id', ['id' => $projectId]);
        return self::query($query);
    }

}
