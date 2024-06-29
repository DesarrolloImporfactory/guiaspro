<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable("./");
$dotenv->load();
print_r($_ENV);
define("HOST", $_ENV['HOST']);
define("USER", $_ENV['USER']);
define("PASSWORD", $_ENV['PASSWORD']);
define("DB", $_ENV['DB']);
define("CHARSET", $_ENV['CHARSET']);
