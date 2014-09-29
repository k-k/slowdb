<?php

require 'vendor/autoload.php';

use SlowDB\Driver;

$driver = new Driver('localhost', 1337);

ld($driver->test->get('abc'));//, ['some' => 'value']));


