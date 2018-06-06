<?php
namespace CarterZenk\EloquentIdeHelper\Config\Loader;

class LoaderManager implements LoaderInterface
{
    /**
     * @var LoaderInterface[]
     */
    protected $loaders = [];

    /**
     * LoaderManager constructor.
     * @param LoaderInterface[] $loaders
     */
    public function __construct(array $loaders = [])
    {
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    /**
     * @param LoaderInterface $loader
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * @inheritdoc
     */
    public function supports(\SplFileInfo $fileInfo)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($fileInfo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function load(\SplFileInfo $fileInfo)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($fileInfo)) {
                return $loader->load($fileInfo);
            }
        }

        throw new \RuntimeException('File '.$fileInfo->getRealPath().' is not supported.');
    }
}