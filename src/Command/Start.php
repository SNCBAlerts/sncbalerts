<?php

namespace drupol\sncbdelay\Command;

use drupol\sncbdelay\Strategies\IRail\IRail;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Start extends ContainerAwareCommand
{
    use LockableTrait;

    protected function configure()
    {
        $this->setName('start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        /** @var IRail $strategy */
        $strategy = $this->getContainer()->get('sncbdelay.strategy');
        $strategy->setContainer($this->getContainer());
        $strategy->getAlerts();
        $strategy->getDelays();

        $this->release();
    }
}
