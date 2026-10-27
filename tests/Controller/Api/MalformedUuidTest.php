<?php

namespace App\Tests\Controller\Api;

/**
 * An {id} that is not a UUID gets an explicit 404, distinct from an unknown route.
 */
final class MalformedUuidTest extends ApiTestCase
{
    /**
     * Routing runs before the firewall, so this answer is the same with or without a token.
     */
    public function testMalformedId(): void
    {
        $this->client->request('GET', '/api/categories/1');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'success' => false,
            'message' => "Ressource introuvable : l'identifiant doit être un UUID valide.",
            'errors' => [['field' => 'id', 'message' => 'UUID attendu, par exemple 01a17926-27bd-787f-b11e-3096660342b7.']],
        ], $this->responseJson());
    }

    public function testMalformedIdOnSubResource(): void
    {
        $this->client->request('DELETE', '/api/categories/abc/cover', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAsAdmin()]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('id', $this->responseJson()['errors'][0]['field']);
    }

    public function testMalformedIdWithUnsupportedMethodIsStillReported(): void
    {
        $this->client->request('POST', '/api/categories/1');

        self::assertResponseStatusCodeSame(404);
        self::assertSame("Ressource introuvable : l'identifiant doit être un UUID valide.", $this->responseJson()['message']);
    }

    public function testValidButUnknownUuidKeepsTheEntityMessage(): void
    {
        $token = $this->login($this->createVerifiedUser(['ROLE_USER']));

        $this->client->request('GET', '/api/categories/01a17926-27bd-787f-b11e-3096660342b7', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Catégorie introuvable.', $this->responseJson()['message']);
    }

    public function testUnknownRouteKeepsTheRouteMessage(): void
    {
        $this->client->request('GET', '/api/nope/1');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Route introuvable.', $this->responseJson()['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseJson(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
