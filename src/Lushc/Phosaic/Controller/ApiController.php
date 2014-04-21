<?php

namespace Lushc\Phosaic\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class ApiController
{
    public function testAction(Request $request, Application $app)
    {
        $dates = array('2014-04-18','2014-04-17','2014-04-16','2014-04-15','2014-04-14','2014-04-13','2014-04-12');

        $requested_date = '2014-04-19';
        if (empty($requested_date)) {
            // default to yesterday
            $requested_date = date('c', strtotime('-1 day'));
        }

        if (!$app['repository.images']->hasImages($requested_date)) {
            $photos = $app['flickr']->getInterestingPhotos();
            $app['repository.images']->populate($photos);
        }

        $library = date('Y-m-d', strtotime($requested_date));
        $app['builder.metapixel']->prepareLibrary($library, $app['repository.images']->getImages($requested_date));
        $app['builder.metapixel']->generateMosaic(__DIR__.'/../../../../var/cache/phosaic/test5.jpg', $dates);
        
        return $app->json();
    }
}
