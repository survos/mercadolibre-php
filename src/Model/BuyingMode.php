<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

enum BuyingMode: string
{
    case BuyItNow = 'buy_it_now';
    case Auction = 'auction';
}
