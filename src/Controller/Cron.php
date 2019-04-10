<?php

namespace drupol\sncbdelay\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

class Cron extends AbstractController
{
    /**
     * @Route("/cron", name="cron")
     */
    public function cron(Request $request)
    {
        $command = sprintf('nohup ../bin/console start > /dev/null 2>&1 &');

        $process = new Process($command);
        $process->run();

        $output = 'Command: ' . $process->getCommandLine();
        $output .= ' Status: ' . $process->getStatus();
        $output .= ' PID: ' . $process->getPid();
        $output .= ' Output: ' . $process->getOutput();

        return new Response($output);
    }
}
