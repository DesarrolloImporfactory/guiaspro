<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable("./");
$dotenv->load();
define("HOST", $_ENV['HOST']);
define("USER", $_ENV['USERDB']);
define("PASSWORD", $_ENV['PASSWORD']);
define("DB", $_ENV['DB']);
define("CHARSET", $_ENV['CHARSET']);
