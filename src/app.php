<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Igorw\Silex\ConfigServiceProvider;
use Saxulum\DoctrineMongoDb\Silex\Provider\DoctrineMongoDbProvider;
use Lushc\Phosaic\Builder\MetapixelBuilder;
use Lushc\Phosaic\Repository\ImageRepository;
use Lushc\Phosaic\Repository\MosaicRepository;
use Lushc\Phosaic\Service\Flickr;

$env = getenv('APP_ENV') ?: 'prod';

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider(), array(
    'twig.path' => array(__DIR__.'/../templates'),
    'twig.options' => array('cache' => __DIR__.'/../var/cache/twig')
));
$app->register(new ConfigServiceProvider(__DIR__."/../config/$env.yml"));
$app->register(new DoctrineMongoDbProvider(), array(
    'mongodb.options' => array(
        'server' => $app['config']['mongodb']['server'],
        'options' => array(
            'username' => $app['config']['mongodb']['username'],
            'password' => $app['config']['mongodb']['password'],
            'db' => $app['config']['mongodb']['db']
        )
    )
));

if ($app['debug']) {
    $app->register(new MonologServiceProvider(), array(
        'monolog.logfile' => __DIR__.'/../var/logs/silex_dev.log',
    ));
    $app->register($p = new WebProfilerServiceProvider(), array(
        'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
    ));
    $app->mount('/_profiler', $p);
}

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    // add custom globals, filters, tags, ...
    return $twig;
}));

// Register Flickr API service.
$app['flickr'] = $app->share(function ($app) {
    return new Flickr($app['config']['flickr']);
});

$app['repository.images'] = $app->share(function ($app) {
    return new ImageRepository($app['mongodb'], array(
        'db' => $app['config']['mongodb']['db'],
        'cache_path' => __DIR__.'/../var/cache/phosaic/images'
    ));
});

$app['repository.mosaics'] = $app->share(function ($app) {
    return new MosaicRepository($app['mongodb'], array(
        'db' => $app['config']['mongodb']['db']
    ));
});

$app['builder.metapixel'] = $app->share(function ($app) {
    return new MetapixelBuilder($app['repository.mosaics'], array(
        'library_path' => __DIR__.'/../var/cache/phosaic/metapixel'
    ));
});

// Middleware to handle JSON request bodies.
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(400, 'The JSON received was malformed.');
        }
        $request->request->replace(is_array($data) ? $data : array());
    }
});

// Register the error handler.
$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // respond with JSON 
    if (0 === strpos($app['request']->headers->get('content-type'), 'application/json')) {
        $response = array(
            'status' => 'error',
            'httpCode' => $code,
            'message' => $e->getMessage()
        );
        return new JsonResponse($response, $code);
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});

return $app;
