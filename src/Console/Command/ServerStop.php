<?php namespace Seals\Console\Command;

use Seals\Library\Worker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServerStop extends ServerBase
{
    protected function configure()
    {
        $this
            ->setName('server:stop')
            ->setAliases(["stop"])
            ->setDescription('停止服务');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Worker::stopAll();
    }
}