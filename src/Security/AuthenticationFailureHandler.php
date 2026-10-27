<?php

namespace App\Security;

use App\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Used by both json_login (wrong credentials, inactive account) and access_token (bad token).
 */
final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            return ApiResponse::error($exception->getMessageKey(), Response::HTTP_FORBIDDEN);
        }

        $message = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessageKey()
            : 'Identifiants invalides.';

        return ApiResponse::error($message, Response::HTTP_UNAUTHORIZED, headers: ['WWW-Authenticate' => 'Bearer']);
    }
}
