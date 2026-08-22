<?php

namespace RestReferenceArchitecture\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableMySqlUuidPKAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\LiteralInterface;
use OpenApi\Attributes as OA;


/**
 * Class Task
 * @package RestReferenceArchitecture\Model
 */
#[OA\Schema(required: ["id", "projectId", "title", "status"], type: "object", xml: new OA\Xml(name: "Task"))]
#[TableMySqlUuidPKAttribute("task")]
class Task
{

    /**
     * @var string|LiteralInterface|null
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldUuidAttribute(primaryKey: true, fieldName: "id")]
    protected string|LiteralInterface|null $id = null;

    /**
     * @var int|null
     */
    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(fieldName: "project_id", parentTable: "project")]
    protected int|null $projectId = null;

    /**
     * @var string|null
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute(fieldName: "title")]
    protected string|null $title = null;

    /**
     * @var string|null
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute(fieldName: "status")]
    protected string|null $status = null;



    /**
     * @return string|LiteralInterface|null
     */
    public function getId(): string|LiteralInterface|null
    {
        return $this->id;
    }

    /**
     * @param string|LiteralInterface|null $id
     * @return $this
     */
    public function setId(string|LiteralInterface|null $id): static
    {
        if ($id instanceof LiteralInterface) {
            $id = new HexUuidLiteral($id);
        }
        $this->id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getProjectId(): int|null
    {
        return $this->projectId;
    }

    /**
     * @param int|null $projectId
     * @return $this
     */
    public function setProjectId(int|null $projectId): static
    {
        
        $this->projectId = $projectId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTitle(): string|null
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(string|null $title): static
    {
        
        $this->title = $title;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getStatus(): string|null
    {
        return $this->status;
    }

    /**
     * @param string|null $status
     * @return $this
     */
    public function setStatus(string|null $status): static
    {
        
        $this->status = $status;
        return $this;
    }


}
