<?php

namespace RestReferenceArchitecture\Generator;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use Override;
use RuntimeException;

class UuidSeedGenerator implements MapperFunctionInterface
{

    #[Override]
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        if ($executor === null) {
            throw new RuntimeException("DatabaseExecutor is required for UUID generation");
        }
        $value = $executor->getScalar("SELECT hex(uuid_to_bin(uuid()))");
        return new HexUuidLiteral($value);
    }
}
