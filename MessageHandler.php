<?php

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace Civ13\Interfaces;

use Discord\Parts\Channel\Message;
use React\Promise\Promise;

interface MessageHandlerInterface extends HandlerInterface
{
    public function handle(Message $message): ?Promise;
    public function checkRank(Message $message, array $allowed_ranks = []): ?Promise;
}

namespace Civ13;

use Civ13\Interfaces\messageHandlerInterface;
use Discord\Parts\Channel\Message;
use React\Promise\Promise;

class MessageHandler extends Handler implements MessageHandlerInterface
{
    protected Civ13 $civ13;
    protected array $permissions;
    protected array $methods;

    public function __construct(Civ13 &$civ13, array $handlers = [], array $permissions = [], array $methods = [])
    {
        $this->civ13 = $civ13;
        parent::__construct($handlers);
        $this->permissions = $permissions;
        $this->methods = $methods;
    }

    public function get(): array
    {
        return [$this->handlers, $this->permissions, $this->methods];
    }

    public function set(array $handlers, array $permissions = [], array $methods = []): static
    {
        parent::set($handlers);
        $this->permissions = $permissions;
        $this->methods = $methods;

        return $this;
    }

    public function pull(int|string $index, ?callable $defaultCallables = null, array $defaultPermissions = null, array $defaultMethods = null): array
    {
        $return = [];
        $return[] = parent::pull($index, $defaultCallables);

        if (isset($this->permissions[$index])) {
            $defaultPermissions = $this->permissions[$index];
            unset($this->permissions[$index]);
        }
        $return[] = $defaultPermissions;

        if (isset($this->methods[$index])) {
            $defaultMethods = $this->methods[$index];
            unset($this->methods[$index]);
        }
        $return[] = $defaultMethods;

        return $return;
    }

    public function fill(array $commands, array $handlers, array $permissions = [], array $methods = []): static
    {
        if (count($commands) !== count($handlers)) {
            throw new \Exception('Commands and Handlers must be the same length.');
            return $this;
        }
        foreach($commands as $command) {
            parent::pushHandler(array_shift($handlers), $command);
            $this->pushPermission(array_shift($permissions), $command);
            $this->pushMethod($methods, $command);
        }
        return $this;
    }
    
    public function pushPermission(array $permissions, int|string|null $command = null): ?static
    {
        if ($command) $this->permissions[$command] = $permissions;
        else $this->permissions[] = $permissions;
        return $this;
    }

    public function pushMethod(string $method, int|string|null $command = null): ?static
    {
        if ($command) $this->methods[$command] = $method;
        else $this->methods[] = $method;
        return $this;
    }

    public function first(): array
    {
        $toArray = $this->toArray();
        $return = [];
        $return[] = array_shift(array_shift($toArray) ?? []);
        $return[] = array_shift(array_shift($toArray) ?? []);
        $return[] = array_shift(array_shift($toArray) ?? []);
        return $return;
    }
    
    public function last(): array
    {
        $toArray = $this->toArray();
        $return = [];
        $return[] = array_pop(array_shift($toArray) ?? []);
        $return[] = array_pop(array_shift($toArray) ?? []);
        $return[] = array_pop(array_shift($toArray) ?? []);
        return $return;
    }

    public function filter(callable $callback): static
    {
        $static = new static($this->civ13, []);
        foreach ($this->handlers as $command => $handler)
            if ($callback($command, $handler))
                $static->pushHandler($handler, $command);
        return $static;
    }

    public function find(callable $callback): array
    {
        foreach ($this->handlers as $index => $handler)
            if ($callback($handler))
                return [$handler, $this->permissions[$index] ?? [], $this->methods[$index] ?? 'str_starts_with'];
        return [];
    }

    public function clear(): static
    {
        parent::clear();
        $this->permissions = [];
        $this->methods = [];
        return $this;
    }
    
    // TODO: Review this method
    public function map(callable $callback): static
    {
        $arr = array_combine(array_keys($this->handlers), array_map($callback, array_values($this->toArray())));
        return new static($this->civ13, array_shift($arr) ?? [], array_shift($arr) ?? [], array_shift($arr) ?? []);
    }

    /**
     * @throws Exception if toArray property does not exist
     */
    public function merge(object $handler): static
    {
        if (! property_exists($handler, 'toArray')) {
            throw new \Exception('Handler::merge() expects parameter 1 to be an object with a method named "toArray", ' . gettype($handler) . ' given');
            return $this;
        }
        $toArray = $handler->toArray();
        $this->handlers = array_merge($this->handlers, array_shift($toArray));
        $this->permissions = array_merge($this->permissions, array_shift($toArray));
        $this->methods = array_merge($this->methods, array_shift($toArray));
        return $this;
    }

    public function toArray(): array
    {
        $toArray = parent::toArray();
        $toArray[] = $this->permissions;
        $toArray[] = $this->methods;
        return $toArray;
    }

    public function offsetGet(int|string $index): array
    {
        $return = parent::offsetGet($index);
        $return[] = $this->permissions[$index] ?? null;
        $return[] = $this->methods[$index] ?? null;
        return $return;
    }
    
    public function offsetSet(int|string $index, callable $callback, ?array $permissions = [], ?string $method = 'str_starts_with'): static
    {
        parent::offsetSet($index, $callback);
        $this->permissions[$index] = $permissions;
        $this->methods[$index] = $method;
        return $this;
    }
    
    public function setOffset(int|string $newOffset, callable $callback, ?array $permissions = [], ?string $method = 'str_starts_with'): static
    {
        parent::setOffset($newOffset, $callback);
        if ($offset = $this->getOffset($callback) === false) $offset = $newOffset;
        unset($this->permissions[$offset]);
        unset($this->methods[$offset]);
        $this->permissions[$newOffset] = $permissions;
        $this->methods[$newOffset] = $method;
        return $this;
    }

    public function __debugInfo(): array
    {
        return ['civ13' => isset($this->civ13) ? $this->civ13 instanceof Civ13 : false, 'handlers' => array_keys($this->handlers)];
    }

    //Unique to MessageHandler
    
    public function handle(Message $message): ?Promise
    {
        $message_filtered = $this->civ13->filterMessage($message);
        foreach ($this->handlers as $command => $callback) {
            switch ($this->methods[$command]) {
                case 'str_contains':
                    $method_func = function () use ($message, $message_filtered, $command, $callback): ?Promise
                    {
                        if (str_contains($message_filtered['message_content_lower'], $command)) 
                            return $callback($message, $message_filtered); // This is where the magic happens
                        return null;
                    };
                    break;
                case 'str_starts_with':
                default:
                    $method_func = function () use ($message, $message_filtered, $command, $callback): ?Promise
                    {
                        if (str_starts_with($message_filtered['message_content_lower'], $command)) 
                            return $callback($message, $message_filtered); // This is where the magic happens
                        return null;
                    };
            }
            $permissions = $this->permissions['command'] ?? [];
            if ($rejected = $this->checkRank($message, $permissions)) return $rejected;
            if ($promise = $method_func()) return $promise;
        }
        if (empty($this->handlers)) $this->civ13->logger->info('No message handlers found!');
        return null;
    }

    public function checkRank(Message $message, array $allowed_ranks = []): ?Promise
    {
        if (empty($allowed_ranks)) return null;
        $resolved_ranks = [];
        foreach ($allowed_ranks as $rank) if (isset($this->civ13->role_ids[$rank])) $resolved_ranks[] = $this->civ13->role_ids[$rank];
        foreach ($message->member->roles as $role) if (in_array($role->id, $resolved_ranks)) return null;
        return $message->reply('Rejected! You need to have at least the <@&' . $this->civ13->role_ids[array_pop($allowed_ranks)] . '> rank.');
    }
}