<?php

namespace Mahadirz\GoogleDatastore\Eloquent;

use Mahadirz\GoogleDatastore\Query\Builder as QueryBuilder;
use Mahadirz\GoogleDatastore\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;
abstract class Model extends BaseModel
{
    protected $excludeFromIndexes = array();
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param QueryBuilderr $query
     *
     * @return EloquentBuilder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();
        $builder = new QueryBuilder($connection, $connection->getPostProcessor());
        $builder->excludeFromIndexes = $this->excludeFromIndexes;
        return $builder;
    }


    /**
     * Handle dynamic method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Unset method
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }
}
