<?php

namespace RestReferenceArchitecture\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Gluo\Repository\BaseRepository;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ReflectionException;
use RestReferenceArchitecture\Model\Project;

class ProjectRepository extends BaseRepository
{
    /**
     * ProjectRepository constructor.
     *
     * @param DatabaseExecutor $executor
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Project::class);
    }


    /**
     * @param mixed $name
     * @return null|Project[]
     */
    public function getByName($name)
    {
        $query = Query::getInstance()
            ->table('project')
            ->where('project.name = :value', ['value' => $name]);
        return $this->repository->getByQuery($query);
    }

}
