<?php

namespace RestReferenceArchitecture\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use OpenApi\Attributes as OA;


/**
 * Class Project
 * @package RestReferenceArchitecture\Model
 */
#[OA\Schema(required: ["id", "name"], type: "object", xml: new OA\Xml(name: "Project"))]
#[TableAttribute("project")]
class Project
{

    /**
     * @var int|null
     */
    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    /**
     * @var string|null
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute(fieldName: "name")]
    protected string|null $name = null;

    /**
     * @var string|null
     */
    #[OA\Property(type: "string", format: "string", nullable: true)]
    #[FieldAttribute(fieldName: "description")]
    protected string|null $description = null;



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
     * @return string|null
     */
    public function getName(): string|null
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function setName(string|null $name): static
    {
        
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): string|null
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(string|null $description): static
    {
        
        $this->description = $description;
        return $this;
    }


}
