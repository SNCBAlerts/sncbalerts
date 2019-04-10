<?php

namespace drupol\sncbdelay\EventSubscriber\Debug;

use drupol\sncbdelay\EventSubscriber\AbstractEndEventSubscriber;
use Symfony\Component\EventDispatcher\Event;

class End extends AbstractEndEventSubscriber
{

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @return mixed
     */
    public function getMessage(Event $event) {
        return 'End.';
    }
}
