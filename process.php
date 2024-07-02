<?php

require 'vendor/autoload.php';

use src\BinChecker;

$binChecker = new BinChecker();
$binChecker->processTransactions($argv[1]);
