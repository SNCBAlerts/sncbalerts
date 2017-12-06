<?php

namespace drupol\sncbdelay\Strategies\IRail;

use drupol\sncbdelay\Event\Alert;
use drupol\sncbdelay\Event\Canceled;
use drupol\sncbdelay\Event\Delay;
use drupol\sncbdelay\Strategies\AbstractStrategy;
use Http\Client\HttpClient;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\UriFactory;
use Psr\Log\LoggerInterface;

class IRail extends AbstractStrategy
{
    /**
     * The URI factory.
     *
     * @var \Http\Message\UriFactory
     */
    protected $uriFactory;

    /**
     * @var \Http\Client\HttpClient
     */
    protected $httpclient;

    /**
     * IRail constructor.
     *
     * @param \Http\Message\UriFactory|NULL $uriFactory
     */
    public function __construct(LoggerInterface $logger, HttpClient $httpclient, UriFactory $uriFactory = null)
    {
        $this->setLogger($logger);
        $this->uriFactory = is_null($uriFactory) ? UriFactoryDiscovery::find() : $uriFactory;
        $this->httpclient = $httpclient;
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

        return $this->uriFactory->createUri($uri)
            ->withQuery(http_build_query($query));
    }

    /**
     * @return \Generator
     * @throws \Exception
     */
    public function getAllStations()
    {
        $this->getLogger()->debug('Getting all stations...');

        $url = $this->get('stations', ['format' => 'json', 'lang' => 'en']);

        $result = $this->httpclient->request('get', $url);

        $stations = json_decode($result->getBody()->__toString(), true);

        foreach ($stations['station'] as $station) {
            $this->getLogger()->debug('Processing station...', ['station' => $station]);
            yield $station;
        }
    }

    /**
     * @param array $station
     *
     * @return mixed
     * @throws \Exception
     */
    public function getLiveBoard(array $station)
    {
        $this->getLogger()->debug('Getting liveboard...', ['station' => $station]);

        $time = date('hi');
        $date = date('dmy');

        $url = $this->get(
            'liveboard',
            ['id' => $station['id'], 'format' => 'json', 'alert' => 'true', 'time' => $time, 'date' => $date]
        );

        $result = $this->httpclient->request('get', $url);

        return $result;
    }

    /**
     * @return \Generator
     * @throws \Exception
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
     * @return \Generator
     * @throws \Exception
     */
    public function getLiveBoards()
    {
        $this->getLogger()->debug('Getting all liveboards...');

        foreach ($this->getAllStations() as $station) {
            $liveboard = $this->getLiveBoard($station);

            if (200 != $liveboard->getStatusCode()) {
                $this->getLogger()->debug('Skipping liveboard for station', ['station' => $station]);
                continue;
            }

            $liveboard = $liveboard->getBody()->__toString();

            $this->getLogger()->debug('Processing liveboard...', ['liveboard' => $liveboard, 'station' => $station]);
            yield ['station' => $station, 'liveboard' => json_decode($liveboard, true)];
        }
    }

    /**
     * @throws \Exception
     */
    public function getDelays()
    {
        $this->getLogger()->debug('Getting departure delays...');
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $departuresHeap = new Departures();

        foreach ($this->getLiveBoards() as $data) {
            $liveboard = $data['liveboard'];
            $station = $data['station'];

            $departures = array_filter($liveboard['departures']['departure'], function($item) {
                return $item['delay'] > 0 || $item['canceled'] != 0;
            });

            foreach ($departures as $departure) {
                $departuresHeap->insert(['departure' => $departure, 'station' => $station]);
            }
        }

        foreach ($departuresHeap as $data) {
            if (0 != $data['departure']['canceled']) {
                $dispatcher->dispatch(Canceled::NAME, new Canceled($data));
            } else if (0 < $data['departure']['delay']) {
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
}
