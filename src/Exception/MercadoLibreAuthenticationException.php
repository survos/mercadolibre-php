<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Exception;

/**
 * 401/403.
 *
 * On Mercado Libre this is very often the symptom of a REUSED refresh token.
 * Refresh tokens are single use; spending one twice invalidates the account's
 * authorization entirely, and the error says nothing about tokens rotating.
 * If you see this after a refresh that appeared to work, suspect your token store
 * before you suspect the credentials.
 */
final class MercadoLibreAuthenticationException extends MercadoLibreApiException
{
}
