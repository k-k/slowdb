<?php

namespace SlowDB;

class Driver
{
    /**
     * The driver
     *
     * @var string
     */
    const CLIENT = 'php-library';

    /**
     * A socket connection to the Database
     *
     * @var resource
     */
    private $connection;

    /**
     * The collection name
     *
     * @var string
     */
    private $collection;

    /**
     * Constructor
     *
     * @param string $host The database host
     * @param string $port The database port
     */
    public function __construct($host, $port)
    {
        $this->connection = $this->buildConnection($host, $port);
    }

    /**
     * Deconstructor
     *
     * Ensures that we close the connection to the Database
     */
    public function __destruct()
    {
        fclose($this->connection);
    }

    /**
     * Magic method that sets the collection name for a Command
     *
     * @param  string $name The collection name
     *
     * @return self
     */
    public function __get($name)
    {
        $this->collection = $name;

        return $this;
    }

    /**
     * Sends Commands to the Database Server
     *
     * @param  string $method    The method/command to call
     * @param  array  $arguments An array of arguments for the command
     *
     * @return mixed
     */
    public function __call($method, array $arguments = [])
    {
        $command = [
            'client'    => self::CLIENT,
            'method'    => $method,
            'arguments' => $arguments
        ];

        if (isset($this->collection)) {
            $command['collection'] = $this->collection;
            $this->collection = null;
        }

        fwrite($this->connection, json_encode($command));
        $response = stream_get_contents($this->connection);

        return json_decode($response, true);
    }

    /**
     * Builds and tests the connection to the Database
     *
     * @param  string   $host The database host
     * @param  string   $port The database port
     *
     * @return resource
     */
    private function buildConnection($host, $port)
    {
        $connection = stream_socket_client("tcp://{$host}:{$port}", $errno, $message);
        $success    = fread($connection, 26);

        if ($connection === false || !$success) {
            throw new \UnexpectedValueException("Failed to connect: $message");
        }

        return $connection;
    }
}
