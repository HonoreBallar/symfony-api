<?php

namespace App\EventListener;

use App\Http\ApiResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Renders every exception thrown under /api as { success: false, message, errors }.
 *
 * Priority -64: after Symfony logs the exception (0), before its default error rendering (-128).
 */
#[AsEventListener(priority: -64)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $errors = [];

        if ($exception->getPrevious() instanceof ValidationFailedException) {
            $message = 'Les données envoyées sont invalides.';
            $errors = $this->formatViolations($exception->getPrevious()->getViolations());
        } elseif ($exception->getPrevious() instanceof UnexpectedValueException) {
            $message = "Le corps de la requête n'est pas un JSON valide.";
        } elseif ($exception->getPrevious() instanceof ResourceNotFoundException) {
            $message = 'Route introuvable.';
        } elseif ($exception instanceof MethodNotAllowedHttpException) {
            $message = 'Méthode HTTP non autorisée.';
        } elseif ($exception instanceof AccessDeniedHttpException) {
            $message = 'Accès refusé : droits insuffisants.';
        } elseif ($exception instanceof HttpExceptionInterface && '' !== $exception->getMessage()) {
            $message = $exception->getMessage();
        } else {
            $message = Response::HTTP_INTERNAL_SERVER_ERROR === $status
                ? 'Une erreur interne est survenue.'
                : (Response::$statusTexts[$status] ?? 'Erreur.');
        }

        $event->setResponse(ApiResponse::error($message, $status, $errors, $headers));
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $errors;
    }
}
