<?php
namespace Clutch\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

  class TrollCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('troll')
            ->setDescription('Trolling')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Hello World');
    }

}
