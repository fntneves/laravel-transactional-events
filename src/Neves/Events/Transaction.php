<?php

namespace Neves\Events;

use Illuminate\Support\Collection;

class Transaction
{
    private Collection $dispatchedEvents;

    private ?Transaction $parent;

    public function __construct(?Transaction $parent = null)
    {
        $this->dispatchedEvents = collect();
        $this->parent = $parent;
    }

    public function setParent(Transaction $parent)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function hasParent()
    {
        return $this->parent !== null;
    }

    public function getDispatchedEvents(): Collection
    {
        return $this->dispatchedEvents;
    }

    public function addDispatchedEvent($event)
    {
        $this->dispatchedEvents->push($event);
    }

    public function countDispatchedEvents()
    {
        return $this->dispatchedEvents->count();
    }
}
