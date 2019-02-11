<?php

namespace vakata\frontenddependencies;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Command\BaseCommand;

class Command extends BaseCommand
{
    protected function configure()
    {
        $this->setName('frontend-dependencies');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new Worker(
            $this->getComposer(),
            function (string $message) use ($output) {
                $output->writeln($message);
            }
        ))->execute("command");
    }
}