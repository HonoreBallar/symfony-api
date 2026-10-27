<?php

namespace App\Security;

use App\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Answers 401 when a protected route is called without a token.
 */
final class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return ApiResponse::error('Authentification requise.', Response::HTTP_UNAUTHORIZED, headers: ['WWW-Authenticate' => 'Bearer']);
    }
}
