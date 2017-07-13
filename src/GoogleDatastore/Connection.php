<?php

namespace Mahadirz\GoogleDatastore;

use Mahadirz\GoogleDatastore\Query\Grammar as Grammar;

class Connection extends \Illuminate\Database\Connection
{
    /**
     * @var type
     */
    protected $googleClient;

    /**
     * @var type
     */
    protected $googleGateway;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {

        //Set the config
        $this->config = $config;
        dd($this->config);

        // Create the connection
        $this->connection = $this->createConnection();

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        //Use the default post processor.
        $this->useDefaultPostProcessor();
    }

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName()
    {
        return 'gdatastore';
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
     * @return \GoogleDatastore\Query\Builder
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
     * Get the gateway used for connecting to Google.
     *
     * @return \GDS\Gateway\GoogleAPIClient
     */
    public function getGoogleGateway()
    {
        return $this->googleGateway;
    }

    /**
     * Create a new MongoDB connection.
     *
     * @return \GDS\Gateway\GoogleAPIClient
     */
    protected function createConnection()
    {

        // We'll need a Google_Client, use our convenience method
        $this->googleClient = \GDS\Gateway\GoogleAPIClient::createGoogleClient($this->config['appname'], $this->config['service_email'], base_path().'/resources/assets/'.$this->config['key_file']);

        //THE GATEWAY TO USE
        $this->googleGateway = new \GDS\Gateway\GoogleAPIClient($this->googleClient, $this->config['project_id']);

        return $this->googleClient;
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
        return call_user_func_array([$this->db, $method], $parameters);
    }
}
