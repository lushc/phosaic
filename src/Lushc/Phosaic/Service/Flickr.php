<?php

namespace Lushc\Phosaic\Service;

use Rezzza\Flickr\Metadata;
use Rezzza\Flickr\ApiFactory;
use Rezzza\Flickr\Http\GuzzleAdapter;

class Flickr
{
    private $api;

    public function __construct($options = array())
    {
        $options = array_merge(array(
            'api_key' => '',
            'api_secret' => '',
            'oauth_token' => '',
            'oauth_secret' => ''
        ), $options);

        $metadata = new Metadata($options['api_key'], $options['api_secret']);

        if ($options['oauth_token'] && $options['oauth_secret']) {
            $metadata->setOauthAccess($options['oauth_token'], $options['oauth_secret']);
        }

        $this->api = new ApiFactory($metadata, new GuzzleAdapter());
    }

    public function getInterestingPhotos($options = array())
    {
        $options = array_merge(
            array(
                'date' => date('Y-m-d', strtotime('-1 day')), // default to yesterday
                'extras' => 'description,date_taken,owner_name,geo,views,url_sq',
                'page' => 1,
                'per_page' => 500
            )
        , $options);

        $photos = array();

        do {  
            $xml = $this->api->call('flickr.interestingness.getList', $options);

            if ($xml->err) {
                throw new \RuntimeException($xml->err->attributes()->msg);
            }

            foreach ($xml->photos->photo as $photo) {
                $photo->addAttribute('interestingness_date', $options['date']);
                $photos[] = $this->parsePhotoXML($photo);
            }

            $options['page']++;
        }
        while ($options['page'] <= $xml->photos->attributes()->pages);

        return $photos;
    }

    private function parsePhotoXML($element)
    {
        $attr = $element->attributes();

        return array(
            'id' => (string) $attr->id,
            'url' => (string) $attr->url_sq,
            'interestingness_date' => (string) $attr->interestingness_date,
            'metadata' => array(
                'title' => (string) $attr->title,
                'description' => (string) $element->description,
                'latitude' => (string) $attr->latitude,
                'longitude' => (string) $attr->longitude,
                'date_taken' => (string) $attr->datetaken
            )
        );
    }
}