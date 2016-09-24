<?php

// This is an example of how easy it is to run an instance of a JobManager.
require('_includes.php');


// Running with no custom JobManager extension
$manager = new JobManager('/path/to/logfile');
$manager->connect('host', 'user', 'password', 'database');
$manager->run();

// Running with a customer JobManager extension
$manager = new ExampleJobManager();
$manager->run();

?>