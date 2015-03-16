<?php

namespace SlowDB;

/**
 * Driver Class
 *
 * @author Keith Kirk <keith@kmfk.io>
 */
class Driver
{
    /**
     * The driver
     *
     * @var string
     */
    const CLIENT = 'php-library';

    /**
     * Database server host
     *
     * @var string
     */
    private $host;

    /**
     * Database server port
     *
     * @var string
     */
    private $port;

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
        $this->host = $host;
        $this->port = $port;
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
        $connection = $this->buildConnection();

        $command = [
            'client'    => self::CLIENT,
            'method'    => $method,
            'arguments' => $arguments
        ];

        if (isset($this->collection)) {
            $command['collection'] = $this->collection;
            $this->collection = null;
        }

        fwrite($connection, json_encode($command));
        $response = stream_get_contents($connection);

        fclose($connection);

        return json_decode($response, true);
    }

    /**
     * Builds and tests the connection to the Database
     *
     * @return resource
     */
    private function buildConnection()
    {
        $connection = stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $message);
        $success    = fread($connection, 26);

        if (false === $connection || false === $success) {
            throw new \UnexpectedValueException("Failed to connect: $message");
        }

        return $connection;
    }
}
