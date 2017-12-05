<?php

namespace drupol\sncbdelay\EventSubscriber\Debug;

use drupol\sncbdelay\EventSubscriber\AbstractCanceledEventSubscriber;
use Symfony\Component\EventDispatcher\Event;

class Canceled extends AbstractCanceledEventSubscriber
{
    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function process(Event $event) {
        $departure = $event->getStorage()['departure'];
        $station = $event->getStorage()['station'];

        echo $this->twig->render(
            'debug/canceled.twig',
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
