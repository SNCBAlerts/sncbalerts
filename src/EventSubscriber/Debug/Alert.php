<?php

namespace drupol\sncbdelay\EventSubscriber\Debug;

use drupol\sncbdelay\EventSubscriber\AbstractAlertEventSubscriber;
use Symfony\Component\EventDispatcher\Event;

class Alert extends AbstractAlertEventSubscriber
{
    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function process(Event $event) {
        $disturbance = $event->getStorage()['disturbance'];

        echo $this->twig->render(
            'debug/alert.twig',
            array(
                'title' => $disturbance['title'],
                'description' => $disturbance['description'],
                'url' => $disturbance['link'],
            )
        );
    }
}
