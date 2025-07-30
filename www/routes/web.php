<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->get('/', ['as'=> 'index', 'uses'=>'MainController@getIndex']);
$router->get('/iframe', ['as'=> 'index', 'uses'=>'MainController@getIframe']);
//$router->get('/scraper', ['as'=> 'index', 'uses'=>'ScraperController@scrapeExample']);

