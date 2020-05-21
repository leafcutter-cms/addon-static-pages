<?php
namespace Leafcutter;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("America/Denver");

//initialize configuration
$config = new Config\Config();
$config['base_dir'] = __DIR__;
$config->readDir(__DIR__ . '/config/');
if (is_file(__DIR__ . '/config/env.yaml')) {
    $config->readFile(__DIR__ . '/config/env.yaml');
}

//initialize logger
$logger = new Logger('leafcutter');
$logger->pushHandler(
    new StreamHandler(__DIR__ . '/debug.log', Logger::DEBUG)
);

//initialize URL site and context
URLFactory::beginSite($config['site.url']);
URLFactory::beginContext(); //calling without argument sets context to site

//normalize URL
URLFactory::normalizeCurrent();

//initialize CMS context
Leafcutter::beginContext($config, $logger);
$leafcutter = Leafcutter::get();
$leafcutter->content()->addDirectory(__DIR__ . '/content');

//build response from URL
$response = $leafcutter->buildResponse(URLFactory::current());

//render response
$response->renderHeaders();
$response->renderContent();
