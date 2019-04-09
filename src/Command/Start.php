<?php

namespace drupol\sncbdelay\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Start extends Command implements ContainerAwareInterface
{
    use LockableTrait;
    use ContainerAwareTrait;

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

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(\drupol\sncbdelay\Event\Start::NAME);

        /** @var IRail $strategy */
        $strategy = $this->getContainer()->get('sncbdelay.strategy');
        $strategy->getAlerts();
        $strategy->getDelays();

        $dispatcher->dispatch(\drupol\sncbdelay\Event\End::NAME);
        $this->release();
    }

    public function getContainer() {
        return $this->container;
    }
}
