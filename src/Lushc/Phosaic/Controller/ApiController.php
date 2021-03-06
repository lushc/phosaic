<?php

namespace Lushc\Phosaic\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController
{
    public function createMosaicAction(Request $request, Application $app)
    {
        $file = $request->files->get('upload');

        if ($file === null) {
            $app->abort(400, 'Please upload a file.');
        }

        if (!$file->isValid()) {
            $app->abort(400, 'There was an error while uploading the file.');
        }

        if (!exif_imagetype($file)) {
            unlink($file);
            $app->abort(400, 'The file uploaded is not an image.');
        }

        $upload_dir = __DIR__.'/../../../../var/cache/phosaic/uploads';
        $upload_filename = sha1(uniqid(mt_rand(), true)).'.'.$file->guessExtension();

        $uploaded_file = $file->move($upload_dir, $upload_filename);

        // dates are provided as comma-delimited values
        $dates = explode(',', $request->request->get('dates'));

        if (empty($dates)) {
            // default to yesterday
            $dates[] = date('Y-m-d', strtotime('-1 day'));
        }

        foreach ($dates as $i => $date) {

            // format to YYYY-MM-DD
            $date_formatted = date('Y-m-d', strtotime($date));

            if (!$app['repository.images']->hasOneByDate($date_formatted)) {

                // get and cache images for this particular date
                $photos = $app['flickr']->getInterestingPhotos(array('date' => $date_formatted));
                $app['repository.images']->populate($photos);
            }

            // make sure the metapixel library is prepared for the day's images
            $app['builder.metapixel']->prepareLibrary($date_formatted, $app['repository.images']->findByDate($date_formatted));

            // now use the dates array as a list of libraries for metapixel to use
            $dates[$i] = $date_formatted;
        }

        // build the mosaic using the request date libraries
        $mosaic_id = $app['builder.metapixel']->generateMosaic($uploaded_file->getRealpath(), $dates);
        
        return $app->json(array(
            'status' => 'success',
            'id' => $mosaic_id,
            'url' => $app['url_generator']->generate('api_get_mosaic', array('id' => $mosaic_id, 'format' => 'jpg'))
        ));
    }

    public function getMosaicAction(Request $request, Application $app)
    {
        $format = $request->attributes->get('format');
        $id = $request->attributes->get('id');

        if (!\MongoId::isValid($id)) {
            $app->abort(400, sprintf('ID "%s" is invalid.', $id));
        }

        $mosaic = $app['repository.mosaics']->findOneById($id);

        if (empty($mosaic)) {
            $app->abort(404, sprintf('Unable to find mosaic with the ID %s.', $id));
        }

        if ($format === 'json') {

            return $app->json(array(
                'status' => 'success',
                'url' => $app['url_generator']->generate('api_get_mosaic', array('id' => $id, 'format' => 'jpg')),
                'upload_date_human' => date('F jS Y, g:i a', $mosaic['uploadDate']->sec),
                'upload_date_unix' => $mosaic['uploadDate']->sec,
                'length' => $mosaic['length'],
                'width' => $mosaic['width'],
                'height' => $mosaic['height'],
                'mimetype' => $mosaic['mimetype']
            ));
        }
        else {

            return new Response($mosaic['file']->getBytes(), 200, array('Content-Type' => $mosaic['mimetype']));
        }
    }
}
