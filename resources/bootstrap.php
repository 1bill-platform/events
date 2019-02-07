<?php

/**
 * OneFramework
 *
 * Copyright (c) 2019 Elixant Technology Ltd.
 *
 * OneFramework is a PHP Software Development Framework created by
 * Elixant Technology for use within our Proprietary Licensed Software;
 * however we acknowledge that there's some things that shouldn't be kept
 * secret and may be useful for the Development Community as a whole. Therefore
 * we have released OneFramework under the MIT Open Source License. Please
 * refer to the LICENSE file included with this package for more info.
 *
 * @package   oneframework/events
 * @license   MIT License
 * @link      https://www.elixant.ca
 * @author    Alexander Schmautz <ceo@elixant.ca>
 * @copyright Copyright (c) 2018 Elixant Technoloy Ltd. All Rights Reserved.
 */

use OneFramework\Container\Container;
use OneFramework\Events\Dispatcher;
use OneFramework\Events\DispatcherInterface;

// Retrieve the Container Instance.
$container = Container::getInstance();

// Define the Singleton
$container->singleton(Dispatcher::class, function () use ($container){
    return new Dispatcher($container);
});

// Define any Aliases for use by way of DI or type hinting.
$aliases = [
    'events',
    '\Illuminate\Events\Dispatcher',
    '\Illuminate\Contracts\Events\Dispatcher',
    \OneFramework\Events\DispatcherInterface::class,
    \Symfony\Component\EventDispatcher\EventDispatcher::class,
    \Symfony\Component\EventDispatcher\EventDispatcherInterface::class
];

foreach ($aliases as $alias) {
    $container->alias(Dispatcher::class, $alias);
}