<?php

namespace drupol\sncbdelay\Event;

use Symfony\Component\EventDispatcher\Event;

abstract class AbstractEvent extends Event
{
    /**
     * @var array
     */
    protected $storage;

    /**
     * AbstractEvent constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->storage = $data;
    }

    /**
     * @return array
     */
    public function getStorage()
    {
        return $this->storage;
    }
}
