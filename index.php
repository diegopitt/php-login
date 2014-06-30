<?php

require 'application/config/config.php';

require 'application/config/autoload.php';

if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
}

$app = new Application();
