<?php

namespace Blender\Command;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Doctrine;
use Blender\OutputHandler;
use Blender\Process;
use Blender\Config;
use Blender\Database;
use Blender;

class WriteMetasFromXML extends Blender\AbstractCommand
{

    protected function configure()
    {
        $this
                ->setDescription(sprintf('Write metadatas in documents from an associated XML file which has the same name.
         You can edit metadatas configuration file structure here from project root %s', '/ressources/config/WriteMetasFromXML.config.yml'))
                ->addOption(
                        'no_backup'
                        , null
                        , InputOption::VALUE_NONE
                        , 'backups original files'
                )
                ->addOption(
                        'allow_duplicate'
                        , null
                        , InputOption::VALUE_NONE
                        , 'allow duplicate files'
                )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $options = array(
            'no_backup'       => $input->getOption('no_backup')
            , 'allow_duplicate' => $input->getOption('allow_duplicate')
        );

        $logger = new Logger('WriteMetasFromXML');
        $logger->pushHandler(new OutputHandler($output));

        $database = new Database(
                        array(
                            'path'   => __DIR__ . '/../../../ressource/db/WriteMetasFromXML.sqlite',
                            'driver' => 'pdo_sqlite'
                        ),
                        new Doctrine\DBAL\Configuration()
        );

        $config = new Config(__DIR__ . '/../../../ressource/config/WriteMetasFromXML.config.yml');

        $blender = new Process\WriteMetasFromXML(
                        $config
                        , $database
                        , $logger
                        , new ParameterBag($options)
        );
        $blender->blend($input->getOption('input_dir'), $input->getOption('output_dir'));
    }

}