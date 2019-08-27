<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


$router->get('user/{id}', 'Controller@show');


$router->get('lobby', 'Controller@lobby');
$router->get('login', 'Controller@login');
$router->get('index', 'Controller@index');
$router->get('connect_to', 'Controller@connect_to');
$router->get('create_session', 'Controller@create_session');

$router->get('set_ready', 'Controller@set_ready');
$router->get('leave', 'Controller@leave');


$router->get('game', 'Controller@game');

$router->get('test', 'Controller@test');

