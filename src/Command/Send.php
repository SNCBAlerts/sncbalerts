<?php

namespace drupol\sncbdelay\Command;

use drupol\sncbdelay\Event\Custom;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Send extends Command implements ContainerAwareInterface
{
    use LockableTrait;
    use ContainerAwareTrait;

    protected function configure()
    {
        $this->setName('send');
        $this->addArgument('message', InputArgument::REQUIRED, 'The message to send.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dispatch = $this->getContainer()->get('event_dispatcher');
        $dispatch->dispatch(Custom::NAME, new Custom(['message' => $input->getArgument('message')]));
    }

    public function getContainer() {
        return $this->container;
    }
}
