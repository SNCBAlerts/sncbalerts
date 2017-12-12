<?php

namespace drupol\sncbdelay\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;

class Cron extends Controller
{
    /**
     * @Route("/cron", name="cron")
     */
    public function cron(Request $request)
    {
        /** @var \Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait $kernel */
        $kernel = $this->get('kernel');

        $command = sprintf('nohup ../bin/console start > /dev/null 2>&1 &');

        $process = new Process($command);
        $process->run();

        $output = "Command: " . $process->getCommandLine();
        $output .= " Status: " . $process->getStatus();
        $output .= " PID: " . $process->getPid();
        $output .= " Output: " . $process->getOutput();

        return new Response($output);

        /* ./bin/console start -v
        $store = new FlockStore(sys_get_temp_dir());
        $factory = new Factory($store);

        $lock = $factory->createLock('sncbdelay-cron');

        if ($lock->acquire()) {
            $strategy = $this->container->get('sncbdelay.strategy');
            $strategy->setContainer($this->container);
            $strategy->getAlerts();
            $strategy->getDelays();

            $lock->release();

            return new Response(microtime(TRUE));
        } else {
            return new Response('Process locked');
        }
        */
    }
}
