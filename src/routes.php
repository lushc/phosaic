<?php

$app->get('/', controller('phosaic/index'))
    ->bind('homepage');

$app->get('/api/test', controller('api/test'))
    ->bind('api_test');

/**
* Helper function to use shorthand to reference our controllers.
*/
function controller($shortName)
{
    list($shortClass, $shortMethod) = explode('/', $shortName, 2);
    return sprintf('Lushc\Phosaic\Controller\%sController::%sAction', ucfirst($shortClass), $shortMethod);
}