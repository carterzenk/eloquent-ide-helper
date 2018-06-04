<?php
namespace CarterZenk\EloquentIdeHelper\Command;

use CarterZenk\EloquentIdeHelper\Config\Config;
use CarterZenk\EloquentIdeHelper\Config\ConfigInterface;
use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load');
    }

    /**
     * Bootstrap the config file.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getConfig()) {
            $this->loadConfig($input, $output);
        }

        $manager = new Manager();
        $connection = $this->config->getConnection();
        $manager->addConnection($connection);
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
     * Parse the config file and load it into the config object
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return void
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $configFilePath = $this->locateConfigFile($input);
        $output->writeln('<info>using config file</info> .' . str_replace(getcwd(), '', realpath($configFilePath)));

        $parser = $input->getOption('parser');

        // If no parser is specified try to determine the correct one from the file extension.  Defaults to YAML
        if (null === $parser) {
            $extension = pathinfo($configFilePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case 'json':
                    $parser = 'json';
                    break;
                case 'php':
                    $parser = 'php';
                    break;
                case 'yml':
                default:
                    $parser = 'yaml';
            }
        }

        switch (strtolower($parser)) {
            case 'json':
                $config = Config::fromJson($configFilePath);
                break;
            case 'php':
                $config = Config::fromPhp($configFilePath);
                break;
            case 'yaml':
                $config = Config::fromYaml($configFilePath);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('\'%s\' is not a valid parser.', $parser));
        }

        $output->writeln('<info>using config parser</info> ' . $parser);

        $this->setConfig($config);
    }

    /**
     * Returns config file path
     *
     * @param InputInterface $input
     * @return string
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function locateConfigFile(InputInterface $input)
    {
        $configFile = $input->getOption('configuration');

        $useDefault = false;

        if (null === $configFile || false === $configFile) {
            $useDefault = true;
        }

        $cwd = getcwd();

        // locate the phinx config file (default: phinx.yml)
        // TODO - In future walk the tree in reverse (max 10 levels)
        $locator = new FileLocator(array(
            $cwd . DIRECTORY_SEPARATOR
        ));

        if (!$useDefault) {
            // Locate() throws an exception if the file does not exist
            return $locator->locate($configFile, $cwd, $first = true);
        }

        $possibleConfigFiles = array('idehelper.php', 'idehelper.json', 'idehelper.yml');
        foreach ($possibleConfigFiles as $configFile) {
            try {
                return $locator->locate($configFile, $cwd, $first = true);
            } catch (\InvalidArgumentException $exception) {
                $lastException = $exception;
            }
        }
        throw $lastException;
    }
}