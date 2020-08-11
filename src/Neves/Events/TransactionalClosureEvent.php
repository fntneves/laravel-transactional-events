<?php

namespace Neves\Events;

use Closure;
use Neves\Events\Contracts\TransactionalEvent;

class TransactionalClosureEvent implements TransactionalEvent
{
    private $closure;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }
}
