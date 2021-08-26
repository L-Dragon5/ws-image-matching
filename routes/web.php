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

$router->get('/', function () {
    return 'Hello World!';
});

$router->group(['middleware' => 'auth.basic'], function () use ($router) {
    $router->get('/retrieveCardList', 'DataScraperController@retrieveCardList');
    $router->get('/retrieveCardData', 'DataScraperController@retrieveCardData');
    $router->get('/retrieveYYTPrices', 'DataScraperController@retrieveYYTPrices');
    $router->get('/retrieveCardTranslations', 'DataScraperController@retrieveCardTranslations');

    $router->get('/updateImageIndex', 'PastecIndexController@updateImageIndex');
});

$router->get('/pingServer', 'CardSearchController@pingServer');
$router->post('/searchByImage', 'CardSearchController@searchByImage');
