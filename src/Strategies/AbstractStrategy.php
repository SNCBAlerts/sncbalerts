<?php

namespace drupol\sncbdelay\Strategies;

use Psr\Log\LoggerAwareTrait;

abstract class AbstractStrategy
{
    use LoggerAwareTrait;

    /**
     * AbstractStrategy constructor.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties()
    {
        return $this->parameters->all();
    }

    /**
     * @throws \Exception
     *
     * @return object
     */
    public function getLogger()
    {
        return $this->logger;
    }
}
