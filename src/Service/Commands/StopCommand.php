<?php
/**
 * Created by PhpStorm.
 * User: zeolite
 * Date: 28.04.18
 * Time: 0:00
 */

namespace HW3\Service\Commands;

use HW3\Exceptions\SocketServer\DaemonException;
use HW3\Interfaces\DaemonInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommand extends Command
{
    /**
     * @var DaemonInterface
     */
    private $daemon;
    /**
     * StopCommand constructor.
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
            ->setName('stop')
            ->setDescription('Stops daemon')
            ->setHelp('Run this command to stop daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $isStopped = $this->daemon->stop();
            if ($isStopped) {
                $output->writeln('<comment>Daemon was successfully stopped</comment>');
            } else {
                $output->writeln("<error>Could not stop daemon</error>");
            }
        } catch (DaemonException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}
