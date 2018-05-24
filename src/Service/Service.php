<?php
namespace HW3\Service;

use HW3\Interfaces\DaemonInterface;
use HW3\Service\Commands\StartCommand;
use HW3\Service\Commands\StopCommand;
use Symfony\Component\Console\Application;

#use HW3\Service\Commands\NonDaemonCommand;
use HW3\Service\Commands\RestartCommand;
use HW3\Service\Commands\StatusCommand;

class Service
{
    /**
     * @var Application
     */
    private $application;
    /**
     * Service constructor.
     *
     * @param DaemonInterface $daemon
     */
    public function __construct(DaemonInterface $daemon)
    {
        $this->application = new Application();
        $this->application->add(new StartCommand('start', $daemon));
        $this->application->add(new StopCommand('stop', $daemon));
        $this->application->add(new RestartCommand('restart', $daemon));
        $this->application->add(new StatusCommand('status', $daemon));
        #$this->application->add(new NonDaemonCommand('non-daemon', $daemon));
    }
    public function run(): void
    {
        $this->application->run();
    }
}