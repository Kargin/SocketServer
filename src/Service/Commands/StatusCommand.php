<?php

namespace HW3\Service\Commands;

use HW3\Exceptions\SocketServer\DaemonException;
use HW3\Interfaces\DaemonInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    /**
     * @var DaemonInterface
     */
    private $daemon;
    /**
     * StatusCommand constructor.
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
            ->setName('status')
            ->setDescription('Tells if daemon is running or not')
            ->setHelp('Run this command check daemon status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $isRunning = $this->daemon->status();
            if ($isRunning) {
                $output->writeln('<comment>Daemon is running</comment>');
            } else {
                $output->writeln("<error>Daemon is NOT running</error>");
            }
        } catch (DaemonException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}
