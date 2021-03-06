<?php

declare(strict_types = 1);

namespace drupol\sncbdelay\Strategies\IRail\Storage;

class Departures extends \SplHeap
{
    /**
     * Sort by time ASC, then delay ASC.
     *
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return int
     */
    protected function compare(array $value1, array $value2)
    {
        if ($value1['departure']['time'] === $value2['departure']['time']) {
            return $value2['departure']['delay'] - $value1['departure']['delay'];
        }

        return $value2['departure']['time'] - $value1['departure']['time'];
    }
}
