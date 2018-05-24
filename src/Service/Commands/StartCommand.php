<?php

namespace HW3\Service\Commands;

use HW3\Exceptions\Daemon\DaemonException;
use HW3\Interfaces\DaemonInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends Command
{
    /**
     * @var DaemonInterface
     */
    private $daemon;
    /**
     * StartCommand constructor.
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
            ->setName('start')
            ->setDescription('Starts daemon')
            ->setHelp('Run this command to start daemon')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('config', 'c', InputOption::VALUE_REQUIRED),
                    new InputOption('non-daemon', 'o'),
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $isStarted = $this->daemon->start();
            $command = $input->getOption('config');
            $output->writeln( "$command");
            if ($isStarted) {
                usleep(500000);
                $pid = $this->daemon->getPid();
                $output->writeln("<comment>Daemon successfully started with PID {$pid} </comment>");
            } else {
                $output->writeln("<error>Could not start daemon</error>");
            }
        } catch (DaemonException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}