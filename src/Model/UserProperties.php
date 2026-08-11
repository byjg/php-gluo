<?php

namespace RestReferenceArchitecture\Model;

use ByJG\Gluo\Model\BaseUserProperties;
use ByJG\MicroOrm\Attributes\TableAttribute;

#[TableAttribute(tableName: 'users_property')]
class UserProperties extends BaseUserProperties
{
}
