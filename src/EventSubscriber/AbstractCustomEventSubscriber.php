<?php

namespace drupol\sncbdelay\EventSubscriber;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractCustomEventSubscriber extends AbstractEventSubscriber
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array('sncbdelay.message.custom' => 'handler');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function handler(Event $event)
    {
        $this->process($event);
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @return mixed|string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getMessage(Event $event)
    {
        $message = $event->getStorage()['message'];

        return $this->twig->render(
            'debug/custom.twig',
            array(
                'message' => $message,
            )
        );
    }
}
