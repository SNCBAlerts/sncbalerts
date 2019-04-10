<?php

namespace drupol\sncbdelay\EventSubscriber\Debug;

use drupol\sncbdelay\EventSubscriber\AbstractStartEventSubscriber;
use Symfony\Component\EventDispatcher\Event;

class Start extends AbstractStartEventSubscriber
{

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @return mixed
     */
    public function getMessage(Event $event) {
        return 'Start!';
    }
}
