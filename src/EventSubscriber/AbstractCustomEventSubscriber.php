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
        return ['sncbdelay.message.custom' => 'handler'];
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     */
    public function handler(Event $event)
    {
        $this->process($event);
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
        $message = $event->getStorage()['message'];

        return $this->twig->render(
            'debug/custom.twig',
            [
                'message' => $message,
            ]
        );
    }
}
