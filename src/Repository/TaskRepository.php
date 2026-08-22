<?php

namespace RestReferenceArchitecture\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Gluo\Repository\BaseRepository;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ReflectionException;
use RestReferenceArchitecture\Model\Task;

class TaskRepository extends BaseRepository
{
    /**
     * TaskRepository constructor.
     *
     * @param DatabaseExecutor $executor
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Task::class);
    }


    /**
     * @param mixed $projectId
     * @return null|Task[]
     */
    public function getByProjectId($projectId)
    {
        $query = Query::getInstance()
            ->table('task')
            ->where('task.project_id = :value', ['value' => $projectId]);
        return $this->repository->getByQuery($query);
    }

}
