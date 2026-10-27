<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

// Lets phpstan-symfony resolve the console commands and their arguments.
require dirname(__DIR__, 2).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

return new Application(new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']));
