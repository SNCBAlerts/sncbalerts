<?php

namespace drupol\sncbdelay\EventSubscriber;

abstract class AbstractEndEventSubscriber extends AbstractEventSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return ['sncbdelay.command.end' => 'handler'];
    }
}
