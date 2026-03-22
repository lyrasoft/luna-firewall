<?php

declare(strict_types=1);

namespace App\Routes;

use Windwalker\Core\Router\RouteCreator;

/** @var RouteCreator $router */

$router->group('honeypot')
    ->register(function (RouteCreator $router) {
        $router->any('honeypot', env('HONEYPOT_ROUTE_ENDPOINT') ?: '/total/product/search')
            //->controller()
            //->view();
            ;
    });
