<?php

namespace CarterZenk\EloquentIdeHelper\Command;

use CarterZenk\EloquentIdeHelper\Generators\FacadesGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GeneratorCommand extends AbstractCommand
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $format;

    /**
     * @inheritdoc
     */
    public function configure()
    {
        parent::configure();

        $this->setName('facades');
        $this->setDescription('Facade IDE Helper');
        $this->setHelp('Generates auto-completion for Eloquent facades.');

        $this->addArgument('filename', InputArgument::OPTIONAL, 'The path to the helper file');
        $this->addOption('format', 'F', InputOption::VALUE_OPTIONAL, 'The format for the IDE Helper', 'php');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return int|null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->bootstrap($input, $output);

            $filename = $input->getArgument('filename');

            if (!$filename) {
                $filename = $this->config->getFacadeOutputFile();
            }

            $generator = new FacadesGenerator();
            $format = $input->getOption('format');
            $content = $generator->generate($format);
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());
            return 1;
        }

        if (file_put_contents($filename, $content, 0) != false) {
            $io->success("A new helper file was written to $filename");
        } else {
            $io->error("The helper file could not be created at $filename");
            return 1;
        }

        return null;
    }
}
