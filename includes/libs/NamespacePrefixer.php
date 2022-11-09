<?php

require_once '/Users/jewel/.composer/vendor/autoload.php';

require_once __DIR__ . "/google-api-client/vendor/autoload.php";

use Symfony\Component\Finder\Finder;

class NamespacePrefixer
{
    protected $ns;
    protected $path;
    protected $excludes;
    protected $contains;

    public function find($ns)
    {
        $this->contains = $ns;

        return $this;
    }

    public function in($path)
    {
        $this->path = $path;

        return $this;
    }

    public function exclude($path)
    {
        $this->excludes = $path;

        return $this;
    }

    public function setNamespacePrefix($ns)
    {
        $this->ns = $ns;

        foreach ($this->getFiles() as $file) {
            $this->replaceNamespace($file->getRealPath());
        }
    }

    protected function getFiles()
    {
        return Finder::create()
            ->in($this->path)
            ->exclude($this->excludes)
            ->contains($this->contains)
            ->name('*.php');
    }

    protected function replaceNamespace($path)
    {
        $search = [];

        foreach ($this->contains as $replaceable) {
            $search[] = 'namespace ' . $replaceable;
            $search[] = 'use ' . $replaceable;

            $replace[] = 'namespace ' . $this->ns . '\\' . $replaceable;
            $replace[] = 'use ' . $this->ns . '\\' . $replaceable;
        }

        $this->replaceIn($path, $search, $replace);
    }

    protected function replaceIn($path, $search, $replace)
    {
        if (file_exists($path)) {
            file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
        }
    }

}

(new NamespacePrefixer)
    ->find([
        'Auth',
        'google',
        'Google',
        'GuzzleHttp',
        'Firebase',
        'Psr',
        'phpseclib',
        'ParagonIE',
        'Monolog',
        'JWT',
        'Elastica',
        'Aws',
        'Doctrine',
        'PhpAmqpLib',
        'AMQPExchange',
        'Gelf',
        'Stash'
    ])->in(__DIR__ . '/google-api-client/vendor')
    ->exclude(['composer', 'symfony'])
    ->setNamespacePrefix('FluentSmtpLib');
