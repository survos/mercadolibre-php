<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/** Mercado Libre's entire condition vocabulary. Far coarser than eBay's eight values. */
enum ItemCondition: string
{
    case NewItem = 'new';
    case Used = 'used';
    case NotSpecified = 'not_specified';
}
