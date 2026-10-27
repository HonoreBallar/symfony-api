<?php

namespace App\Tests\Controller\Api;

use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Category creation, with or without a cover image sent in the same request.
 */
final class CategoryCreationTest extends ApiTestCase
{
    /** 1x1 transparent PNG. */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testCreateWithJsonHasNoCover(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->jsonRequest('POST', '/api/categories', ['name' => $this->uniqueName(), 'description' => 'Sans image'], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->responseData();
        self::assertSame('Sans image', $data['description']);
        self::assertNull($data['coverUrl']);
    }

    public function testCreateWithCoverInSameRequest(): void
    {
        $token = $this->loginAsAdmin();
        $name = $this->uniqueName();

        $this->client->request('POST', '/api/categories', ['name' => $name, 'description' => 'Avec image'], ['cover' => $this->upload('png', 'image/png', base64_decode(self::PNG))], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->responseData();
        self::assertSame($name, $data['name']);
        self::assertSame('Avec image', $data['description']);
        self::assertMatchesRegularExpression('#^http://localhost/uploads/categories/[0-9a-f-]{36}\.png$#', $data['coverUrl']);

        $file = static::getContainer()->getParameter('kernel.project_dir').'/public'.parse_url($data['coverUrl'], \PHP_URL_PATH);
        self::assertFileExists($file);

        // Deleting the category also removes its cover file.
        $this->client->request('DELETE', '/api/categories/'.$data['id'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
        self::assertFileDoesNotExist($file);
    }

    public function testInvalidCoverRejectsTheWholeCreation(): void
    {
        $token = $this->loginAsAdmin();
        $name = $this->uniqueName();

        $this->client->request('POST', '/api/categories', ['name' => $name], ['cover' => $this->upload('txt', 'text/plain', 'pas une image')], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(422);
        self::assertFalse(static::getContainer()->get(CategoryRepository::class)->nameExists($name));
    }

    public function testCreateWithCoverStillValidatesFields(): void
    {
        $token = $this->loginAsAdmin();

        $this->client->request('POST', '/api/categories', ['name' => ''], ['cover' => $this->upload('png', 'image/png', base64_decode(self::PNG))], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(422);
    }

    private function upload(string $extension, string $mimeType, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'cover.'.$extension, $mimeType, test: true);
    }

    private function uniqueName(): string
    {
        return uniqid('Catégorie ', true);
    }
}
