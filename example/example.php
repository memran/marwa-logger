<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Marwa\Logger\Logger;

//Boot logger (env auto-detected from APP_ENV or defaults to dev)
$logger = Logger::boot('my-app','dev');

// Write logs
$logger->info("Application started", ['env' => getenv('APP_ENV')]);
$logger->error("Critical failure", ['exception' => 'RuntimeException']);

// Or use init() alias
// $logger = Logger::init('payments', 'production');
// $logger->warning("Payment delayed", ['user_id' => 42]);
// $logger->debug("Debug Payment Delayed", ['user_id' => 42]);
// $logger->info("Info: Payment is delaying", ['user_id' => 42]);
// $logger->error("Error: Payment delayed", ['user_id' => 42]);

die('Logging initialized. Check the logs in ' );