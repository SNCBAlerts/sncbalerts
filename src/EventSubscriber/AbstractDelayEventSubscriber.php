<?php

namespace drupol\sncbdelay\EventSubscriber;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractDelayEventSubscriber extends AbstractEventSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array('sncbdelay.message.delay' => 'handler');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function handler(Event $event) {
        $departure = $event->getStorage()['departure'];
        date_default_timezone_set('Europe/Brussels');

        $currentTime = time();

        $uniqid1 = sha1(serialize([Static::class, $departure]));
        $uniqid2 = sha1(serialize([Static::class, $departure['vehicleinfo']['name']]));

        $cache1 = $this->cache->getItem($uniqid1)->expiresAfter(new \DateInterval('PT10M'));
        $cache2 = $this->cache->getItem($uniqid2)->expiresAfter(new \DateInterval('PT10M'));

        if (
            !$cache1->isHit() &&
            !$cache2->isHit() &&
            $departure['time'] > $currentTime - 600 &&
            abs($departure['time'] - $currentTime) <= 1200 &&
            $departure['delay'] >= 600
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
     * @return mixed|string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getMessage(Event $event) {
        $departure = $event->getStorage()['departure'];
        $station = $event->getStorage()['station'];

        return $this->twig->render(
            'debug/delay.twig',
            array(
                'train' => $departure['vehicle'],
                'station_from' => $station['name'],
                'station_to' => $departure['stationinfo']['name'],
                'delay' => $departure['delay']/60,
                'date' => date('H:i', $departure['time'])
            )
        );
    }
}
