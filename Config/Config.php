<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable("./");
$dotenv->load();
define("HOST", $_ENV['HOST']);
define("USER", $_ENV['USERDB']);
define("PASSWORD", $_ENV['PASSWORD']);
define("DB", $_ENV['DB']);
define("CHARSET", $_ENV['CHARSET']);

define("HOSTMARKET", $_ENV['HOSTMARKET']);
define("USERMARKET", $_ENV['USERMARKET']);
define("PASSWORDMARKET", $_ENV['PASSWORDMARKET']);
define("DBMARKET", $_ENV['DBMARKET']);
define("CHARSETMARKET", $_ENV['CHARSETMARKET']);
