<?php

namespace RestReferenceArchitecture\Service;

use ByJG\Gluo\Service\BaseService;
use RestReferenceArchitecture\Repository\ProjectRepository;

class ProjectService extends BaseService
{
    public function __construct(ProjectRepository $baseRepository)
    {
        parent::__construct($baseRepository);
    }
}
