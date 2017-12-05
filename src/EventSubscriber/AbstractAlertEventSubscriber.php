<?php

namespace drupol\sncbdelay\EventSubscriber;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractAlertEventSubscriber extends AbstractEventSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array('sncbdelay.alert' => 'handler');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function handler(Event $event) {
        $disturbance = $event->getStorage()['disturbance'];

        $currentTime = time();

        $uniqid = sha1(serialize($disturbance));

        $cache = $this->cache->getItem($uniqid);

        if (
            !$cache->isHit() &&
            ($disturbance['timestamp'] > $currentTime + 60*60)
        ) {
            $this->process($event);
            $cache->set($event);
            $this->cache->save($cache);
        }
    }
}
