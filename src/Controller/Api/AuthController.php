<?php

namespace App\Controller\Api;

use App\Dto\ForgotPasswordInput;
use App\Dto\RegisterInput;
use App\Dto\ResetPasswordInput;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\OpenApi\ErrorResponse;
use App\OpenApi\SuccessResponse;
use App\Repository\ApiTokenRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\AuthMailer;
use App\Service\UserRegistration;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth', name: 'api_auth_', format: 'json')]
#[OA\Tag(name: 'Auth')]
final class AuthController extends ApiController
{
    private const array CONTEXT = ['groups' => ['user:read']];

    public function __construct(
        private readonly UserRepository $users,
        private readonly ApiTokenRepository $apiTokens,
        private readonly PasswordResetTokenRepository $resetTokens,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
        private readonly AuthMailer $mailer,
        private readonly UserRegistration $registration,
    ) {
    }

    /**
     * Handled by the json_login authenticator (see security.yaml); this method is never executed.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    #[Security(name: null)]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Admin1234!'),
        ],
    ))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Logged in: use data.token as "Authorization: Bearer <token>"', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Connexion réussie.'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'token', type: 'string'),
            new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer'),
            new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
            new OA\Property(property: 'user', ref: new Model(type: User::class, groups: ['user:read'])),
        ], type: 'object'),
    ]))]
    #[ErrorResponse(Response::HTTP_UNAUTHORIZED, 'Invalid credentials')]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Account not active')]
    public function login(): never
    {
        throw new \LogicException('This code should never be reached: json_login handles this route.');
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[Security(name: null)]
    #[SuccessResponse(Response::HTTP_CREATED, 'Account created, a confirmation e-mail has been sent', User::class, ['user:read'])]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'E-mail already used')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed')]
    public function register(#[MapRequestPayload] RegisterInput $input): JsonResponse
    {
        $user = $this->registration->register($input->name, $input->email, $input->password);

        return $this->success($user, 'Compte créé. Consultez vos e-mails pour confirmer votre adresse.', Response::HTTP_CREATED, context: self::CONTEXT);
    }

    /**
     * Target of the signed link sent by e-mail after registration.
     */
    #[Route('/verify-email', name: 'verify_email', methods: ['GET'])]
    #[Security(name: null)]
    #[OA\Parameter(name: 'id', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: '_expiration', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: '_hash', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[SuccessResponse(Response::HTTP_OK, 'E-mail confirmed', User::class, ['user:read'])]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid or expired link')]
    public function verifyEmail(Request $request, UriSigner $uriSigner): JsonResponse
    {
        $user = $uriSigner->checkRequest($request) ? $this->users->find($request->query->getString('id')) : null;

        if (null === $user) {
            throw new BadRequestHttpException('Lien de confirmation invalide ou expiré.');
        }

        $this->registration->confirmEmail($user);

        return $this->success($user, 'Adresse e-mail confirmée. Vous pouvez vous connecter.', context: self::CONTEXT);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[SuccessResponse(Response::HTTP_OK, 'Current user', User::class, ['user:read'])]
    #[ErrorResponse(Response::HTTP_UNAUTHORIZED, 'Not authenticated')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->success($user, 'Utilisateur connecté récupéré avec succès.', context: self::CONTEXT);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[SuccessResponse(Response::HTTP_OK, 'Logged out: the token is revoked')]
    #[ErrorResponse(Response::HTTP_UNAUTHORIZED, 'Not authenticated')]
    public function logout(Request $request): JsonResponse
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', $request->headers->get('Authorization', ''), $matches)) {
            $this->apiTokens->deleteByPlainToken($matches[1]);
        }

        return $this->success(null, 'Déconnexion réussie.');
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    #[Security(name: null)]
    #[SuccessResponse(Response::HTTP_OK, 'Reset e-mail sent if the account exists')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed')]
    public function forgotPassword(#[MapRequestPayload] ForgotPasswordInput $input): JsonResponse
    {
        $user = $this->users->findOneByEmail($input->email);

        // Same answer whether the account exists or not, so e-mails cannot be enumerated.
        if (null !== $user && $user->isActive() && $user->isEmailVerified()) {
            $this->resetTokens->deleteAllForUser($user);

            [$resetToken, $plainToken] = PasswordResetToken::issue($user);
            $this->em->persist($resetToken);
            $this->em->flush();

            $this->mailer->sendPasswordReset($user, $plainToken);
        }

        return $this->success(null, 'Si un compte actif existe avec cette adresse, un e-mail de réinitialisation a été envoyé.');
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    #[Security(name: null)]
    #[SuccessResponse(Response::HTTP_OK, 'Password changed, all sessions are revoked')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid or expired token')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed')]
    public function resetPassword(#[MapRequestPayload] ResetPasswordInput $input): JsonResponse
    {
        $resetToken = $this->resetTokens->findOneByPlainToken($input->token);

        if (null === $resetToken || $resetToken->isExpired()) {
            throw new BadRequestHttpException('Jeton de réinitialisation invalide ou expiré.');
        }

        $user = $resetToken->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->password));
        $this->em->flush();

        // One-time token, and every existing session is logged out.
        $this->resetTokens->deleteAllForUser($user);
        $this->apiTokens->deleteAllForUser($user);

        return $this->success(null, 'Mot de passe modifié. Vous pouvez vous connecter avec votre nouveau mot de passe.');
    }
}
