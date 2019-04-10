<?php

declare(strict_types = 1);

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
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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
     * @return \Generator
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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

        $stations['station'] = array_combine(
            array_column(
                $stations['station'],
                'id'
            ),
            $stations['station']
        );

        // In order to avoid 404.
        // TODO: use a static var.
        $ignore = [
            'BE.NMBS.007015400',
            'BE.NMBS.007015440',
            'BE.NMBS.008011090', // Frankfurt am Main Flughafen
            'BE.NMBS.008039904', // Düsseldorf Flughafen Hbf
            'BE.NMBS.008400180', // Dordrecht
            'BE.NMBS.008400280', // Den Haag HS
            'BE.NMBS.008500010', // Basel
            'BE.NMBS.008718201', // Colmar
            'BE.NMBS.008718206', // Mulhouse
            'BE.NMBS.008718213', // Saint-Louis-Haut-Rhin
            'BE.NMBS.008719100', // Thionville
            'BE.NMBS.008719203', // Metz
            'BE.NMBS.008721222', // Saverne
            'BE.NMBS.008721405', // Selestat
            'BE.NMBS.008727100', // Paris Nord
            'BE.NMBS.008728105', // Croix l'Allumette
            'BE.NMBS.008728671', // Croix Wasquehal
            'BE.NMBS.008772202', // Lyon-Perrache TGV
            'BE.NMBS.008774100', // Chambéry-Challes-les-Eaux
            'BE.NMBS.008774164', // Albertville
            'BE.NMBS.008774172', // Moûtiers-Salins-Brides-les-Bai
            'BE.NMBS.008774176', // Aime-la-Plagne
            'BE.NMBS.008774177', // Landry
            'BE.NMBS.008774179', // Bourg-Saint-Maurice
            'BE.NMBS.008775500', // Toulon
            'BE.NMBS.008775544', // Les Arcs - Draguignan
            'BE.NMBS.008775605', // Nice Ville
            'BE.NMBS.008775752', // Saint-Raphaël-Valescure
            'BE.NMBS.008775762', // Cannes
            'BE.NMBS.008775767', // Antibes
            'BE.NMBS.008776290', // Lyon-Saint Exupéry TGV
            'BE.NMBS.008777320', // Sète
            'BE.NMBS.008778100', // Béziers
            'BE.NMBS.008778110', // Narbonne
            'BE.NMBS.008778127', // Agde
            'BE.NMBS.008778400', // Perpignan
            'BE.NMBS.008821022', // Antwerpen-Oost
            'BE.NMBS.008821030', // Antwerpen-Dam
            'BE.NMBS.008821048', // Antwerpen-Haven
            'BE.NMBS.008821154', // Mortsel-Deurnesteenweg
            'BE.NMBS.008829009', // Essen-Grens
            'BE.NMBS.008841525', // Liège-Saint-Lambert
            'BE.NMBS.008841558', // Liège-Carré
            'BE.NMBS.008847258', // Y.renory
            'BE.NMBS.008849023', // Hergenrath-Frontiere
            'BE.NMBS.008849064', // Vise-Frontiere
            'BE.NMBS.008849072', // Gouvy-Frontiere
            'BE.NMBS.008861168', // Ham-sur-Sambre
            'BE.NMBS.008864923', // Florée
            'BE.NMBS.008869047', // Athus-Frontiere
            'BE.NMBS.008869054', // Sterpenich-Frontiere
            'BE.NMBS.008869088', // Aubange-Frontiere-Luxembourg
            'BE.NMBS.008889011', // Mouscron-Frontiere
            'BE.NMBS.008889045', // Blandain-Frontiere
            'BE.NMBS.008891173', // Zeebrugge-Strand
            'BE.NMBS.008891611', // Zwankendamme
        ];

        foreach ($ignore as $ignored_station) {
            unset($stations['station'][$ignored_station]);
        }

        yield from new \ArrayIterator($stations['station']);
    }

    /**
     * @param $stationId
     *
     * @return mixed
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getLiveBoard(string $stationId)
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

        dump($stationId);

        return false;
    }

    /**
     * @return \Generator
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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

        $disturbances = $result->toArray() + ['disturbance' => []];

        foreach ($disturbances['disturbance'] as $disturbance) {
            $this->getLogger()->debug('Processing disturbance...', ['disturbance' => $disturbance]);
            yield $disturbance;
        }
    }

    /**
     * @return \Generator
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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

            yield [$station, $liveboard];
        }
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getDelays()
    {
        $this->getLogger()->debug('Getting departure delays...');
        $currentTime = time();

        foreach ($this->getLiveBoards() as [$station, $liveboard]) {
            $departures = array_filter(
                $liveboard['departures']['departure'],
                static function ($item) use ($currentTime) {
                    return (($item['delay'] > 0 || '0' !== $item['canceled']) && ($item['time'] < $currentTime + 3600*2));
                }
            );

            foreach ($departures as $departure) {
                $arguments = [
                    'departure' => $departure,
                    'station' => $station
                ];

                $arguments['lines'] = $this->getLines(
                    parse_url($station['@id'], PHP_URL_PATH),
                    parse_url($departure['stationinfo']['@id'], PHP_URL_PATH)
                );

                $this->departures->insert($arguments);
            }
        }

        foreach ($this->departures as $data) {
            if ('0' !== $data['departure']['canceled']) {
                $this->eventDispatcher->dispatch(Canceled::NAME, new Canceled($data));
                continue;
            }

            if (0 < $data['departure']['delay']) {
                $this->eventDispatcher->dispatch(Delay::NAME, new Delay($data));
                continue;
            }
        }
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getAlerts()
    {
        $this->getLogger()->debug('Getting alerts...');
        $currentTime = time();

        foreach ($this->getDisturbances() as $disturbance) {
            if (abs($currentTime - $disturbance['timestamp']) > 1200) {
                continue;
            }

            $this->eventDispatcher->dispatch(Alert::NAME, new Alert(['disturbance' => $disturbance]));
        }
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     *
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
                        GROUP BY ?iRail ?name ?sLabel',
                ],
            ]
        );

        $result = $request->toArray();

        $datas = [];
        foreach ($result['results']['bindings'] as $data)
        {
            $path = parse_url($data['name']['value'], PHP_URL_PATH);

            $datas[$path] = array_unique(
                array_map(
                    static function ($line) {
                        return filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                    },
                    explode('|', $data['lineLabels']['value'])
                )
            );
        }

        return $datas;
    }

    /**
     * @param $uriStation1
     * @param $uriStation2
     *
     * @return array
     */
    public function getLines(string $uriStation1, string $uriStation2): array
    {
        $this->linesMatrix += [
            $uriStation1 => [],
            $uriStation2 => [],
        ];

        return array_values(
            array_unique(
                array_intersect(
                    $this->linesMatrix[$uriStation1],
                    $this->linesMatrix[$uriStation2]
                )
            )
        );
    }
}
