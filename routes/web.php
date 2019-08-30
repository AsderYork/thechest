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


$router->get('/', 'Ajax_interface_controller@index');
$router->post('/ajax/lobby', 'Ajax_interface_controller@lobby');
$router->post('/ajax/login', 'Ajax_interface_controller@login');
$router->post('/ajax/new_session', 'Ajax_interface_controller@new_session');
$router->post('/ajax/connect_to', 'Ajax_interface_controller@connect_to');
$router->post('/ajax/ready_table', 'Ajax_interface_controller@ready_table');
$router->post('/ajax/set_ready', 'Ajax_interface_controller@set_ready');
$router->post('/ajax/game', 'Ajax_interface_controller@game');
$router->post('/ajax/action', 'Ajax_interface_controller@action');
$router->post('/ajax/next_level', 'Ajax_interface_controller@next_level');
$router->post('/ajax/end_turn', 'Ajax_interface_controller@end_turn');


