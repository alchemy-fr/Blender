<?php

namespace Blender;

use Doctrine;
use Symfony\Component\Filesystem\Filesystem;

class Database
{

    /**
     *
     * @var Doctrine\DBAL\Connection
     */
    protected $conn;
    protected $params;

    public function __construct($connectionParams, Doctrine\DBAL\Configuration $config)
    {
        $this->conn = Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
        $this->params = $connectionParams;
        $this->init();
    }

    private function init()
    {
        if ( ! file_exists($this->params['path']))
        {
            $filesystem = new Filesystem();
            $filesystem->touch($this->params['path']);
            chmod($this->params['path'], 0777);
        }
        $query      = 'CREATE TABLE IF NOT EXISTS blender(id INTEGER PRIMARY KEY ASC, md5 TEXT);';
        $this->conn->executeQuery($query);
    }

    public function contains($md5)
    {
        $query = 'SELECT * FROM blender WHERE md5 = "' . $md5 . '"';

        $rs = $this->conn->fetchArray($query);

        return $rs ? count($rs) > 0 : $rs;
    }

    public function insert($md5)
    {
        $query = 'INSERT INTO blender (md5) values ("' . $md5 . '");';

        $this->conn->executeQuery($query);
    }

    public function getPath()
    {
        return $this->params['path'];
    }

}

