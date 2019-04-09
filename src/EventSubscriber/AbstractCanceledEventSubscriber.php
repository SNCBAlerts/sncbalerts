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
        return ['sncbdelay.message.canceled' => 'handler'];
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function handler(Event $event)
    {
        $departure = $event->getStorage()['departure'];
        date_default_timezone_set('Europe/Brussels');

        $currentTime = time();

        $uniqid1 = sha1(serialize([static::class, $departure]));
        $uniqid2 = sha1(serialize([static::class, $departure['vehicleinfo']['name']]));

        $cache1 = $this->cache->getItem($uniqid1);
        $cache2 = $this->cache->getItem($uniqid2);

        if (
            $departure['time'] > $currentTime &&
            !$cache1->isHit() &&
            !$cache2->isHit()
        ) {
            $this->process($event);

            $cache1->set($event);
            $cache2->set($event);

            $this->cache->save($cache1);
            $this->cache->save($cache2);
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     *
     * @return mixed|string
     */
    public function getMessage(Event $event)
    {
        $departure = $event->getStorage()['departure'];
        $station = $event->getStorage()['station'];

        return $this->twig->render(
            'debug/canceled.twig',
            [
                'train' => $departure['vehicle'],
                'station_from' => $station['name'],
                'station_to' => $departure['stationinfo']['name'],
                'time' => date('H:i', $departure['time'])
            ]
        );
    }
}
