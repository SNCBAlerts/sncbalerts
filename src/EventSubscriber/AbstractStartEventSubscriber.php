<?php

namespace drupol\sncbdelay\EventSubscriber;

abstract class AbstractStartEventSubscriber extends AbstractEventSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return ['sncbdelay.command.start' => 'handler'];
    }
}
