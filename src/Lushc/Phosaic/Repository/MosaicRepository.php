<?php

namespace Lushc\Phosaic\Repository;

class MosaicRepository
{
    private $grid;

    public function __construct($mongodb, $options = array())
    {
        $options = array_merge(array(
            'db' => 'phosaic',
        ), $options);

        $this->grid = $mongodb->selectDatabase($options['db'])->getGridFS();
    }

    public function storeMosaic($file, $metadata = array())
    {
        $this->grid->storeFile($file, $metadata);

        // cast the MongoId object to get the actual ID string
        return (string) $metadata['_id'];
    }

    public function findOneById($id)
    {
        return $this->grid->findOne(array('_id' => new \MongoId($id)));
    }
}