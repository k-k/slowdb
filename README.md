SlowDB
======

![SlowDB](slowdb.jpg)

When you don't want Redis, Memcached, or any other Key/Value store.

SlowDB is the Key/Value store written purely in PHP that'd you be upset to find your
co-worker running in production.

### Features:

  - Multiple named Collections
  - In-memory indexes
  - Safe persistence to disk

SlowDB allows for storing Key/Value pairs in multiple collections. Indexes are
built/rebuilt on startup to map Keys to file locations on disk. This allows
performant binary searches across database files and writing directly to disk
for safe, consistent writes.

### Installation

Clone the repository locally and run [composer](https://getcomposer.org/download/) install:

    $> git clone https://github.com/kmfk/slowdb
    $> cd slowdb/
    $> php composer.phar install

### Usage

Technically, SlowDB can be instantiated as a service in your application.

However, when SlowDB is used as a service, the Database needs to be instantiated and the
indexes built on every request. On small datasets, this should be negligible -
while large datasets, this can add unwanted latency to requests.

The best way to use SlowDB is by using the included socket server
(built on [ReactPHP](https://github.com/reactphp/socket)) and the [driver](src/Driver.php).

While only single threaded, this will keep the database indexes in memory and provide better
performance.

    $> ./slowdb &

### Basic Example

Once the server is running, you can use it like this:

```php
    <?php

    require 'vendor/autoload.php';

    use SlowDB\Driver;

    $driver = new Driver('localhost', 1337);

    $driver->test->set('abc', ['foo' => 'bar']);

    $value = $driver->test->get('abc');

    print_r($value);
```
