<?php

namespace Lushc\Phosaic\Builder;

use Symfony\Component\Process\Process;

class MetapixelBuilder
{
    private $mosaic_repository;
    private $library_path;
    private $scale;

    public function __construct($mosaic_repository, $options = array())
    {
        $options = array_merge(array(
            'library_path' => '/tmp',
            'scale' => 4
        ), $options);

        $this->mosaic_repository = $mosaic_repository;
        $this->library_path = $options['library_path'];
        $this->scale = $options['scale'];
    }

    public function generateMosaic($input_path, $libraries = array())
    {
        if (empty($libraries)) {
            throw new \RuntimeException('At least one metapixel library needs to be used.');
        }

        if (!is_file($input_path)) {
            throw new \RuntimeException(sprintf('%s does not exist or could not be read.', $input_path));
        }

        $opts = '';

        foreach ($libraries as $library) {
            $path = $this->library_path . '/' . $library;
            $opts .= "--library $path ";
        }

        $opts = rtrim($opts);
        $path_parts = pathinfo($input_path);
        $output_path = $path_parts['dirname'] . '/tmp_metapixel_' . uniqid() . '.png';

        $process = new Process("metapixel $opts --metapixel $input_path $output_path --scale=$this->scale");
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $im = new \Imagick($output_path);
        $width = floor($im->getImageWidth() / $this->scale);
        $height = floor($im->getImageHeight() / $this->scale);
        $im->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
        $im->setImageFormat('jpg');
        $im->setCompressionQuality(90);
        $mosaic_path = preg_replace('/\.png$/', '_converted.jpg', $output_path);
        $im->writeImage($mosaic_path);

        $_id = $this->mosaic_repository->storeMosaic($mosaic_path, array(
            'mimetype' => 'image/jpeg',
            'width' => $width,
            'height' => $height
        ));

        unlink($input_path);
        unlink($output_path);
        unlink($mosaic_path);

        return $_id;
    }

    public function prepareLibrary($library_name, $images)
    {
        $dir = $this->library_path . '/' . $library_name;
        $metapixel_table = $dir . '/tables.mxt';

        if (is_file($metapixel_table) && filesize($metapixel_table) <= 0) {
            unlink($metapixel_table);
        }

        if (!is_file($metapixel_table)) {

            if (!is_dir($dir)) {
                if (false === @mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new \RuntimeException(sprintf("Unable to create the metapixel library directory (%s).", $dir));
                }
            }
            elseif (!is_writable($dir)) {
                throw new \RuntimeException(sprintf("Unable to write in the metapixel library directory (%s).", $dir));
            }

            $process = new Process("metapixel --new-library '$dir'");
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

            foreach ($images as $image) {
                $file = $image['file_path'];
                $process = new Process("metapixel --width=75 --height=75 --prepare '$dir' '$file'");
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new \RuntimeException($process->getErrorOutput());
                }
            }
        }
    }
}