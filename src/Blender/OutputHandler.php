<?php

namespace Blender;

use Symfony\Component\Console\Output\OutputInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class OutputHandler extends AbstractProcessingHandler
{

    protected $output;

    public function __construct(OutputInterface $output, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->output = $output;
    }

    public function close()
    {
        $this->output = null;
    }

    protected function write(array $record)
    {
        if (null !== $this->output)
        {
            $this->output->write((string) $record['formatted']);
        }
    }

}

