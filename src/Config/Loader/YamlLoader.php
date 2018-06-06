<?php
namespace CarterZenk\EloquentIdeHelper\Config\Loader;

use CarterZenk\EloquentIdeHelper\Config\Config;
use Symfony\Component\Yaml\Yaml;

class YamlLoader implements LoaderInterface
{
    /**
     * @inheritdoc
     */
    public function supports(\SplFileInfo $fileInfo)
    {
        return $fileInfo->getExtension() === 'yml';
    }

    /**
     * @inheritdoc
     */
    public function load(\SplFileInfo $fileInfo)
    {
        $path = $fileInfo->getRealPath();
        $configArray = Yaml::parse(file_get_contents($path));

        if (!is_array($configArray)) {
            throw new \RuntimeException('File '.$path.' must be valid YAML.');
        }

        return new Config($configArray, $path);
    }
}