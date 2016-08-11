<?php

require_once 'bootstrap.php';

// Laravel DB model
//require_once 'database/models/datamodel.php';

// views folder
$app['config']['view.paths'] = [__DIR__.'/views/'];
// eloquent
$app['config']['migrations.path'] = __DIR__.'/database/migrations/';
// Init Database Connection
$app->setupConnection();

// Laravel DB class
// $DB = $app['DB'];

$render = array();

echo View::make('blade_template', $render)->render();

?>