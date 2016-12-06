<?php

namespace App\ElasticSearch;

/**
 * Class PropertiesManager
 *
 * @category Elasticsearch
 * @package  Elasticsearch
 * @author   Ruslan Muriev <ruslana.net@gmail.com>
 * @link     http://elasticsearch.org
 */
class PropertiesManager implements ObjectManager
{
    const ELASTIC_INDEX = 'liveonriviera';

    const ELASTIC_TYPE = 'objects';

    protected static $Fields = [
        'id' => 'integer',
        'type' => 'integer',
        'estatetype_id' => 'integer',
        'country_id' => 'string',
        'city_id' => 'integer',
        'region_id' => 'integer',
        'rooms' => 'integer',
        'bedrooms' => 'integer',
        'guests' => 'integer',
        'size' => 'integer',
        'size_total' => 'integer',
        'price' => 'float',
        'price_standard' => 'float',
        'import_agency_id' => 'integer',
        'distances' => 'serialize',
        'translations' => 'object',
        'location' => [
            'type' => 'geo_point',
            'lat_field' => 'lat',
            'lon_field' => 'lon'
        ],
        'features' => [
            'type' => 'leftJoin',
            'table' => 'properties2features',
            'id_field' => 'property_id',
            'key' => 'feature_id',
            'value' => 'value'
        ],
        'availables' => [
            'type' => 'leftJoin',
            'table' => 'properties_availabilities',
            'id_field' => 'property_id',
            'key' => 'date',
            'value' => 'state'
        ],
    ];

    protected static $TranslationFields = [
        'name' => 'string',
        'location' => 'string',
        'description' => 'string',
    ];

    /** @var \Elasticsearch\Client */
    protected $client;

    /** @var  \Doctrine\DBAL\Connection */
    protected $db;

    /**
     * @var int
     */
    protected $min = 10;

    /**
     * PropertiesManager constructor.
     * @param \Elasticsearch\Client $client
     * @param \Doctrine\DBAL\Connection $db
     * @param int $min
     */
    public function __construct(\Elasticsearch\Client $client, \Doctrine\DBAL\Connection $db, $min = 10)
    {
        $this->client = $client;
        $this->db = $db;
        $this->min = $min;
    }

    /**
     * Clear all properties
     *
     * @return array
     */
    public function clear()
    {
        $params = ['index' => $this->getIndex()];
        if (empty($this->getClient()->indices()->exists($params))) {
            $this->createMapping();
        } else {
            $this->getClient()->indices()->delete($params);
            $this->createMapping();
        }
    }

    /**
     * Create ElasticSearch mapping
     */
    public function createMapping()
    {
        foreach (self::$Fields as $fieldName => $options) {
            if ($fieldName === 'id') {
                continue;
            }

            if (is_array($options)) {
                $type = $options['type'] === 'leftJoin' ? 'object' : $options['type'];
            } elseif ($options === 'serialize') {
                $type = 'object';
            } else {
                $type = $options;
            }

            $mapParams[$fieldName] = [
                'type' => $type
            ];
        }

        $mapping = [
            'index' => $this->getIndex(),
            'body' => [
//                'settings' => [
//                    'number_of_shards' => 3,
//                    'number_of_replicas' => 2
//                ],
                'mappings' => [
                    $this->getType() => [
//                        '_source' => [
//                            'enabled' => true
//                        ],
                        'properties' => $mapParams
                    ]
                ]
            ]
        ];

        $this->getClient()->indices()->create($mapping);
    }

    /**
     * @param array $property
     */
    public function add(array $property)
    {
        $this->update($property);
    }

    /**
     * @param array $property
     * @return array
     */
    public function update(array $property)
    {
        $bodyParams = [];
        foreach (self::$Fields as $fieldName => $options) {
            $value = array_key_exists($fieldName, $property) ? $property[$fieldName] : null;

            if (is_array($options)) {
                switch ($options['type']) {
                    case 'leftJoin':
                        $value = [];
                        $sql = 'SELECT * FROM ' . $options['table'] . ' WHERE ' . $options['id_field'] . '=' . (int)$property['id'];
                        $items = $this->db->fetchAll($sql);
                        foreach ($items as $item) {
                            $value[$item[$options['key']]] = $item[$options['value']];
                        }
                        break;
                    case 'geo_point':
                        if ($property[$options['lat_field']] && $property[$options['lon_field']]) {
                            $value = [
                                'lat' => $property[$options['lat_field']],
                                'lon' => $property[$options['lon_field']],
                            ];
                        }
                        break;
                }
            } elseif ($options === 'serialize') {
                $value = $value != '' ? unserialize($value) : [];
            } else {
                settype($value, $options);
            }

            $bodyParams[$fieldName] = $value;
        }
        $translations = \Helpers\Translations::getTranslationsByModelId($property['id']);

        $bodyParams['translations'] = [];
        foreach ($translations as $lang => $translation) {
            $trans = [];
            foreach ($translation as $fieldName => $value) {
                if (array_key_exists($fieldName, self::$TranslationFields)) {
                    settype($value, self::$TranslationFields[$fieldName]);
                    $trans[$fieldName] = $value;
                }
            }
            if (!empty($trans)) {
                $bodyParams['translations'][$lang] = $trans;
            }
        }

        $params = $this->getInitParams();
        $params['id'] = $property['id'];
        $params['body'] = $bodyParams;

        return $this->getClient()->index($params);
    }

    /**
     * @param int $id
     * @return array
     */
    public function delete($id)
    {
        $params = $this->getInitParams();
        $params['id'] = $id;

        return $this->getClient()->delete($params);
    }

    /**
     * @param $id
     * @return array
     */
    public function find($id)
    {
        $params = $this->getInitParams();
        $params['id'] = $id;

        return $this->getClient()->get($params);
    }

    /**
     * @return array
     */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /**
     * @param array $criteria
     *      'lang' => 'lang - en'
     *      'search' => 'City name - Blonville'
     *      'property_type_id' => 'id of properties_types rend, sale, longterment, ... - integer'
     *      'estatetype_id' => 'id of properties_estatetypes apartment, house, garage, ... - integer'
     *      'import_agency_id' => 'agency id - integer'
     *      'country_ids' => 'ids of countries - array ['at', 'fr']'
     *      'region_ids' => 'ids of regions - array [1,2,3]'
     *      'city_ids' => 'ids of cities - array [1,2,3]'
     *      'rooms' => 'rooms count - integer'
     *      'bedrooms' => 'bedrooms count - integer'
     *      'guests' => 'guests count - integer'
     *      'size_from' => 'size_from - integer'
     *      'size_to' => 'size_to - integer'
     *      'size_total_from' => 'size_total_from - integer'
     *      'size_total_to' => 'size_total_to - integer'
     *      'price_standard_from' => 'discount price from - integer'
     *      'price_standard_to' => 'discount price to - integer'
     *      'price_from' => 'price from - integer'
     *      'price_to' => 'price to - integer'
     *      'availables' => search by available date and state [date => state, '2016-04-21' => 1]
     *      'features' => 'search by features - [featureId => $featureValue, 15 => 1] $featureValue - int or string
     *      'distances' => 'search by distances - ['place' => $maxDistance, 'ski' => 700]
     *      https://www.elastic.co/guide/en/elasticsearch/reference/2.3/query-dsl-geo-distance-query.html
     *      'near_filter' => ["distance" => "10km","location" => ["lat" => 47.5823185000,"lon" => 12.6942207000]]
     * @param array|null $orderBy https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html
     * @param array|null $limit
     * @param null $offset
     * @return array
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $params = $this->getInitParams();

        if ($limit) {
            $params['size'] = $limit;
        }

        if ($offset) {
            $params['from'] = $offset;
        }

        $lang = array_key_exists('lang', $criteria) ? $criteria['lang'] : 'en';
        $filter = [];
        $query = [];
        $must = [];

        if (empty($criteria)) {
            $query = [
                "match_all" => []
            ];
        }

        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $must[] = ['match' => [
                "translations.$lang.name" => $criteria['search']
            ]];
        }

        if (array_key_exists('property_type_id', $criteria)) {
            $filter[] = ['term' => [
                'type' => $criteria['property_type_id']
            ]];
        }

        if (array_key_exists('estatetype_id', $criteria)) {
            $filter[] = ['term' => [
                'estatetype_id' => $criteria['estatetype_id']
            ]];
        }

        if (array_key_exists('import_agency_id', $criteria)) {
            $filter[] = ['term' => [
                'import_agency_id' => $criteria['import_agency_id']
            ]];
        }

        if (array_key_exists('country_ids', $criteria) && !empty($criteria['country_ids'])) {
            $filter[] = ['terms' => [
                'country_id' => $criteria['country_ids']
            ]];
        }

        if (array_key_exists('region_ids', $criteria) && !empty($criteria['region_ids'])) {
            $filter[] = ['terms' => [
                'region_id' => $criteria['region_ids']
            ]];
        }

        if (array_key_exists('city_ids', $criteria) && !empty($criteria['city_ids'])) {
            $filter[] = ['terms' => [
                'city_id' => $criteria['city_ids']
            ]];
        }

        if (array_key_exists('size_from', $criteria)) {
            $filter[] = [
                'range' => [
                    'size' => ['gte' => (int)$criteria['size_from']]
                ]
            ];
        }

        if (array_key_exists('size_to', $criteria)) {
            $filter[] = [
                'range' => [
                    'size' => ['lte' => (int)$criteria['size_to']]
                ]
            ];
        }

        if (array_key_exists('size_total_from', $criteria)) {
            $filter[] = [
                'range' => [
                    'size_total' => ['gte' => (int)$criteria['size_total_from']]
                ]
            ];
        }

        if (array_key_exists('size_total_to', $criteria)) {
            $filter[] = [
                'range' => [
                    'size_total' => ['lte' => (int)$criteria['size_total_to']]
                ]
            ];
        }

        if (array_key_exists('price_from', $criteria)) {
            $filter[] = [
                'range' => [
                    'price' => ['gte' => (int)$criteria['price_from']]
                ]
            ];
        }

        if (array_key_exists('price_to', $criteria)) {
            $filter[] = [
                'range' => [
                    'price' => ['lte' => (int)$criteria['price_to']]
                ]
            ];
        }

        if (array_key_exists('price_standard_from', $criteria)) {
            $filter[] = [
                'range' => [
                    'price_standard' => ['gte' => (int)$criteria['price_standard_from']]
                ]
            ];
        }

        if (array_key_exists('price_standard_to', $criteria)) {
            $filter[] = [
                'range' => [
                    'price_standard' => ['lte' => (int)$criteria['price_standard_to']]
                ]
            ];
        }

        if (array_key_exists('distances', $criteria) && !empty($criteria['distances'])) {
            foreach ($criteria['distances'] as $place => $distance) {
                $filter[] = [
                    'range' => [
                        'distances.' . $place => ['lte' => (int)$distance]
                    ]
                ];
            }
        }

        if (array_key_exists('near_filter', $criteria)) {
            $filter[] = [
                'geo_distance' => $criteria['near_filter']
            ];
        }

        if (array_key_exists('rooms', $criteria)) {
            $filter[] = ['term' => [
                'rooms' => $criteria['rooms']
            ]];
        }

        if (array_key_exists('bedrooms', $criteria)) {
            $filter[] = ['term' => [
                'bedrooms' => $criteria['bedrooms']
            ]];
        }

        if (array_key_exists('guests', $criteria)) {
            $filter[] = ['term' => [
                'guests' => $criteria['guests']
            ]];
        }

        if (array_key_exists('features', $criteria)) {
            $features = $criteria['features'];
            foreach ($features as $featureId => $feature) {
                $filter[] = ['term' => [
                    'features.' . $featureId => $feature
                ]];
            }
        }

        if (array_key_exists('availables', $criteria)) {
            $availables = $criteria['availables'];
            foreach ($availables as $date => $status) {
                $filter[] = ['term' => [
                    'availables.' . $date => $status
                ]];
            }
        }

        if (count($must) > 0) {
            $query['bool']['must'] = $must;
        }
        if (count($filter) > 0) {
            $query['bool']['filter'] = $filter;
        }

        $params['body']['query'] = $query;

        if (!empty($orderBy)) {
            $params['body']['sort'] = $orderBy;
        }

        $result = $this->getClient()->search($params);

        if (count($criteria) > 0 && $result['hits']['total'] < $this->getMin()) {
            array_pop($criteria);
            return $this->findBy($criteria, $orderBy, $limit, $offset);
        }

        return $result;
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @return array
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        $items = $this->findBy($criteria, $orderBy, 1);

        return count($items) > 0 ? array_shift($items) : [];
    }

    /**
     * @return string
     */
    public function getIndex()
    {
        return self::ELASTIC_INDEX;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return self::ELASTIC_TYPE;
    }

    /**
     * @return \Elasticsearch\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param integer $min
     */
    public function setMin($min)
    {
        $this->min = $min;
    }

    /**
     * @return integer
     */
    public function getMin()
    {
        return $this->min;
    }

    /**
     * @return array
     */
    protected function getInitParams()
    {
        return [
            'index' => $this->getIndex(),
            'type' => $this->getType(),
        ];
    }
}