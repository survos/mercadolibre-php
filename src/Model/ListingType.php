<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * Listing tier. This is a commercial decision, not a technical one: it sets the
 * commission and the exposure, and it cannot be downgraded once a listing has
 * sales.
 */
enum ListingType: string
{
    /** Free. No commission, low exposure, limited duration. */
    case Free = 'free';

    /** "Clásica" -- the usual default. */
    case Classic = 'gold_special';

    /** "Premium" -- higher commission, allows interest-free instalments. */
    case Premium = 'gold_pro';
}
