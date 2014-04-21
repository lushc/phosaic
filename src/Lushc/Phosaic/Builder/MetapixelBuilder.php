<?php

namespace Lushc\Phosaic\Builder;

use Symfony\Component\Process\Process;

class MetapixelBuilder
{
    private $library_path;

    public function __construct($options = array())
    {
        $options = array_merge(array(
            'library_path' => __DIR__.'/../../../../var/cache/phosaic/metapixel'
        ), $options);

        $this->library_path = $options['library_path'];
    }

    public function generateMosaic($input_path, $libraries = array())
    {
        if (empty($libraries)) {
            throw new \RuntimeException('At least one metapixel library needs to be used.');
        }

        $opts = '';

        foreach ($libraries as $library) {
            $path = $this->library_path . '/' . $library;
            $opts .= "--library $path ";
        }

        $opts = rtrim($opts);
        $path_parts = pathinfo($input_path);
        $output_path = $path_parts['dirname'] . '/' . uniqid() . '_phosaic.png';
        $scale = 4;

        $process = new Process("metapixel $opts --metapixel $input_path $output_path --scale=$scale");
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $im = new \Imagick($output_path);
        $im->resizeImage(floor($im->getImageWidth() / $scale), floor($im->getImageHeight() / $scale), \Imagick::FILTER_LANCZOS, 1);
        $im->setImageFormat('jpg');
        $im->setCompressionQuality(90);
        $im->writeImage(preg_replace('/\.png$/', '.jpg', $output_path));
    }

    public function prepareLibrary($library_name, $images)
    {
        $dir = $this->library_path . '/' . $library_name;

        if (!is_file("$dir/tables.mxt")) {

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