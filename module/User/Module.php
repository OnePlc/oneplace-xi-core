<?php
namespace User;

use Laminas\ApiTools\Provider\ApiToolsProviderInterface;
use Laminas\Uri\UriFactory;

class Module implements ApiToolsProviderInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap($e)
    {
        UriFactory::registerScheme('chrome-extension', 'Zend\\Uri\\Uri');
        UriFactory::registerScheme('moz-extension', 'Zend\\Uri\\Uri');

    }

    public function getAutoloaderConfig()
    {
        return [
            'Laminas\ApiTools\Autoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src',
                ],
            ],
        ];
    }
}
