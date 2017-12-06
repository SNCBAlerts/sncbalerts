<?php

namespace drupol\sncbdelay\Strategies\IRail;

class Departures extends \SplHeap
{
    /**
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return int|void
     */
    protected function compare($value1, $value2) {
        if ($value1['departure']['time'] == $value2['departure']['time'])
        {
            return $value2['departure']['delay'] - $value1['departure']['delay'];
        }

        return $value2['departure']['time'] - $value1['departure']['time'];
    }
}
