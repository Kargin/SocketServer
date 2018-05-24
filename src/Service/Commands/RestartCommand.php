<?php

namespace HW3\Service\Commands;

use HW3\Exceptions\Daemon\DaemonException;
use HW3\Interfaces\DaemonInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RestartCommand extends Command
{
    /**
     * @var DaemonInterface
     */
    private $daemon;
    /**
     * RestartCommand constructor.
     *
     * @param null $name
     * @param DaemonInterface $daemon
     *
     */
    public function __construct($name = null, DaemonInterface $daemon)
    {
        parent::__construct($name);
        $this->daemon = $daemon;
    }

    protected function configure(): void
    {
        $this
            ->setName('restart')
            ->setDescription('Restarts daemon')
            ->setHelp('Run this command to restart daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $isSuccess = $this->daemon->restart();
            if ($isSuccess) {
                $pid = $this->daemon->getPid();
                $output->writeln("<comment>Daemon was successfully restarted at PID {$pid}</comment>");
            } else {
                $output->writeln("<error>Error occured while restarting daemon</error>");
            }
        } catch (DaemonException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}
