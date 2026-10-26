<?php

namespace RestReferenceArchitecture\Service;

use ByJG\Gluo\Service\BaseService;
use RestReferenceArchitecture\Model\Task;
use RestReferenceArchitecture\Repository\TaskRepository;

class TaskService extends BaseService
{
    public function __construct(TaskRepository $baseRepository)
    {
        parent::__construct($baseRepository);
    }

    /**
     * @return Task[]
     */
    public function getByProjectId(int $projectId): array
    {
        /** @var TaskRepository $repository */
        $repository = $this->baseRepository;
        return $repository->getByProjectId($projectId) ?? [];
    }
}
