<?php

namespace Lushc\Phosaic\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class PhosaicController
{
    public function indexAction(Request $request, Application $app)
    {
        return $app['twig']->render('index.html.twig', array());
    }
}
