<?php

define( 'TEAM51_CLI_ROOT_DIR', __DIR__ );

require __DIR__ . '/src/helpers/config-loader.php';
require __DIR__ . '/vendor/autoload.php';

echo ASCII_WELCOME_ART . PHP_EOL;

use Symfony\Component\Console\Application;

$application = new Application();

$application->add( new Team51\Command\Create_Production_Site() );
$application->add( new Team51\Command\Create_Development_Site() );
$application->add( new Team51\Command\Create_Repository() );

$application->run();
