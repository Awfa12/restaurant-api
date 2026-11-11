<?php

namespace App\Enums;

/**
 * How a product or offer photo is shown (frontend `ImageStyle`).
 * Stored as null for the default look (cutout), so the JSON leaves it out.
 */
enum ImageStyle: string
{
    /** The dish on a transparent background (default). */
    case Cutout = 'cutout';

    /** A full photo with its own background, shown with rounded corners. */
    case Photo = 'photo';
}
