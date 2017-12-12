<?php

namespace drupol\sncbdelay\Strategies\IRail;

use drupol\sncbdelay\Event\Alert;
use drupol\sncbdelay\Event\Canceled;
use drupol\sncbdelay\Event\Delay;
use drupol\sncbdelay\Strategies\AbstractStrategy;
use drupol\sncbdelay\Strategies\IRail\Storage\Departures;
use Http\Client\HttpClient;
use Psr\Log\LoggerInterface;

class IRail extends AbstractStrategy
{
    /**
     * The HTTP client.
     *
     * @var \Http\Client\HttpClient
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
     * IRail constructor.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *   The logger.
     * @param \Http\Client\HttpClient $httpclient
     *   The HTTP client.
     * @param \drupol\sncbdelay\Strategies\IRail\Storage\Departures $departures
     *   The departures storage.
     */
    public function __construct(LoggerInterface $logger, HttpClient $httpclient, Departures $departures)
    {
        $this->setLogger($logger);
        $this->httpclient = $httpclient;
        $this->departures = $departures;
        $this->linesMatrix = $this->getLinesMatrix();

        date_default_timezone_set('Europe/Brussels');
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, array $query = [])
    {
        $properties = $this->getProperties();
        $name = strtolower($name);

        $uri = sprintf('%s/%s/', $properties['api']['uri'], $name);

        return $this->httpclient->getUriFactory()->createUri($uri)
            ->withQuery(http_build_query($query));
    }

    /**
     * @throws \Exception
     *
     * @return \Generator
     */
    public function getAllStations()
    {
        $this->getLogger()->debug('Getting all stations...');

        $result = $this->httpclient->request('get', $this->get('stations', ['format' => 'json', 'lang' => 'en']));

        $stations = json_decode($result->getBody()->__toString(), true);

        foreach ($stations['station'] as $station) {
            $this->getLogger()->debug('Processing station...', ['station' => $station]);
            yield $station;
        }
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
        $time = date('hi');
        $date = date('dmy');

        return $this->httpclient->request('get', $this->get(
            'liveboard',
            ['id' => $stationId, 'format' => 'json', 'alert' => 'true', 'time' => $time, 'date' => $date]
        ));
    }

    /**
     * @throws \Exception
     *
     * @return \Generator
     */
    public function getDisturbances()
    {
        $this->getLogger()->debug('Getting disturbances...');

        $url = $this->get(
            'disturbances',
            ['format' => 'json']
        );

        $result = $this->httpclient->request('get', $url);

        $disturbances = json_decode($result->getBody()->__toString(), true);

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

            if (200 != $liveboard->getStatusCode()) {
                $this->getLogger()->debug('Skipping liveboard for station', ['station' => $station]);

                continue;
            }

            $this->getLogger()->debug('Processing liveboard...', ['liveboard' => $liveboard, 'station' => $station]);
            yield ['station' => $station, 'liveboard' => json_decode($liveboard->getBody()->__toString(), true)];
        }
    }

    /**
     * @throws \Exception
     */
    public function getDelays()
    {
        $this->getLogger()->debug('Getting departure delays...');
        $dispatcher = $this->getContainer()->get('event_dispatcher');
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
                $dispatcher->dispatch(Canceled::NAME, new Canceled($data));
            } elseif (0 < $data['departure']['delay']) {
                $dispatcher->dispatch(Delay::NAME, new Delay($data));
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getAlerts()
    {
        $this->getLogger()->debug('Getting alerts...');
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $currentTime = time();

        foreach ($this->getDisturbances() as $disturbance) {
            if (abs($currentTime - $disturbance['timestamp']) <= 1200) {
                $dispatcher->dispatch(Alert::NAME, new Alert(['disturbance' => $disturbance]));
            }
        }
    }

    /**
     * @return array
     */
    public function getLinesMatrix()
    {
        $url = $this->httpclient->getUriFactory()->createUri('/sparql')
            ->withHost('query.wikidata.org')
            ->withScheme('https')
            ->withQuery(
                http_build_query(
                    [
                        'query' => 'SELECT
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
                    ]
                )
            );

        $response = $this->httpclient->request('get', $url, ['Accept' => 'application/json']);
        $result = json_decode($response->getBody()->__toString(), TRUE);

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
