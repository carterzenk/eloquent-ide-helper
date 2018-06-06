<?php
namespace CarterZenk\EloquentIdeHelper\Command;

use CarterZenk\EloquentIdeHelper\Config\ConfigInterface;
use CarterZenk\EloquentIdeHelper\Config\Loader\JsonLoader;
use CarterZenk\EloquentIdeHelper\Config\Loader\LoaderInterface;
use CarterZenk\EloquentIdeHelper\Config\Loader\LoaderManager;
use CarterZenk\EloquentIdeHelper\Config\Loader\PhpLoader;
use CarterZenk\EloquentIdeHelper\Config\Loader\YamlLoader;
use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var StyleInterface
     */
    protected $io;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $defaultConfigFile = 'eloquent-ide-helper';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load');
    }

    /**
     * Bootstrap the config file and eloquent.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        if (!$this->getLoader()) {
            $loader = new LoaderManager([
                new JsonLoader(),
                new PhpLoader()
            ]);

            if (class_exists('Symfony\Component\Yaml\Yaml')) {
                $loader->addLoader(new YamlLoader());
            }

            $this->setLoader($loader);
        }

        if (!$this->getConfig()) {
            $this->loadConfig($input, $output);
        }

        $manager = new Manager();
        $manager->addConnection($this->config->getConnection());
        $manager->setAsGlobal();
        $manager->bootEloquent();
    }

    /**
     * Sets the config.
     *
     * @param  ConfigInterface $config
     * @return AbstractCommand
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the config.
     *
     * @return ConfigInterface
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the loader.
     *
     * @param LoaderInterface $loader
     * @return $this
     */
    public function setLoader(LoaderInterface $loader)
    {
        $this->loader = $loader;
        return $this;
    }

    /**
     * Gets the loader.
     *
     * @return LoaderInterface
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Load the config file.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return void
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $configFile = $input->getOption('configuration');

        $configFileInfo = $configFile
            ? $this->locateConfigFromPath($configFile)
            : $this->locateConfigFromDirectory();

        $this->io->text('Using config file at '.$configFileInfo->getRealPath());

        $config = $this->loader->load($configFileInfo);

        $this->setConfig($config);
    }

    /**
     * Locates a config file from a given path.
     *
     * @param string $filePath
     * @return \SplFileInfo
     */
    protected function locateConfigFromPath($filePath)
    {
        // Try to find file at absolute path first.
        $fileInfo = new \SplFileInfo($filePath);

        if ($this->loader->supports($fileInfo)) {
            return $fileInfo;
        }

        $fileInfo = new \SplFileInfo(getcwd().DIRECTORY_SEPARATOR.$filePath);

        if ($this->loader->supports($fileInfo)) {
            return $fileInfo;
        }

        throw new \RuntimeException('File at '.$filePath.' is not supported');
    }

    /**
     * Searches for a compatible config file in current working directory.
     *
     * @return \DirectoryIterator
     */
    protected function locateConfigFromDirectory()
    {
        $directoryIterator = new \DirectoryIterator(getcwd());

        foreach ($directoryIterator as $fileInfo) {
            $baseName = $fileInfo->getBasename('.'.$fileInfo->getExtension());

            if ($baseName !== $this->defaultConfigFile) {
                continue;
            }

            if ($this->loader->supports($fileInfo)) {
                return $fileInfo;
            }
        }

        throw new \RuntimeException('No supported configuration files found.');
    }
}