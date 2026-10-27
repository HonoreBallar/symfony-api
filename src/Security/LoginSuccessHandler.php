<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Http\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Issues a bearer token after a successful json_login.
 */
final readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private NormalizerInterface $normalizer,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();

        [$apiToken, $plainToken] = ApiToken::issue($user);
        $this->em->persist($apiToken);
        $this->em->flush();

        return ApiResponse::success([
            'token' => $plainToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $apiToken->getExpiresAt()->format(\DATE_ATOM),
            'user' => $this->normalizer->normalize($user, 'json', ['groups' => ['user:read']]),
        ], 'Connexion réussie.');
    }
}
