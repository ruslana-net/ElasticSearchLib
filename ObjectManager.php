<?php

namespace App\ElasticSearch;

/**
 * interface ObjectManager
 *
 * @category Elasticsearch
 * @package  Elasticsearch
 * @author   Ruslan Muriev <ruslana.net@gmail.com>
 * @link     http://elasticsearch.org
 */
interface ObjectManager
{
    /**
     * PropertiesManager constructor.
     * @param \Elasticsearch\Client $client
     * @param \Doctrine\DBAL\Connection $db
     * @param int $min
     */
    public function __construct(\Elasticsearch\Client $client, \Doctrine\DBAL\Connection $db, $min = 10);

    /**
     * Add ElasticSearch object
     *
     * @param array $property
     * @return array
     */
    public function add(array $property);

    /**
     * Update ElasticSearch object
     *
     * @param array $property
     * @return array
     */
    public function update(array $property);

    /**
     * Delete ElasticSearch object
     *
     * @param integer $id
     * @return array
     */
    public function delete($id);

    /**
     * Clear all objects
     *
     * @return array
     */
    public function clear();

    /**
     * Find object by id
     *
     * @param $id
     * @return array
     */
    public function find($id);

    /**
     * Get all objects
     *
     * @return array
     */
    public function findAll();

    /**
     * Find objects by criteria
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param null $limit
     * @param null $offset
     * @return array
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null);

    /**
     * Find one object by criteria
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @return mixed
     */
    public function findOneBy(array $criteria, array $orderBy = null);

    /**
     * Get ElasticSearch index
     *
     * @return string
     */
    public function getIndex();

    /**
     * Get ElasticSearch type
     *
     * @return string
     */
    public function getType();

    /**
     * Get ElasticSearch Client
     *
     * @return \Elasticsearch\Client
     */
    public function getClient();

    /**
     * @param integer $min
     */
    public function setMin($min);

    /**
     * @return integer
     */
    public function getMin();
}