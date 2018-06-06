<?php
/**
 * Laravel IDE Helper Generator
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2014 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace CarterZenk\EloquentIdeHelper\Command;

use CarterZenk\EloquentIdeHelper\Generators\ModelsGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ClassLoader\ClassMapGenerator;
use Barryvdh\Reflection\DocBlock;
use Barryvdh\Reflection\DocBlock\Context;
use Barryvdh\Reflection\DocBlock\Tag;
use Barryvdh\Reflection\DocBlock\Serializer as DocBlockSerializer;

/**
 * A command to generate autocomplete information for your IDE
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */
class ModelsCommand extends AbstractCommand
{
    protected $properties = [];
    protected $methods = [];
    protected $write = false;
    protected $ignore;
    protected $dirs;
    protected $filename;
    protected $reset;
    protected $settings;
    protected $verbosity;

    /**
     * @inheritdoc
     */
    public function configure()
    {
        parent::configure();

        $this->setName('models');
        $this->setDescription('Model IDE Helper');
        $this->setHelp('Generates auto-completion for models.');

        $this->addArgument('model', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Which models to include', []);
        $this->addOption('filename', 'F', InputOption::VALUE_REQUIRED, 'The path to the helper file');
        $this->addOption('dir', 'D', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The model dirs', []);
        $this->addOption('ignore', 'I', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Which models to ignore', []);
        $this->addOption('write', 'W', InputOption::VALUE_NONE, 'Write to Model file');
        $this->addOption('nowrite', 'N', InputOption::VALUE_NONE, 'Don\'t write to Model file');
        $this->addOption('reset', 'R', InputOption::VALUE_NONE, 'Remove the original phpdocs instead of appending');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->bootstrap($input, $output);
            $model = $input->getArgument('model');
            $content = $this->generateDocs($io, $model, $this->ignore);
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());
            return 1;
        }

        if (!$this->write) {
            if (file_put_contents($this->filename, $content, 0) != false) {
                $io->success("Model information was written to $this->filename");
            } else {
                $io->error("Failed to write model information to $this->filename");
                return 1;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        parent::bootstrap($input, $output);

        $this->verbosity = $output->getVerbosity();

        $dirs = (array) $input->getOption('dir');
        $this->dirs = $dirs ? $dirs : $this->config->getModelDirectories();

        $this->write = $input->getOption('write')
            ? $input->getOption('write')
            : $this->config->getModelWrite();

        $this->reset = $input->getOption('reset')
            ? $input->getOption('reset')
            : $this->config->getModelReset();

        $this->filename = $input->getOption('filename')
            ? $input->getOption('filename')
            : $this->config->getModelOutputFile();

        $this->ignore = $input->getOption('ignore')
            ? $input->getOption('ignore')
            : $this->config->getIgnoredModels();
    }

    /**
     * @param StyleInterface $io
     * @param $loadModels
     * @param array $ignore
     * @return string|OutputInterface
     */
    protected function generateDocs(StyleInterface $io, $loadModels, $ignore)
    {
        $docs = "<?php
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */
\n\n";

        $hasDoctrine = interface_exists('Doctrine\DBAL\Driver');

        if (empty($loadModels)) {
            $models = $this->loadModels();
        } else {
            $models = array();
            foreach ($loadModels as $model) {
                $models = array_merge($models, explode(',', $model));
            }
        }

        foreach ($models as $name) {
            if (in_array($name, $ignore)) {
                if ($this->verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                    $io->text("Ignoring model '$name'");
                }
                continue;
            }

            if (!class_exists($name)) {
                continue;
            }

            try {
                // handle abstract classes, interfaces, ...
                $reflectionClass = new \ReflectionClass($name);

                if (!$reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                    continue;
                }

                if (!$reflectionClass->isInstantiable()) {
                    // ignore abstract class or interface
                    continue;
                }

                if ($this->verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                    $io->text("Loading model '$name'");
                }

                $generator = new ModelsGenerator(
                    $reflectionClass,
                    $this->config->getModelTypeOverrides(),
                    $this->config->getModelCustomDbTypes(),
                    $this->config->getModelCamelCasedProperties(),
                    $this->reset
                );

                $generator->generate($hasDoctrine);

                if ($this->write) {
                    $generator->writeToModel();
                } else {
                    $namespace = $reflectionClass->getNamespaceName();
                    $docComment = $generator->serialize();
                    $classname = $reflectionClass->getShortName();

                    $docs .= "namespace {$namespace}{\n{$docComment}\n\tclass {$classname} extends \Eloquent {}\n}\n\n";
                }

                $ignore[] = $name;
            } catch (\Exception $e) {
                $io->error([
                    "Could not analyze class $name",
                    $e->getMessage()
                ]);
            }
        }

        if (!$hasDoctrine) {
            $io->warning([
                'Warning: `"doctrine/dbal": "~2.3"` is required to load database information.',
                'Please require that in your composer.json and run `composer update`.'
            ]);
        }

        return $docs;
    }

    /**
     * @return array
     */
    protected function loadModels()
    {
        $models = array();
        foreach ($this->dirs as $dir) {
            if (file_exists($dir)) {
                foreach (ClassMapGenerator::createMap($dir) as $model => $path) {
                    $models[] = $model;
                }
            }
        }
        return $models;
    }
}
