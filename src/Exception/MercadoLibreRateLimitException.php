<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Exception;

/** 429. Waiting helps; retrying immediately does not. */
final class MercadoLibreRateLimitException extends MercadoLibreApiException
{
}
