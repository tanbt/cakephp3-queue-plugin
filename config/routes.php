<?php
use Cake\Routing\Router;

Router::plugin('CakeQueue', function ($routes) {
    $routes->fallbacks('InflectedRoute');
});
