<?php

namespace Mahadirz\GoogleDatastore\Query;

use Google\Cloud\Datastore\Entity;
use Google\Cloud\Datastore\EntityIterator;
use Google\Cloud\Datastore\Query\Query;
use Illuminate\Database\Eloquent\Collection;
use Mahadirz\GoogleDatastore\Connection;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Symfony\Component\Finder\Expression\Regex;

class Builder extends BaseBuilder
{
    /**
     * The database collection.
     *
     * @var \Illuminate\Database\Eloquent\Collection
     */
    protected $collection;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    public $bindings = [
        'select' => [],
        'join'   => [],
        'where'  => [],
        'having' => [],
        'order'  => [],
        'union'  => [],
    ];

    /**
     * @var array
     */
    public $excludeFromIndexes = [

    ];

    /**
     * Indicate if we are executing a pagination query.
     *
     * @var bool
     */
    public $paginating = false;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=',
        '<',
        '>',
        '<=',
        '>=',
        '<>',
        '!=',
        ];

    /**
     * Check if we need to return Collections instead of plain arrays (laravel >= 5.3 )
     *
     * @var boolean
     */
    protected $useCollections;

    /**
     * //TODO move this outside Builder
     * The datastore client.
     *
     * @var array
     */
    protected $dataStoreClient;

    /**
     * @var Query
     */
    protected $dataStoreQuery;


    /**
     * The cursor timeout value.
     *
     * @var int
     */
    public $timeout;
    /**
     * The cursor hint value.
     *
     * @var int
     */
    public $hint;
    /**
     * Custom options to add to the query.
     *
     * @var array
     */
    public $options = [];


    /**
     * A Builder object.
     *
     * @param Connection                                $connection
     * @param \Mahadirz\GoogleDatastore\Query\Processor $processor
     */
    public function __construct(Connection $connection, Processor $processor)
    {
        $this->grammar = new Grammar();
        $this->connection = $connection;
        $this->processor = $processor;
        $this->useCollections = $this->shouldUseCollections();
        $this->dataStoreClient = $this->getConnection()->getConnection();
    }

    /**
     * Returns true if Laravel or Lumen >= 5.3
     *
     * @return bool
     */
    protected function shouldUseCollections()
    {
        if (function_exists('app')) {
            $version = app()->version();
            $version = filter_var(explode(')', $version)[0], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // lumen
            return version_compare($version, '5.3', '>=');
        }
    }

    /**
     * Set the projections.
     *
     * @param  array $columns
     * @return $this
     */
    public function project($columns)
    {
        $this->projections = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    /**
     * Set the cursor timeout in seconds.
     *
     * @param  int $seconds
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function find($id, $columns = [])
    {
        return $this->where("id", '=', $id)->first($columns);
    }

    /**
     * @inheritdoc
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $params = func_get_args();
        // Remove the leading $ from operators.
        if (func_num_args() == 3) {
            $operator = &$params[1];
            if (starts_with($operator, '$')) {
                $operator = substr($operator, 1);
            }
        }
        return call_user_func_array('parent::where', $params);
    }


    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     *
     * @return array|static[]
     */
    public function get($columns = [])
    {
        $this->dataStoreQuery = $this->dataStoreClient->query();
        $this->dataStoreQuery->kind($this->from);
        return $this->getFresh($columns);
    }

    /**
     * Execute the query as a fresh "select" statement.
     *
     * @param  array $columns
     * @return array|static[]|Collection
     */
    public function getFresh($columns = [])
    {
        // If no columns have been specified for the select statement, we will set them
        // here to either the passed columns, or the standard default of retrieving
        // all of the columns on the table using the "wildcard" column character.
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }
        // Compile wheres
        $this->wheres = $this->compileWheres();

            $columns = [];
            // Convert select columns to simple projections.
            foreach ($this->columns as $column) {
                $columns[$column] = true;
            }
        $entities = $this->runSelect();
        $results = array();
        foreach ($entities as $i=>$entity){
            /* @var Entity $entity */
            $results[$i] = $entity->get();
        }
        // Return results as an array with numeric keys
        //$results = iterator_to_array($cursor, false);
        return $this->useCollections ? new Collection($results) : $results;
    }

    /**
     * @inheritdoc
     */
    public function insert(array $values)
    {
        //TODO
    }

    /**
     * @inheritdoc
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $key = $this->dataStoreClient->key($this->from);
        $keyWithAllocatedId = $this->dataStoreClient->allocateId($key);
        $id = $keyWithAllocatedId->pathEnd()['id'];
        $entity = $this->dataStoreClient->entity(
            $key,
            $values,
            array('excludeFromIndexes' => $this->excludeFromIndexes)
        );
        $this->dataStoreClient->insert($entity);
        return $id;
    }


    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return EntityIterator
     */
    protected function runSelect()
    {
        $query = $this->dataStoreClient->gqlQuery($this->toGql(), [
            'allowLiterals' => true
        ]);
        $entities = $this->dataStoreClient->runQuery($query);
        return $entities;
    }

    /**
     * Compile the where array.
     *
     * @return array
     */
    protected function compileWheres()
    {
        // The wheres to compile.
        $wheres = $this->wheres ?: [];
        // We will add all compiled wheres to this array.
        foreach ($wheres as $i => &$where) {
            // Make sure the operator is in lowercase.
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);
                // Operator conversions
                $convert = [
                    ''
                ];
                if (array_key_exists($where['operator'], $convert)) {
                    $where['operator'] = $convert[$where['operator']];
                }
            }

            if($where['column'] == 'id'){
                //filter on the value of an entity's key
                $where['column'] = '__key__';
                $where['value'] = sprintf('KEY(%s, %s)',$this->from,$where['value']);
                //$this->dataStoreQuery->filter('__key__', $where['operator'], $this->dataStoreClient->key($this->from, $where['column']));
            }

            // Merge the compiled where with the others.
            //$compiled = array_merge_recursive($compiled, $result);
            //$this->dataStoreQuery->filter($where['column'],$where['operator'],$where['value']);
        }
        return $wheres;
    }



    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toGql()
    {
        return $this->grammar->compileSelect($this);
    }
}
