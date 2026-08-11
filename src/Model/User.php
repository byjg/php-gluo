<?php

namespace RestReferenceArchitecture\Model;

use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\Gluo\Model\BaseUser;
use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaDeletedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableMySqlUuidPKAttribute;
use ByJG\MicroOrm\Literal\Literal;
use OpenApi\Attributes as OA;

// Constants (ROLE_*, PROP_*, VALUE_*), the password definition wiring and the
// base fields live in BaseUser (byjg/gluo). This class owns the table mapping
// and the OpenAPI schema. NOTE: not a docblock — swagger-php would publish it
// as the schema description.
#[TableMySqlUuidPKAttribute("users")]
#[OA\Schema(required: ["email"], type: "object", xml: new OA\Xml(name: "User"))]
class User extends BaseUser
{
    use OaCreatedAt;
    use OaUpdatedAt;
    use OaDeletedAt;

    /**
     * @var ?string|int|Literal
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldUuidAttribute(primaryKey: true)]
    protected string|int|Literal|null $userid = null;

    /**
     * @var ?string
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute]
    protected ?string $name = null;

    /**
     * @var ?string
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute]
    protected ?string $email = null;

    /**
     * @var ?string
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute]
    protected ?string $username = null;

    /**
     * @var ?string
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute(updateFunction: PasswordSha1Mapper::class)]
    protected ?string $password = null;

    /**
     * @var ?string
     */
    #[OA\Property(type: "string", format: "string")]
    #[FieldAttribute]
    protected ?string $role = null;
}
