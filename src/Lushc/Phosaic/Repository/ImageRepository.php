<?php

namespace Lushc\Phosaic\Repository;

use Guzzle\Http\Client;

class ImageRepository
{
    private $collection;
    private $cache_path;
    private $client;

    public function __construct($mongodb, $options = array())
    {
        $options = array_merge(array(
            'db' => 'phosaic',
            'cache_path' => '/tmp'
        ), $options);

        $this->cache_path = $options['cache_path'];
        $this->collection = $mongodb->selectDatabase($options['db'])->selectCollection('images');
        $this->collection->ensureIndex(array('interestingness_date' => -1));
        $this->collection->ensureIndex(array('id' => 1), array('dropDups' => true, 'unique' => true));
        $this->client = new Client();
    }

    public function populate($photos = array())
    {
        foreach ($photos as $photo) {
            
            $path = $this->generateFilePath($photo['id']);

            if ($this->writeToCache($photo['url'], $path)) {

                $photo['file_path'] = $path;
                $this->collection->insert($photo);
            }
        }
    }

    public function hasOneByDate($date = '')
    {
        return null !== $this->collection->findOne(array('interestingness_date' => $date));
    }

    public function findByDate($date = '')
    {
        return $this->collection->find(array('interestingness_date' => $date));
    }

    public function findByDateRange($start_date = '', $end_date = '')
    {
        return $this->collection->find(array('interestingness_date' => array('$gte' => $start_date, '$lte' => $end_date)));
    }

    public function generateFilePath($photo_id)
    {
        $md5 = md5($photo_id);
        $dir = $this->cache_path . '/' . substr($md5, 0, 2) . '/' . substr($md5, 2, 2);
        $path = $dir . '/' . $photo_id . '.jpg';

        return $path;
    }

    private function writeToCache($file, $path)
    {
        $path_parts = pathinfo($path);
        $dir = $path_parts['dirname'];

        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf("Unable to create the cache directory (%s).", $dir));
            }
        }
        elseif (!is_writable($dir)) {
            throw new \RuntimeException(sprintf("Unable to write in the cache directory (%s).", $dir));
        }

        if (filter_var($file, FILTER_VALIDATE_URL)) {
            try {
                $response = $this->client->get($file, array(), array('save_to' => $path))->send();
            }
            catch (\Exception $e) {
                throw new \RuntimeException(sprintf("Unable to download file (%s) when generating the cache.", $file));
            }
        }
        elseif (is_uploaded_file($file)) {
            move_uploaded_file($file, $path);
        }
        else {
            copy($file, $path);
        }

        return is_file($path);
    }
}