<?php
namespace Clutch\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JumpCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('jump')
            ->setDescription('This is how you Jump')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Hello World');
    }

}
