<?php

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Config\Config;
use ByJG\MicroOrm\Literal\HexUuidLiteral;

$executor = Config::get(DatabaseExecutor::class);

function dump($var) {
    var_dump($var);
}

function uuid_to_bin($uuid) {
    return HexUuidLiteral::getFormattedUuid($uuid);
}

function qq(string $sql, array $params = []) {
    var_dump(Config::get(DatabaseExecutor::class)->getIterator($sql, $params)->toArray());
}
