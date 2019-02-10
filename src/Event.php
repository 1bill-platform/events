<?php namespace OneFramework\Events;

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

use OneFramework\Container\ContainerAwareInterface;
use OneFramework\Container\Traits\ContainerAwareTrait;

/**
 * Class Definition: Event
 *
 * ${CARET}
 *
 * @package     oneframework/events
 * @subpackage  Event
 * @license     MIT License
 * @link        https://www.elixant.ca
 * @author      Alexander Schmautz <ceo@elixant.ca>
 * @copyright   Copyright (c) 2018 Elixant Technoloy Ltd. All Rights Reserved.
 */
abstract class Event implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    
    /**
     * Registers the event listeners using the given dispatcher instance.
     *
     * @param  Dispatcher  $dispatcher
     * @return void
     */
    abstract public function subscribe(Dispatcher $dispatcher);
    
    /**
     * Dynamically retrieve objects from the container.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->container($key);
    }
}