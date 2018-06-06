<?php
namespace CarterZenk\EloquentIdeHelper\Config\Loader;

use CarterZenk\EloquentIdeHelper\Config\Config;

class JsonLoader implements LoaderInterface
{
    /**
     * @inheritdoc
     */
    public function supports(\SplFileInfo $fileInfo)
    {
        return $fileInfo->getExtension() === 'json';
    }

    /**
     * @inheritdoc
     */
    public function load(\SplFileInfo $fileInfo)
    {
        $path = $fileInfo->getRealPath();
        $configArray = json_decode(file_get_contents($path), true);

        if (!is_array($configArray)) {
            throw new \RuntimeException('File '.$path.' must be valid JSON.');
        }

        return new Config($configArray, $path);
    }
}