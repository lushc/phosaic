<?php

$app->get('/', controller('phosaic/index'))
    ->bind('homepage');

$app->post('/api/mosaic', controller('api/createMosaic'))
    ->bind('api_create_mosaic');

$app->get('/api/mosaic/{id}.{format}', controller('api/getMosaic'))
    ->value('format', 'json')
    ->bind('api_get_mosaic');

/**
* Helper function to use shorthand to reference our controllers.
*/
function controller($shortName)
{
    list($shortClass, $shortMethod) = explode('/', $shortName, 2);
    return sprintf('Lushc\Phosaic\Controller\%sController::%sAction', ucfirst($shortClass), $shortMethod);
}