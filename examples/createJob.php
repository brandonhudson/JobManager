<?php

// This is an example of how to create a new job from anywhere in your codebase.
require('_includes.php');


// Running with no custom JobManager extension
$manager = new JobManager('/path/to/logfile');
$manager->connect('host', 'user', 'password', 'database');
$manager->create();

// Create a basic job with default status to run immediately
$manager->create('ClassName', ['key' => 'value']);

// Create a basic job with custom status
$manager->create('ClassName', ['key' => 'value'], 'CUSTOMSTATUS');

// Create a job with custom status and schedule time
$manager->create('ClassName', ['key' => 'value'], 'CUSTOMSTATUS', time() + 1200);



// ------------------------------------------------------------



// Running with a customer JobManager extension
$manager = new ExampleJobManager();

// Create a basic job with default status to run immediately
$manager->create('ClassName', ['key' => 'value']);

// Create a basic job with custom status
$manager->create('ClassName', ['key' => 'value'], 'CUSTOMSTATUS');

// Create a job with custom status and schedule time
$manager->create('ClassName', ['key' => 'value'], 'CUSTOMSTATUS', time() + 1200);

?>