<?php

namespace Manzadey\Larasentry\Facades;

use Illuminate\Support\Facades\Facade;

class Larasentry extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'larasentry';
    }
}
