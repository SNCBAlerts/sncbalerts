<?php

namespace drupol\sncbdelay\EventSubscriber;

use Doctrine\ORM\EntityManager;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventSubscriber implements EventSubscriberInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * The container.
     *
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $doctrine;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    protected $cache;

    /**
     * AbstractEventSubscriber constructor.
     *
     * @param \Psr\Container\ContainerInterface $container
     * @param \Twig_Environment $twig
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Doctrine\ORM\EntityManager $doctrine
     */
    public function __construct(ContainerInterface $container, \Twig_Environment $twig, LoggerInterface $logger, CacheItemPoolInterface $cache, EntityManager $doctrine)
    {
        $this->container = $container;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->cache = $cache;
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
     */
    public function process(Event $event)
    {
        $this->logger->notice(
            $this->getMessage($event)
        );
    }

    /**
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @return mixed
     */
    abstract public function getMessage(Event $event);
}
