<?php

namespace App\Controller\Api;

use App\Dto\CategoryInput;
use App\Dto\PaginationQuery;
use App\Entity\Category;
use App\Entity\User;
use App\OpenApi\ErrorResponse;
use App\OpenApi\SuccessResponse;
use App\Repository\CategoryRepository;
use App\Service\CategoryCoverStorage;
use App\Validator\CoverImage;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/categories', name: 'api_category_', format: 'json')]
#[OA\Tag(name: 'Category')]
final class CategoryController extends ApiController
{
    private const array CONTEXT = ['groups' => ['category:read']];
    private const string NOT_FOUND = 'Catégorie introuvable.';

    public function __construct(
        private readonly CategoryRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly CategoryCoverStorage $coverStorage,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[SuccessResponse(Response::HTTP_OK, 'Paginated list of categories', Category::class, ['category:read'], paginated: true)]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid pagination parameters')]
    public function list(#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PaginationQuery $query = new PaginationQuery()): JsonResponse
    {
        return $this->paginated(
            $this->repository->paginate($query->page, $query->pageSize),
            $query->page,
            $query->pageSize,
            'Catégories récupérées avec succès.',
            self::CONTEXT,
        );
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[SuccessResponse(Response::HTTP_OK, 'Category details', Category::class, ['category:read'])]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Category not found')]
    public function show(#[MapEntity(message: self::NOT_FOUND)] Category $category): JsonResponse
    {
        return $this->success($category, 'Catégorie récupérée avec succès.', context: self::CONTEXT);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Admins only')]
    #[SuccessResponse(Response::HTTP_CREATED, 'Category created. Send JSON, or multipart/form-data to attach a "cover" image at the same time', Category::class, ['category:read'])]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'A category with this name already exists')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed (fields or cover image: JPEG, PNG or WebP, 5 MB max)')]
    public function create(
        #[MapRequestPayload] CategoryInput $input,
        #[MapUploadedFile([new CoverImage()])] ?UploadedFile $cover = null,
    ): JsonResponse {
        $this->assertNameAvailable($input->name);

        $category = new Category($input->name, $input->description);
        if (null !== $cover) {
            $this->coverStorage->replace($category, $cover);
        }
        $this->em->persist($category);
        $this->em->flush();

        return $this->success($category, 'Catégorie créée avec succès.', Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_category_show', ['id' => $category->getId()]),
        ], self::CONTEXT);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Admins only')]
    #[SuccessResponse(Response::HTTP_OK, 'Category updated', Category::class, ['category:read'])]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Category not found')]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'A category with this name already exists')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed')]
    public function update(#[MapEntity(message: self::NOT_FOUND)] Category $category, #[MapRequestPayload] CategoryInput $input): JsonResponse
    {
        $this->assertNameAvailable($input->name, $category);

        $category->setName($input->name)->setDescription($input->description);
        $this->em->flush();

        return $this->success($category, 'Catégorie mise à jour avec succès.', context: self::CONTEXT);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Admins only')]
    #[SuccessResponse(Response::HTTP_OK, 'Category deleted')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Category not found')]
    public function delete(#[MapEntity(message: self::NOT_FOUND)] Category $category): JsonResponse
    {
        $this->em->remove($category);
        $this->em->flush();
        $this->coverStorage->remove($category);

        return $this->success(null, 'Catégorie supprimée avec succès.');
    }

    #[Route('/{id}/cover', name: 'cover_upload', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[SuccessResponse(Response::HTTP_OK, 'Cover uploaded (replaces the previous one)', Category::class, ['category:read'])]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Admins only')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Category not found')]
    #[ErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'Missing or invalid image (JPEG, PNG or WebP, 5 MB max)')]
    public function uploadCover(
        #[MapEntity(message: self::NOT_FOUND)] Category $category,
        #[MapUploadedFile([new CoverImage()])]
        ?UploadedFile $cover,
    ): JsonResponse {
        // Constraints are skipped when no file is sent at all.
        if (null === $cover) {
            throw new UnprocessableEntityHttpException('Veuillez envoyer une image dans le champ "cover" (multipart/form-data).');
        }

        $this->coverStorage->replace($category, $cover);
        $this->em->flush();

        return $this->success($category, 'Image de couverture enregistrée avec succès.', context: self::CONTEXT);
    }

    #[Route('/{id}/cover', name: 'cover_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[SuccessResponse(Response::HTTP_OK, 'Cover removed', Category::class, ['category:read'])]
    #[ErrorResponse(Response::HTTP_FORBIDDEN, 'Admins only')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Category not found')]
    public function deleteCover(#[MapEntity(message: self::NOT_FOUND)] Category $category): JsonResponse
    {
        $this->coverStorage->remove($category);
        $this->em->flush();

        return $this->success($category, 'Image de couverture supprimée avec succès.', context: self::CONTEXT);
    }

    private function assertNameAvailable(string $name, ?Category $exclude = null): void
    {
        if ($this->repository->nameExists($name, $exclude)) {
            throw new ConflictHttpException(\sprintf('Une catégorie nommée "%s" existe déjà.', $name));
        }
    }
}
