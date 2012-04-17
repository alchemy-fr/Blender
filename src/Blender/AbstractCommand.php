<?php

namespace Blender;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command
{
  public function __construct($name)
  {
    parent::__construct($name);

    $this
            ->addOption(
                    'input_dir'
                    , null
                    , InputOption::VALUE_REQUIRED
                    , 'precise the input directory'
                    , array()
            )
            ->addOption(
                    'output_dir'
                    , null
                    , InputOption::VALUE_REQUIRED
                    , 'precise the output directory'
    );
  }
  
}