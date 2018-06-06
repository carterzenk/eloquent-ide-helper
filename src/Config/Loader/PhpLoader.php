<?php
namespace CarterZenk\EloquentIdeHelper\Config\Loader;

use CarterZenk\EloquentIdeHelper\Config\Config;

class PhpLoader implements LoaderInterface
{
    /**
     * @inheritdoc
     */
    public function supports(\SplFileInfo $fileInfo)
    {
        return $fileInfo->getExtension() === 'php';
    }

    /**
     * @inheritdoc
     */
    public function load(\SplFileInfo $fileInfo)
    {
        $path = $fileInfo->getRealPath();

        ob_start();
        $configArray = include($path);
        ob_end_clean();

        if (!is_array($configArray)) {
            throw new \RuntimeException('File '.$path.' must return an array.');
        }

        return new Config($configArray, $path);
    }
}