<?php

namespace drupol\sncbdelay\EventSubscriber;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventSubscriber implements EventSubscriberInterface
{
    /**
     * The TWIG environment.
     *
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * The cache.
     *
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    protected $cache;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * AbstractEventSubscriber constructor.
     *
     * @param \Twig_Environment $twig
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Twig_Environment $twig,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger
    ) {
        $this->twig = $twig;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    abstract public function handler(Event $event);

    /**
     * @param \Symfony\Component\EventDispatcher\Event $event
     */
    abstract public function process(Event $event);
}
