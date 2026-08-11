<?php

namespace RestReferenceArchitecture\Service;

use ByJG\Gluo\Service\BaseService;
use RestReferenceArchitecture\Repository\TaskRepository;

class TaskService extends BaseService
{
    public function __construct(TaskRepository $baseRepository)
    {
        parent::__construct($baseRepository);
    }
}
