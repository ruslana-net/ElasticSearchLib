<?php

namespace App\ElasticSearch;

use Knp\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class Command
 *
 * @category Elasticsearch
 * @package  Elasticsearch
 * @author   Ruslan Muriev <ruslana.net@gmail.com>
 * @link     http://elasticsearch.org
 */
class Command extends BaseCommand
{
    protected $input;
    protected $app;

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('elasticsearch:update:properties')
            ->setDescription('ElasticSearch cmd')
            ->addOption(
                'clear',
                null,
                InputOption::VALUE_OPTIONAL,
                'Clear all properties --clear=all'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Max num updated objects'
            )
            ->addOption(
                'offset',
                null,
                InputOption::VALUE_OPTIONAL,
                'Offset updated objects'
            )
            ->addOption(
                'order-by',
                null,
                InputOption::VALUE_OPTIONAL,
                'Objects Order by'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->app = $this->getSilexApplication();
        $this->updateProperties();
    }

    /**
     * Update all properties
     */
    protected function updateProperties()
    {
        $sql = 'SELECT * FROM properties';
        if ($orderBy = $this->input->getOption('order-by')) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit = $this->input->getOption('limit')) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset = $this->input->getOption('offset')) {
            $sql .= ' OFFSET ' . $offset;
        }
        $properties = $this->app['db']->fetchAll($sql);

        /** @var PropertiesManager $propertiesManager */
        $propertiesManager = $this->app['elastic_properties_manager'];

        //Clear all properties
        if ($this->input->getOption('clear') === 'all') {
            $propertiesManager->clear();
        }

        foreach ($properties as $property) {
            $propertiesManager->update($property);
        }
    }
}