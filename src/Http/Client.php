<?php
namespace drupol\sncbdelay\Http;

use Http\Client\Common\HttpClientDecorator;
use Http\Client\Common\Plugin\CachePlugin;
use Http\Client\Common\Plugin\ContentLengthPlugin;
use Http\Client\Common\Plugin\ContentTypePlugin;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\Plugin\LoggerPlugin;
use Http\Client\Common\Plugin\RedirectPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Exception\TransferException;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Client.
 */
class Client implements HttpClient
{
    use HttpClientDecorator;

    /**
     * The URI Factory.
     *
     * @var UriFactory
     */
    protected $uriFactory;

    /**
     * The message factory.
     *
     * @var \Http\Message\MessageFactory
     */
    protected $messageFactory;

    /**
     * The stream factory.
     *
     * @var \Http\Message\StreamFactory
     */
    protected $streamFactory;

    /**
     * The logger.
     *
     * @var
     */
    protected $logger;

    /**
     * The cache.
     *
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    protected $cache;

    /**
     * Client constructor.
     *
     * @param \Http\Client\HttpClient|NULL $httpClient
     *   The HTTP client.
     * @param \Http\Message\MessageFactory|NULL $messageFactory
     *   The message factory.
     * @param \Http\Message\StreamFactory|NULL $streamFactory
     *   The stream factory.
     * @param \Http\Message\UriFactory|NULL $uriFactory
     *   The URI factory.
     * @param \Psr\Log\LoggerInterface|NULL $logger
     *   The logger.
     * @param \Psr\Cache\CacheItemPoolInterface|NULL $cache
     *   The cache.
     */
    public function __construct(
        HttpClient $httpClient = null,
        MessageFactory $messageFactory = null,
        StreamFactory $streamFactory = null,
        UriFactory $uriFactory = null,
        LoggerInterface $logger = null,
        CacheItemPoolInterface $cache = null
    ) {
        $this->httpClient = is_null($httpClient) ? HttpClientDiscovery::find() : $httpClient;
        $this->messageFactory = is_null($messageFactory) ? MessageFactoryDiscovery::find() : $messageFactory;
        $this->streamFactory = is_null($streamFactory) ? StreamFactoryDiscovery::find() : $streamFactory;
        $this->uriFactory = is_null($uriFactory) ? UriFactoryDiscovery::find() : $uriFactory;
        $this->logger = is_null($logger) ? new NullLogger() : $logger;
        $this->cache = $cache;

        $plugins = [
            new HeaderDefaultsPlugin([
                'User-Agent' => 'SNCB Alerts (pol.dellaiera@gmail.com)',
            ]),
            new RetryPlugin(),
            new RedirectPlugin(),
            new ContentLengthPlugin(),
            new ContentTypePlugin(),
            new LoggerPlugin($this->logger),
        ];

        if (!is_null($this->cache)) {
            $plugins[] = new CachePlugin($this->cache, $this->streamFactory);
        }

        $this->httpClient = new PluginClient(
            $this->httpClient, $plugins
        );
    }

    /**
     * Perform a request.
     *
     * @param string                                $method
     *   The HTTP method.
     * @param string|\Psr\Http\Message\UriInterface $url
     *   The URL.
     * @param array                                 $headers
     *   The headers.
     * @param string|null                           $body
     *   The body.
     * @param string                                $protocolVersion
     *   The protocol version.
     *
     * @throws \Exception
     *
     * @return \Psr\Http\Message\ResponseInterface
     *   The response.
     */
    public function request($method, $url, array $headers = [], $body = null, $protocolVersion = '1.1')
    {
        $request = $this->messageFactory->createRequest($method, $url, $headers, $body, $protocolVersion);

        try {
            return $this->sendRequest($request);
        } catch (TransferException $e) {
            throw new \Exception('Error while requesting data: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the URI Factory.
     *
     * @return \Http\Message\UriFactory|NULL
     */
    public function getUriFactory()
    {
        return $this->uriFactory;
    }
}
