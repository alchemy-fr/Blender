<?php

namespace Blender;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class Config
{
    /**
     *
     * @var ParameterBag
     */
    protected $config;

    public function __construct($path)
    {
        $this->config = new ParameterBag(Yaml::parse($path));
    }

    public function get($key)
    {
        return $this->config->get($key);
    }
}
