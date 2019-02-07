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

use Illuminate\Support\Str;
use OneFramework\Container\Container;
use OneFramework\Container\ContainerAwareInterface;
use OneFramework\Container\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Definition: Dispatcher
 *
 * The Event Dispatcher Class; based off of Symfony, but with implemented functionality
 * from Laravel.
 *
 * @package     oneframework/events
 * @subpackage  Dispatcher
 * @license     MIT License
 * @link        https://www.elixant.ca
 * @author      Alexander Schmautz <ceo@elixant.ca>
 * @copyright   Copyright (c) 2018 Elixant Technoloy Ltd. All Rights Reserved.
 */
class Dispatcher extends EventDispatcher implements DispatcherInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    
    /**
     * The registered event listeners.
     *
     * @var array
     */
    protected $listeners = [];
    
    /**
     * The wildcard listeners.
     *
     * @var array
     */
    protected $wildcards = [];
    
    /**
     * The cached wildcard listeners.
     *
     * @var array
     */
    protected $wildcardsCache = [];
    
    /**
     * Dispatcher constructor.
     *
     * @param Container|null $container
     */
    public function __construct(Container $container = null)
    {
        $this->setContainer($container ?? $this->getContainer());
    }
    
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string|array  $events
     * @param  mixed  $listener
     * @return void
     */
    public function listen($events, $listener): void
    {
        foreach ((array) $events as $event) {
            if (Str::contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][] = $this->makeListener($listener);
            }
        }
    }
    
    /**
     * Setup a wildcard listener callback.
     *
     * @param  string  $event
     * @param  mixed  $listener
     * @return void
     */
    protected function setupWildcardListen($event, $listener): void
    {
        $this->wildcards[$event][] = $this->makeListener($listener, true);
        
        $this->wildcardsCache = [];
    }
    
    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName = null): bool
    {
        return ! empty($this->listeners) && (isset($this->listeners[$eventName]) || isset($this->wildcards[$eventName]));
    }
    
    /**
     * Register an event and payload to be fired later.
     *
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function push($event, $payload = []): void
    {
        $this->listen($event.'_pushed', function () use ($event, $payload) {
            $this->dispatch($event, $payload);
        });
    }
    
    /**
     * Flush a set of pushed events.
     *
     * @param  string  $event
     * @return void
     */
    public function flush($event): void
    {
        $this->dispatch($event.'_pushed');
    }
    
    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string $subscriber
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function subscribe($subscriber): void
    {
        $subscriber = $this->resolveSubscriber($subscriber);
        
        $subscriber->subscribe($this);
    }
    
    /**
     * Resolve the subscriber instance.
     *
     * @param  object|string $subscriber
     * @return mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolveSubscriber($subscriber)
    {
        if (is_string($subscriber)) {
            return $this->container->make($subscriber);
        }
        
        return $subscriber;
    }
    
    /**
     * Fire an event until the first non-null response is returned.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @return array|null
     */
    public function until($event, $payload = []): ?array
    {
        return $this->dispatch($event, $payload, true);
    }
    
    /**
     * Fire an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function fire($event, $payload = [], $halt = false): ?array
    {
        return $this->dispatch($event, $payload, $halt);
    }
    
    /**
     * Fire an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function dispatch($event, Event $payload = null, $halt = false): ?array
    {
        // When the given "event" is actually an object we will assume it is an event
        // object and use the class as the event name and this event itself as the
        // payload to the handler, which makes object based events quite simple.
        [$event, $payload] = $this->parseEventAndPayload(
            $event, $payload
        );
        
        $responses = [];
        
        foreach ($this->getListeners($event) as $listener) {
            $response = $listener($event, $payload);
            
            // If a response is returned from the listener and event halting is enabled
            // we will just return this response, and not call the rest of the event
            // listeners. Otherwise we will add the response on the response list.
            if ($halt && $response !== null) {
                return $response;
            }
            
            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and firing every one in our sequence.
            if ($response === false) {
                break;
            }
            
            $responses[] = $response;
        }
        
        return $halt ? null : $responses;
    }
    
    /**
     * Parse the given event and payload and prepare them for dispatching.
     *
     * @param  mixed  $event
     * @param  mixed  $payload
     * @return array
     */
    protected function parseEventAndPayload($event, $payload): array
    {
        if (is_object($event)) {
            [$payload, $event] = [[$event], get_class($event)];
        }
        
        return [$event, Arr::wrap($payload)];
    }
    
    /**
     * Get all of the listeners for a given event name.
     *
     * @param  string  $eventName
     * @return array
     */
    public function getListeners($eventName = null): array
    {
        $listeners = $this->listeners[$eventName] ?? [];
        
        $listeners = array_merge(
            $listeners,
            $this->wildcardsCache[$eventName] ?? $this->getWildcardListeners($eventName)
        );
        
        return class_exists($eventName, false)
            ? $this->addInterfaceListeners($eventName, $listeners)
            : $listeners;
    }
    
    /**
     * Get the wildcard listeners for the event.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function getWildcardListeners($eventName): array
    {
        $wildcards = [];
        
        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $eventName)) {
                if (! empty($listeners)) {
                    $wildcards = array_merge($wildcards, $listeners);
                }
            }
        }
        
        return $this->wildcardsCache[$eventName] = $wildcards;
    }
    
    /**
     * Add the listeners for the event's interfaces to the given array.
     *
     * @param  string  $eventName
     * @param  array  $listeners
     * @return array
     */
    protected function addInterfaceListeners($eventName, array $listeners = []): array
    {
        foreach (class_implements($eventName) as $interface) {
            if (isset($this->listeners[$interface])) {
                foreach ($this->listeners[$interface] as $names) {
                    $listeners = array_merge($listeners, (array) $names);
                }
            }
        }
        
        return $listeners;
    }
    
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Closure|string  $listener
     * @param  bool  $wildcard
     * @return \Closure
     */
    public function makeListener($listener, $wildcard = false): callable
    {
        if (is_string($listener)) {
            return $this->createClassListener($listener, $wildcard);
        }
        
        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return $listener($event, $payload);
            }
            
            return $listener(...array_values($payload));
        };
    }
    
    /**
     * Create a class based listener using the IoC container.
     *
     * @param  string  $listener
     * @param  bool  $wildcard
     * @return \Closure
     */
    public function createClassListener($listener, $wildcard = false): callable
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return call_user_func($this->createClassCallable($listener), $event, $payload);
            }
            
            return call_user_func_array(
                $this->createClassCallable($listener), $payload
            );
        };
    }
    
    /**
     * Create the class based event callable.
     *
     * @param  string $listener
     * @return callable
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function createClassCallable($listener): callable
    {
        [$class, $method] = $this->parseClassCallable($listener);
        
        return [$this->container->make($class), $method];
    }
    
    /**
     * Parse the class listener into class and method.
     *
     * @param  string  $listener
     * @return array
     */
    protected function parseClassCallable($listener): array
    {
        return Str::parseCallback($listener, 'handle');
    }
    
    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     * @return void
     */
    public function forget($event): void
    {
        if (Str::contains($event, '*')) {
            unset($this->wildcards[$event]);
        } else {
            unset($this->listeners[$event]);
        }
    }
    
    /**
     * Forget all of the pushed listeners.
     *
     * @return void
     */
    public function forgetPushed(): void
    {
        foreach ($this->listeners as $key => $value) {
            if (Str::endsWith($key, '_pushed')) {
                $this->forget($key);
            }
        }
    }
}