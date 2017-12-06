<?php

namespace drupol\sncbdelay\Command;

use drupol\sncbdelay\Event\Custom;
use drupol\sncbdelay\Strategies\IRail\IRail;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Send extends ContainerAwareCommand
{
    use LockableTrait;

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
}
