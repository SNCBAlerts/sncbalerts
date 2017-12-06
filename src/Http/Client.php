<?php
namespace drupol\sncbdelay\Http;

use Http\Client\Common\HttpClientDecorator;
use Http\Client\Common\Plugin\ContentLengthPlugin;
use Http\Client\Common\Plugin\ContentTypePlugin;
use Http\Client\Common\Plugin\DecoderPlugin;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\Plugin\RedirectPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Exception\TransferException;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;

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
    private $messageFactory;

    /**
     * Client constructor.
     *
     * @param \Http\Client\HttpClient|NULL $httpClient
     *   The HTTP client.
     * @param \Http\Message\MessageFactory|NULL $messageFactory
     *   The message factory.
     * @param \Http\Message\UriFactory|NULL $uriFactory
     *   The URI factory.
     */
    public function __construct(
        HttpClient $httpClient = null,
        MessageFactory $messageFactory = null,
        UriFactory $uriFactory = null
    ) {
        $this->httpClient = is_null($httpClient) ? HttpClientDiscovery::find() : $httpClient;
        $this->messageFactory = is_null($messageFactory) ? MessageFactoryDiscovery::find() : $messageFactory;
        $this->uriFactory = is_null($uriFactory) ? UriFactoryDiscovery::find() : $uriFactory;

        $defaultUserAgent = 'SNCB Alerts (pol.dellaiera@gmail.com)';

        $headerDefaultsPlugin = new HeaderDefaultsPlugin([
            'User-Agent' => $defaultUserAgent
        ]);

        $this->httpClient = new PluginClient(
            $this->httpClient,
            [
                $headerDefaultsPlugin,
                new RetryPlugin(),
                new RedirectPlugin(),
                new ContentLengthPlugin(),
                new ContentTypePlugin(),
            ]
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
}
