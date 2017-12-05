<?php

namespace drupol\sncbdelay\Strategies;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

abstract class AbstractStrategy implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    /**
     * AbstractStrategy constructor.
     */
    public function __construct() {

    }

    /**
     * Get the container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties()
    {
        return $this->getContainer()->getParameterBag()->all();
    }

    /**
     * @return object
     * @throws \Exception
     */
    public function getLogger() {
        return $this->logger;
    }
}
