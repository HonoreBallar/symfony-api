<?php

namespace App\Service;

use App\Entity\Category;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Stores category cover images in public/uploads/categories, served directly by nginx.
 */
final readonly class CategoryCoverStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public'.Category::COVER_DIR)]
        private string $directory,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * Saves the uploaded file and deletes the previous cover, if any.
     */
    public function replace(Category $category, UploadedFile $file): void
    {
        // The extension comes from the detected MIME type, never from the client file name.
        $filename = Uuid::v7().'.'.$file->guessExtension();
        $file->move($this->directory, $filename);

        $this->remove($category);
        $category->setCoverImage($filename);
    }

    public function remove(Category $category): void
    {
        if (null === $category->getCoverImage()) {
            return;
        }

        $this->filesystem->remove($this->directory.'/'.$category->getCoverImage());
        $category->setCoverImage(null);
    }
}
