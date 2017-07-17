<?php

namespace Mahadirz\GoogleDatastore;

use Mahadirz\GoogleDatastore\Query\Grammar as Grammar;
use Google\Cloud\Datastore\DatastoreClient;
use Illuminate\Database\Connection as BaseConnection;


class Connection extends BaseConnection
{
    /**
     * @var \Google\Cloud\Datastore\DatastoreClient
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {

        //Set the config
        $this->config = $config;
        // Create the connection
        $this->connection = $this->createConnection();

        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();
    }


    /**
     * @param type $table
     *
     * @return Query\Builder
     */
    public function table($table)
    {
        return $this->query()->from($table);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Mahadirz\GoogleDatastore\Query\Builder
     */
    public function query()
    {
        return new Query\Builder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * @param type $kind
     *
     * @return type
     */
    public function kind($kind)
    {
        return $this->table($kind);
    }

    /**
     * @return \Google\Cloud\Datastore\DatastoreClient
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Grammar();
    }

    /**
     * Get the default post processor instance.
     *
     * @return Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }


    /**
     * Create a new Datastore connection.
     *
     * @return \Google\Cloud\Datastore\DatastoreClient
     */
    protected function createConnection()
    {
        $client = new DatastoreClient(array(
            'keyFilePath' =>  app_path('../').$this->config['keyFilePath'],
        ));

        return $client;
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this, $method], $parameters);
    }
}
