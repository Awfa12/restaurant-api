<?php

namespace App\Enums;

/**
 * POST /admin/{resource}/{id}/move { direction } (API-CONTRACT §4.2).
 */
enum MoveDirection: string
{
    /** One place earlier in the list. */
    case Up = 'up';

    /** One place later in the list. */
    case Down = 'down';
}
