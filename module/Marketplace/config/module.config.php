<?php
return [
    'service_manager' => [
        'factories' => [
            \Marketplace\V1\Rest\Marketplace\MarketplaceResource::class => \Marketplace\V1\Rest\Marketplace\MarketplaceResourceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'marketplace.rest.marketplace' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/marketplace[/:marketplace_id]',
                    'defaults' => [
                        'controller' => 'Marketplace\\V1\\Rest\\Marketplace\\Controller',
                    ],
                ],
            ],
            'marketplace.rpc.auction' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/marketplace-auction',
                    'defaults' => [
                        'controller' => 'Marketplace\\V1\\Rpc\\Auction\\Controller',
                        'action' => 'auction',
                    ],
                ],
            ],
        ],
    ],
    'api-tools-versioning' => [
        'uri' => [
            0 => 'marketplace.rest.marketplace',
            1 => 'marketplace.rpc.auction',
        ],
    ],
    'api-tools-rest' => [
        'Marketplace\\V1\\Rest\\Marketplace\\Controller' => [
            'listener' => \Marketplace\V1\Rest\Marketplace\MarketplaceResource::class,
            'route_name' => 'marketplace.rest.marketplace',
            'route_identifier_name' => 'marketplace_id',
            'collection_name' => 'marketplace',
            'entity_http_methods' => [
                0 => 'GET',
                1 => 'PATCH',
                2 => 'PUT',
                3 => 'DELETE',
            ],
            'collection_http_methods' => [
                0 => 'GET',
                1 => 'POST',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => null,
            'entity_class' => \Marketplace\V1\Rest\Marketplace\MarketplaceEntity::class,
            'collection_class' => \Marketplace\V1\Rest\Marketplace\MarketplaceCollection::class,
            'service_name' => 'Marketplace',
        ],
    ],
    'api-tools-content-negotiation' => [
        'controllers' => [
            'Marketplace\\V1\\Rest\\Marketplace\\Controller' => 'HalJson',
            'Marketplace\\V1\\Rpc\\Auction\\Controller' => 'Json',
        ],
        'accept_whitelist' => [
            'Marketplace\\V1\\Rest\\Marketplace\\Controller' => [
                0 => 'application/vnd.marketplace.v1+json',
                1 => 'application/hal+json',
                2 => 'application/json',
            ],
            'Marketplace\\V1\\Rpc\\Auction\\Controller' => [
                0 => 'application/vnd.marketplace.v1+json',
                1 => 'application/json',
                2 => 'application/*+json',
            ],
        ],
        'content_type_whitelist' => [
            'Marketplace\\V1\\Rest\\Marketplace\\Controller' => [
                0 => 'application/vnd.marketplace.v1+json',
                1 => 'application/json',
            ],
            'Marketplace\\V1\\Rpc\\Auction\\Controller' => [
                0 => 'application/vnd.marketplace.v1+json',
                1 => 'application/json',
            ],
        ],
    ],
    'api-tools-hal' => [
        'metadata_map' => [
            \Marketplace\V1\Rest\Marketplace\MarketplaceEntity::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'marketplace.rest.marketplace',
                'route_identifier_name' => 'marketplace_id',
                'hydrator' => \Laminas\Hydrator\ArraySerializable::class,
            ],
            \Marketplace\V1\Rest\Marketplace\MarketplaceCollection::class => [
                'entity_identifier_name' => 'id',
                'route_name' => 'marketplace.rest.marketplace',
                'route_identifier_name' => 'marketplace_id',
                'is_collection' => true,
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'Marketplace\\V1\\Rpc\\Auction\\Controller' => \Marketplace\V1\Rpc\Auction\AuctionControllerFactory::class,
        ],
    ],
    'api-tools-rpc' => [
        'Marketplace\\V1\\Rpc\\Auction\\Controller' => [
            'service_name' => 'Auction',
            'http_methods' => [
                0 => 'GET',
            ],
            'route_name' => 'marketplace.rpc.auction',
        ],
    ],
    'api-tools-mvc-auth' => [
        'authorization' => [
            'Marketplace\\V1\\Rpc\\Auction\\Controller' => [
                'actions' => [
                    'auction' => [
                        'GET' => true,
                        'POST' => false,
                        'PUT' => false,
                        'PATCH' => false,
                        'DELETE' => false,
                    ],
                ],
            ],
        ],
    ],
];
