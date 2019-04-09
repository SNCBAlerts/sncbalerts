<?php

namespace drupol\sncbdelay\Strategies\IRail;

use drupol\sncbdelay\Event\Alert;
use drupol\sncbdelay\Event\Canceled;
use drupol\sncbdelay\Event\Delay;
use drupol\sncbdelay\Strategies\AbstractStrategy;
use drupol\sncbdelay\Strategies\IRail\Storage\Departures;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IRail extends AbstractStrategy
{
    use HttpClientTrait;

    /**
     * The HTTP client.
     *
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    protected $httpclient;

    /**
     * The departures storage.
     *
     * @var \drupol\sncbdelay\Strategies\IRail\Storage\Departures
     */
    protected $departures;

    /**
     * @var array
     */
    protected $linesMatrix;

    /**
     * @var \Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface
     */
    protected $parameters;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * IRail constructor.
     *
     * @param \Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface $parameters
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param \Psr\Log\LoggerInterface $logger
     *   The logger.
     * @param \Symfony\Contracts\HttpClient\HttpClientInterface $httpclient
     *   The HTTP client.
     * @param \drupol\sncbdelay\Strategies\IRail\Storage\Departures $departures
     *   The departures storage.
     */
    public function __construct(ContainerBagInterface $parameters, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger, HttpClientInterface $httpclient, Departures $departures)
    {
        parent::__construct();

        date_default_timezone_set('Europe/Brussels');

        $this->setLogger($logger);
        $this->eventDispatcher = $eventDispatcher;
        $this->httpclient = $httpclient;
        $this->departures = $departures;
        $this->parameters = $parameters;
        $this->linesMatrix = $this->getLinesMatrix();

    }

    /**
     * @throws \Exception
     *
     * @return \Generator
     */
    public function getAllStations()
    {
        $this->getLogger()->debug('Getting all stations...');

        $stations = $this->httpclient->request(
            'GET',
            'http://api.irail.be/stations/',
            [
                'query' =>
                    [
                        'format' => 'json',
                        'lang' => 'en',
                    ],
            ]
        )->toArray();

        yield from new \ArrayIterator($stations['station']);
    }

    /**
     * @param array $station
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function getLiveBoard($stationId)
    {
        $request = $this->httpclient->request(
            'GET',
            'http://api.irail.be/liveboard/',
            [
                'query' => [
                    'id' => $stationId,
                    'format' => 'json',
                    'alert' => 'true',
                    'time' => date('hi'),
                    'date' => date('hmy'),
                ],
            ]
        );

        if (200 === $request->getStatusCode()) {
            return $request->toArray();
        }

        return false;
    }

    /**
     * @throws \Exception
     *
     * @return \Generator
     */
    public function getDisturbances()
    {
        $this->getLogger()->debug('Getting disturbances...');

        $result = $this->httpclient->request(
            'GET',
            'http://api.irail.be/disturbances/',
            [
                'query' => [
                    'format' => 'json',
                ],
            ]
        );

        $disturbances = $result->toArray();

        $disturbances += ['disturbance' => []];

        foreach ($disturbances['disturbance'] as $disturbance) {
            $this->getLogger()->debug('Processing disturbance...', ['disturbance' => $disturbance]);
            yield $disturbance;
        }
    }

    /**
     * @throws \Exception
     *
     * @return \Generator
     */
    public function getLiveBoards()
    {
        $this->getLogger()->debug('Getting all liveboards...');

        foreach ($this->getAllStations() as $station) {
            $liveboard = $this->getLiveBoard($station['id']);

            if ($liveboard === false) {
                $this->getLogger()->debug('Skipping liveboard for station', ['station' => $station]);

                continue;
            }

            $this->getLogger()->debug('Processing liveboard...', ['liveboard' => $liveboard, 'station' => $station]);

            yield [
                'station' => $station,
                'liveboard' => $liveboard
            ];
        }
    }

    /**
     * @throws \Exception
     */
    public function getDelays()
    {
        $this->getLogger()->debug('Getting departure delays...');
        $currentTime = time();

        foreach ($this->getLiveBoards() as $data) {
            $liveboard = $data['liveboard'];
            $station = $data['station'];

            $departures = array_filter($liveboard['departures']['departure'], function ($item) use ($currentTime){
                return (($item['delay'] > 0 || 0 != $item['canceled']) && ($item['time'] < $currentTime + 3600*2));
            });

            foreach ($departures as $departure) {
                $arguments = ['departure' => $departure, 'station' => $station];

                $arguments['lines'] = $this->getLines(
                    parse_url($station['@id'], PHP_URL_PATH),
                    parse_url($departure['stationinfo']['@id'], PHP_URL_PATH)
                );

                $this->departures->insert($arguments);
            }
        }

        foreach ($this->departures as $data) {
            if (0 != $data['departure']['canceled']) {
                $this->eventDispatcher->dispatch(Canceled::NAME, new Canceled($data));
            } elseif (0 < $data['departure']['delay']) {
                $this->eventDispatcher->dispatch(Delay::NAME, new Delay($data));
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getAlerts()
    {
        $this->getLogger()->debug('Getting alerts...');
        $currentTime = time();

        foreach ($this->getDisturbances() as $disturbance) {
            if (abs($currentTime - $disturbance['timestamp']) <= 1200) {
                $this->eventDispatcher->dispatch(Alert::NAME, new Alert(['disturbance' => $disturbance]));
            }
        }
    }

    /**
     * @return array
     */
    public function getLinesMatrix()
    {
        $request = $this->httpclient->request(
            'GET',
            'https://query.wikidata.org/sparql',
            [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'query'=> 'SELECT
                          DISTINCT ?name
                          (GROUP_CONCAT(DISTINCT ?lineLabel; SEPARATOR = "|") AS ?lineLabels)
                        WHERE {
                          ?s wdt:P31 wd:Q55488.
                          ?s wdt:P17 wd:Q31.
                          ?s wdt:P2888 ?iRail.
                          OPTIONAL { ?s wdt:P81 ?line. }
                          SERVICE wikibase:label {
                            bd:serviceParam wikibase:language "en".
                            ?line rdfs:label ?lineLabel.
                            ?iRail rdfs:label ?name
                          }
                        }
                        GROUP BY ?iRail ?name ?sLabel'
                ],
            ]
        );

        $result = $request->toArray();

        $datas = [];
        foreach ($result['results']['bindings'] as $data)
        {
            $path = parse_url($data['name']['value'], PHP_URL_PATH);

            $datas[$path] = array_unique(array_map(function($line) {
                return filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            }, explode('|', $data['lineLabels']['value'])));
        }

        return $datas;
    }

    /**
     * @param $uriStation1
     * @param $uriStation2
     *
     * @return array
     */
    public function getLines($uriStation1, $uriStation2)
    {
        $this->linesMatrix += [
            $uriStation1 => [],
            $uriStation2 => [],
        ];

        return array_values(
            array_unique(array_intersect(
                    $this->linesMatrix[$uriStation1],
                    $this->linesMatrix[$uriStation2]
                )
            ));
    }
}
