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
        return ['sncbdelay.message.alert' => 'handler'];
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function handler(Event $event)
    {
        $disturbance = $event->getStorage()['disturbance'];
        date_default_timezone_set('Europe/Brussels');

        $currentTime = time();

        $uniqid = sha1(serialize([static::class, $disturbance]));

        $cache = $this->cache->getItem($uniqid);

        if (
            !$cache->isHit() &&
            $disturbance['timestamp'] > $currentTime - 60*60
        ) {
            $this->process($event);
            $cache->set($event);
            $this->cache->save($cache);
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @return mixed|string
     */
    public function getMessage(Event $event)
    {
        $disturbance = $event->getStorage()['disturbance'];

        return $this->twig->render(
            'debug/alert.twig',
            [
                'title' => $disturbance['title'],
                'description' => $disturbance['description'],
                'url' => $disturbance['link'],
            ]
        );
    }
}
