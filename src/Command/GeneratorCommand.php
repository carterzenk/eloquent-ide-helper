<?php

namespace CarterZenk\EloquentIdeHelper\Command;

use CarterZenk\EloquentIdeHelper\Generator;
use Symfony\Component\Console\Command\Command;
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
        $this->setName('ide-helper:generator');
        $this->setDescription('Facade IDE Helper');
        $this->setHelp('Generates auto-completion for Eloquent facades.');

        $this->addArgument('filename', InputArgument::OPTIONAL, 'The path to the helper file');
        $this->addOption('format', 'F', InputOption::VALUE_OPTIONAL, 'The format for the IDE Helper', 'php');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $filename = $input->getArgument('filename');

        if (!$filename) {
            $filename = $this->config->getFacadeOutputFile();
        }

        $format = $input->getOption('format');

        $io = new SymfonyStyle($input, $output);

        $generator = new Generator();
        $content = $generator->generate($format);

        if (file_put_contents($filename, $content, 0) != false) {
            $io->success("A new helper file was written to $filename");
            return null;
        } else {
            $io->error("The helper file could not be created at $filename");
            return 1;
        }
    }
}
