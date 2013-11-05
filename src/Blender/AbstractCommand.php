<?php

namespace Blender;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command
{

    public function __construct($name)
    {
        parent::__construct($name);

        $this
                ->addArgument(
                        'input_dir'
                        , null
                        , InputArgument::REQUIRED
                        , 'Input directory'
                        , null
                )
                ->addArgument(
                        'output_dir'
                        , null
                        , InputArgument::REQUIRED
                        , 'Output directory'
        );
    }

}
