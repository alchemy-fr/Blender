<?php

namespace Blender\Process;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
//use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\CssSelector\CssSelector;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Blender\BlenderInterface;
use Blender\Database;
use Blender\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPExiftool;
use Doctrine;

class WriteMetasFromXML implements BlenderInterface
{

    /**
     * A monolog logger
     * @var Logger 
     */
    protected $logger;

    /**
     * An array of options
     * @var array 
     */
    protected $options;

    /**
     * Path to the temporary folder
     * @var string 
     */
    protected $tempFolder;

    /**
     * Path to the log folder
     * @var string 
     */
    protected $logFolder;

    /**
     * Path to the log file
     * @var string 
     */
    protected $logFileName;

    /**
     * Path to the backup folder
     * @var string 
     */
    protected $backupFolder;

    /**
     * Provides basic utility to manipulate the file system.
     * @var Filesystem 
     */
    private $filesystem;

    /**
     * SQLite database
     * @var \Blender\Database 
     */
    protected $database;

    /**
     * Configuration settings
     * @var \Blender\Config
     */
    protected $config;

    public function __construct(Config $config, Database $database, Logger $logger, ParameterBag $options)
    {
        $this->logger = $logger;
        $this->options = $options;
        $this->database = $database;
        $this->config = $config;
        $this->init();
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function getTempFolder()
    {
        return $this->tempFolder;
    }

    public function setTempFolder($tempFolder)
    {
        $this->tempFolder = $tempFolder;
    }

    public function getLogFolder()
    {
        return $this->logFolder;
    }

    public function setLogFolder($logFolder)
    {
        $this->logFolder = $logFolder;
    }

    public function getLogFileName()
    {
        return $this->logFileName;
    }

    public function setLogFileName($logFileName)
    {
        $this->logFileName = $logFileName;
    }

    public function getBackupFolder()
    {
        return $this->backupFolder;
    }

    public function setBackupFolder($backupFolder)
    {
        $this->backupFolder = $backupFolder;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function setDatabase($database)
    {
        $this->database = $database;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function blend($inputDir, $outputDir)
    {
        $inputDir = rtrim($inputDir, '/');
        $outputDir = rtrim($outputDir, '/');

        $this->filesystem->mkdir($this->tempFolder);
        $this->filesystem->mkdir($this->logFolder);

        if ( ! $this->options->get('no_backup'))
        {
            $this->filesystem->mkdir($this->backupFolder);
        }

        //parse input dir
        $finder = new Finder();

        $finder->files()
                ->name('*.jpg')
                ->name('*.xml')
                ->filter($this->filterEliminateJpgWithNoXml())
                ->in($inputDir)
                ->sort($this->sortByDate());

        $backupOneFile = false;
        foreach ($finder as $file)
        {
            $allowDuplicate = $this->options->get('allow_duplicate');

            $md5 = md5_file($file->getPathname());

            $this->logger->info(sprintf('processing file %s', $file->getPathname()));

            if ($allowDuplicate || ! $this->database->contains($md5))
            {
                $this->database->insert($md5);

                $this->copyToTempDir($file);

                $this->logger->info(sprintf('inserting file in database %s', $file->getPathname()));

                if ( ! $this->options->get('no_backup') && $file->getExtension() === 'jpg')
                {
                    $this->backupFile($file);
                    $backupOneFile = true;
                }
            }
            else
            {
                $this->logger->info(sprintf('duplicate file %s', $file->getPathname()));
            }
        }


        //parse temp dir
        $finder = new Finder();

        $finder->files()
                ->name('*.jpg')
                ->in($this->tempFolder);

        //merge xml & meta
        foreach ($finder as $file)
        {
            $cmd = $this->generateExifCmd($file);

            $this->logger->info(sprintf('execute cmd %s', $cmd));

            if (null === trim(shell_exec($cmd)))
            {
                $this->logger->info(sprintf('failed to execute the cmd %s', $cmd));
            }

            $this->copyToDir($file, $outputDir);
        }
    }

    /**
     * Copy a file to a directory
     * 
     * @param \SplFileInfo $file File to copy
     * @param string $dir Destination
     */
    private function copyToDir(\SplFileInfo $file, $dir)
    {
        $dest = $dir . '/' . $file->getBasename();
        $this->filesystem->copy($file->getPathname(), $dest);
        $this->logger->info(sprintf('copy filename %s to %s', $file->getPathname(), $dest));
    }

    /**
     * Copy a file to the temporary folder
     * 
     * @param \SplFileInfo $file File to copy
     */
    private function copyToTempDir(\SplFileInfo $file)
    {
        $this->copyToDir($file, $this->tempFolder);
    }

    /**
     * Copy a file to the backup folder
     * 
     * @param \SplFileInfo $file File to copy
     */
    private function backupFile(\SplFileInfo $file)
    {
        $this->copyToDir($file, $this->backupFolder);
        $this->logger->info(sprintf('backup file %s to', $file->getPathname()));
    }

    /**
     * `Return a Closure which sort file by date
     * 
     * @return Closure 
     */
    private function sortByDate()
    {
        return function (\SplFileInfo $a, \SplFileInfo $b)
                {
                    return $a->getMTime() > $b->getMTime();
                };
    }

    /**
     * Return a Closure which bypass file with no XML associated
     * 
     * @return Closure 
     */
    private function filterEliminateJpgWithNoXml()
    {
        return function (\SplFileInfo $file)
                {
                    if ($file->getExtension() !== 'xml')
                    {
                        $fileName = sprintf('%s/%sxml'
                                , $file->getPath()
                                , $file->getBasename($file->getExtension())
                        );

                        return file_exists($fileName);
                    }
                    return true;
                };
    }

    /**
     * Return associated XML File content 
     * 
     * @param \SplFileInfo $file wanted file
     * @return \DOMDocument 
     */
    private function getAssociatedXmlFromFile(\SplFileInfo $file)
    {
        $xmlFile = new \SplFileInfo(sprintf('%s/%sxml'
                                , $file->getPath()
                                , $file->getBasename($file->getExtension())
                ));
        $document = new \DOMDocument();
        $document->loadXML(file_get_contents($xmlFile->getPathname()));

        return $document;
    }

    /**
     * Extract metadatas from XML
     * 
     * @param \DOMDocument $document wanted document
     * @return array 
     */
    private function extractDatasFromXML(\DOMDocument $document)
    {
        $datas = array();

        $xpath = new \DOMXPath($document);

        $xPathQuery = CssSelector::toXPath('description > *');

        $structure = $this->config->get('structure');

        foreach ($xpath->query($xPathQuery) as $node)
        {
            $nodeName = $node->nodeName;
            $value = $node->nodeValue;

            $meta = isset($structure[$nodeName]) ? $structure[$nodeName] : null;
            $isMulti = ! isset($meta['multi']) ? false :  ! ! $meta['multi'];

            if ( ! $meta)
            {
                $this->logger->log(sprintf('undefined meta name %s', $nodeName));
                continue;
            }

            if ( ! isset($datas[$nodeName]))
            {
                $datas[$nodeName] = array(
                    'values' => array(),
                    'meta_src' => $meta['src'],
                    'multi' => $isMulti
                );
            }
            if ($nodeName == 'Date' ||
                    $nodeName == 'DatePrisedeVue')
            {
                $value = str_replace('/', ':', $value);
            }

            $datas[$nodeName]['values'][] = $value;
        }

        return $datas;
    }

    /**
     * Generate the exifTool command to write associated XML datas
     * 
     * @param \SplFileInfo $file the file to write
     * @return string 
     */
    private function generateExifCmd(\SplFileInfo $file)
    {
        $document = $this->getAssociatedXmlFromFile($file);
        $datas = $this->extractDatasFromXML($document);
        $subCMD = $this->generateSubCmdFromDatas($datas);

        $exiftoolBinary = __DIR__ . '/../../../vendor/alchemy/exiftool/exiftool';

        $cmd = $exiftoolBinary . ' -m -overwrite_original ';
        $cmd .= ' -codedcharacterset=utf8 ';
        $cmd .= $subCMD . ' ' . escapeshellarg($file);

        echo $cmd . "\n";
        return $cmd;
    }

    /**
     * Generate sub part of exifTool command 
     * 
     * @param array $datas datas to write
     * @return string 
     */
    private function generateSubCmdFromDatas(array $datas)
    {
        $subCMD = '';

        foreach ($datas as $field)
        {
            $multi = $field['multi'];
            $values = $field['values'];
            $metaSrc = $field['meta_src'];

            if ($multi)
            {
                foreach ($values as $value)
                {
                    $subCMD .= ' -' . $metaSrc . '=';
                    $subCMD .= escapeshellarg($value) . ' ';
                }
            }
            else
            {
                $value = array_pop($values);
                $subCMD .= ' -' . $metaSrc . '=';
                $subCMD .= escapeshellarg($value) . ' ';
            }
        }

        return $subCMD;
    }

    /**
     * Contructor initialization 
     */
    private function init()
    {
        $this->filesystem = new Filesystem();

        $this->tempFolder = sys_get_temp_dir() . '/blender/copy';
        $this->logFolder = sys_get_temp_dir() . '/blender/log';
        $this->backupFolder = sys_get_temp_dir() . '/blender/backup';
        $this->logFileName = $this->logFolder . '/WriteMetasFromXML.log';

        $this->logger->pushHandler(
                new StreamHandler($this->logFileName, Logger::WARNING)
        );
    }

}