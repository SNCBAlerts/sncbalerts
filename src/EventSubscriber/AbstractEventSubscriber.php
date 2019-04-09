<?php

namespace drupol\sncbdelay\EventSubscriber;

use Doctrine\ORM\EntityManager;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

abstract class AbstractEventSubscriber implements EventSubscriberInterface
{
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
     * @var \Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface
     */
    protected $parameters;

    /**
     * AbstractEventSubscriber constructor.
     *
     * @param \Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface $parameters
     * @param \Twig\Environment $twig
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     * @param \Doctrine\ORM\EntityManager $doctrine
     */
    public function __construct(ContainerBagInterface $parameters, Environment $twig, LoggerInterface $logger, CacheItemPoolInterface $cache, EntityManager $doctrine)
    {
        $this->parameters = $parameters;
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
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @return mixed
     */
    abstract public function getMessage(Event $event);
}
