<?php

namespace App\Controller\Api;

use App\Dto\PaginationQuery;
use App\Dto\UserUpdateInput;
use App\Entity\User;
use App\OpenApi\ErrorResponse;
use App\OpenApi\SuccessResponse;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User management, admins only.
 */
#[Route('/api/users', name: 'api_user_', format: 'json')]
#[IsGranted(User::ROLE_ADMIN)]
#[OA\Tag(name: 'User (admin)')]
final class UserController extends ApiController
{
    private const array CONTEXT = ['groups' => ['user:read']];
    private const string NOT_FOUND = 'Utilisateur introuvable.';

    public function __construct(
        private readonly UserRepository $users,
        private readonly ApiTokenRepository $apiTokens,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[SuccessResponse(Response::HTTP_OK, 'Paginated list of users', User::class, ['user:read'], paginated: true)]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Admins only')]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PaginationQuery $query = new PaginationQuery()): JsonResponse
    {
        return $this->paginated(
            $this->users->paginate($query->page, $query->pageSize),
            $query->page,
            $query->pageSize,
            'Utilisateurs récupérés avec succès.',
            self::CONTEXT,
        );
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[SuccessResponse(Response::HTTP_OK, 'User details', User::class, ['user:read'])]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'User not found')]
    public function show(#[MapEntity(message: self::NOT_FOUND)] User $user): JsonResponse
    {
        return $this->success($user, 'Utilisateur récupéré avec succès.', context: self::CONTEXT);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    #[SuccessResponse(Response::HTTP_OK, 'User updated (name, roles, isActive)', User::class, ['user:read'])]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'An admin cannot change their own roles or status')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'User not found')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed')]
    public function update(
        #[MapEntity(message: self::NOT_FOUND)] User $user,
        #[MapRequestPayload] UserUpdateInput $input,
        #[CurrentUser] User $currentUser,
    ): JsonResponse {
        if ($user === $currentUser && (null !== $input->roles || null !== $input->isActive)) {
            throw new BadRequestHttpException('Vous ne pouvez pas modifier vos propres rôles ni votre statut.');
        }

        if (null !== $input->name) {
            $user->setName($input->name);
        }
        if (null !== $input->roles) {
            $user->setRoles($input->roles);
        }
        if (null !== $input->isActive) {
            $user->setIsActive($input->isActive);
        }
        $this->em->flush();

        if (!$user->isActive()) {
            $this->apiTokens->deleteAllForUser($user);
        }

        return $this->success($user, 'Utilisateur mis à jour avec succès.', context: self::CONTEXT);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[SuccessResponse(Response::HTTP_OK, 'User deleted')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'An admin cannot delete their own account')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'User not found')]
    public function delete(#[MapEntity(message: self::NOT_FOUND)] User $user, #[CurrentUser] User $currentUser): JsonResponse
    {
        if ($user === $currentUser) {
            throw new BadRequestHttpException('Vous ne pouvez pas supprimer votre propre compte.');
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->success(null, 'Utilisateur supprimé avec succès.');
    }
}
