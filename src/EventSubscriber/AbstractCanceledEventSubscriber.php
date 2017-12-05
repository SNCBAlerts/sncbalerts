<?php

namespace drupol\sncbdelay\EventSubscriber;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractCanceledEventSubscriber extends AbstractEventSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array('sncbdelay.canceled' => 'handler');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function handler(Event $event) {
        $departure = $event->getStorage()['departure'];

        $currentTime = time();

        $uniqid1 = sha1(serialize($departure));
        $uniqid2 = sha1(serialize($departure['vehicleinfo']['name']));

        $cache1 = $this->cache->getItem($uniqid1);
        $cache2 = $this->cache->getItem($uniqid2);

        if (
            !$cache1->isHit() &&
            !$cache2->isHit() &&
            ($departure['time'] > $currentTime && $departure['time'] < $currentTime + 60*30) &&
            ($departure['time'] + $departure['delay'] < $currentTime + 60*45)
        ) {
            $this->process($event);

            $cache1->set($event);
            $cache2->set($event);

            $this->cache->save($cache1);
            $this->cache->save($cache2);
        }
    }
}
