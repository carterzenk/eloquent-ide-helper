<?php
namespace CarterZenk\EloquentIdeHelper\Config\Loader;

use CarterZenk\EloquentIdeHelper\Config\Config;

interface LoaderInterface
{
    /**
     * Should return an array of extensions supported by the parser.
     *
     * @param \SplFileInfo $fileInfo
     * @return bool
     */
    public function supports(\SplFileInfo $fileInfo);

    /**
     * Parse the config file path to return a Config instance.
     *
     * @param \SplFileInfo
     * @return Config
     */
    public function load(\SplFileInfo $fileInfo);
}