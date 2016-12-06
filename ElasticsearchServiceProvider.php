<?php

namespace App\ElasticSearch;

use Silex\Application;
use Elasticsearch\ClientBuilder;
use InvalidArgumentException;
use Silex\ServiceProviderInterface;

/**
 * class ElasticsearchServiceProvider
 *
 * @category Elasticsearch
 * @package  Elasticsearch
 * @author   Ruslan Muriev <ruslana.net@gmail.com>
 * @link     http://elasticsearch.org
 */
class ElasticsearchServiceProvider implements ServiceProviderInterface
{
    protected $prefix;

    /**
     * @param string $prefix Prefix name used to register the service in Silex.
     */
    public function __construct($prefix = 'elasticsearch')
    {
        if (empty($prefix) || false === is_string($prefix)) {
            throw new InvalidArgumentException(
                sprintf('$prefix must be a non-empty string, "%s" given', gettype($prefix))
            );
        }

        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $prefix = $this->prefix;
        $params_key = sprintf('%s.params', $prefix);
        $app[$params_key] = isset($app[$params_key]) ? $app[$params_key] : [];

        $app[$prefix] = $app->share(
            function (Application $app) use ($params_key) {
                return ClientBuilder::create()->setHosts($app[$params_key])->build();
            }
        );
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function boot(Application $app)
    {
    }
}
