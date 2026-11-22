<?php

namespace App\Controllers;

use App\Models\NewsModel;
use App\Services\SupabaseStorageService;
use CodeIgniter\HTTP\ResponseInterface;

class NewsController extends BaseController
{
    private NewsModel $newsModel;
    private SupabaseStorageService $storage;

    public function __construct()
    {
        helper(['filesystem', 'text']);
        $this->newsModel = new NewsModel();
        $this->storage = new SupabaseStorageService();
    }

    public function list(): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $items = array_map(function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'title' => (string) ($item['title'] ?? ''),
                'content' => (string) ($item['content'] ?? ''),
                'link_url' => (string) ($item['link_url'] ?? ''),
                'image_url' => $this->storage->resolvePublicUrl($item['image_path'] ?? null),
                'author' => (string) ($item['author_name'] ?? 'Staff Office'),
                'author_role' => (string) ($item['author_role'] ?? 'Staff'),
                'created_at' => (string) ($item['created_at'] ?? ''),
            ];
        }, $this->newsModel->getAllPublished());

        return $this->response->setJSON([
            'success' => true,
            'news' => $items,
        ]);
    }

    public function create(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $title = trim((string) $this->request->getPost('title'));
        $content = trim((string) $this->request->getPost('content'));

        if ($title === '' || $content === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please add both a title and content.',
            ]);
        }

        $linkUrl = trim((string) $this->request->getPost('link_url'));
        if ($linkUrl !== '' && ! filter_var($linkUrl, FILTER_VALIDATE_URL)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter a valid link URL.',
            ]);
        }

        $imagePath = null;
        $image = $this->request->getFile('image');
        if ($image && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            if (! $image->isValid()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Image upload failed: ' . $image->getErrorString(),
                ]);
            }

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (! in_array($image->getMimeType(), $allowedMimeTypes, true)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.',
                ]);
            }

            $newName = $image->getRandomName();
            if ($this->storage->isConfigured()) {
                $uploadedRef = $this->storage->uploadUploadedFile($image, 'news/' . $newName);
                if ($uploadedRef === null) {
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Unable to upload image to Supabase Storage. Please check storage settings and try again.',
                    ]);
                }
                $imagePath = $uploadedRef;
            } else {
                $uploadDirectory = ROOTPATH . 'public/uploads/news';
                if (! is_dir($uploadDirectory) && ! @mkdir($uploadDirectory, 0775, true)) {
                    log_message('error', 'News image upload failed: unable to create directory {dir}', ['dir' => $uploadDirectory]);
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Server upload folder is not writable. Please contact the administrator.',
                    ]);
                }

                try {
                    $image->move($uploadDirectory, $newName);
                    $imagePath = 'uploads/news/' . $newName;
                } catch (\Throwable $e) {
                    log_message('error', 'News image move failed: {msg}', ['msg' => $e->getMessage()]);
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Unable to save the uploaded image. Please try again.',
                    ]);
                }
            }
        }

        $user = session('auth_user');
        $authorName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        if ($authorName === '') {
            $authorName = (string) ($user['name'] ?? 'Staff Office');
        }

        $payload = [
            'title' => $title,
            'content' => $content,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'image_path' => $imagePath,
            'author_name' => $authorName,
            'author_role' => (string) ($user['role'] ?? 'staff'),
        ];

        $this->newsModel->insert($payload);
        $id = (int) $this->newsModel->getInsertID();

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'News post published successfully.',
            'data' => [
                'id' => $id,
                'title' => $title,
                'content' => $content,
                'link_url' => $linkUrl,
                'image_url' => $this->storage->resolvePublicUrl($imagePath),
                'author' => $authorName,
                'author_role' => (string) ($user['role'] ?? 'staff'),
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $post = $this->newsModel->find($id);
        if (! $post) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'News post not found.',
            ]);
        }

        $title = trim((string) $this->request->getPost('title'));
        $content = trim((string) $this->request->getPost('content'));
        $linkUrl = trim((string) $this->request->getPost('link_url'));
        $removeImage = strtolower(trim((string) $this->request->getPost('remove_image'))) === '1';

        if ($title === '' || $content === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please add both a title and content.',
            ]);
        }

        if ($linkUrl !== '' && ! filter_var($linkUrl, FILTER_VALIDATE_URL)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter a valid link URL.',
            ]);
        }

        $imagePath = (string) ($post['image_path'] ?? '');
        $image = $this->request->getFile('image');
        if ($image && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            if (! $image->isValid()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Image upload failed: ' . $image->getErrorString(),
                ]);
            }

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (! in_array($image->getMimeType(), $allowedMimeTypes, true)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.',
                ]);
            }

            $newName = $image->getRandomName();
            if ($this->storage->isConfigured()) {
                $uploadedRef = $this->storage->uploadUploadedFile($image, 'news/' . $newName);
                if ($uploadedRef === null) {
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Unable to upload image to Supabase Storage. Please try again.',
                    ]);
                }
                $newImagePath = $uploadedRef;
            } else {
                $uploadDirectory = ROOTPATH . 'public/uploads/news';
                if (! is_dir($uploadDirectory) && ! @mkdir($uploadDirectory, 0775, true)) {
                    log_message('error', 'News image update failed: unable to create directory {dir}', ['dir' => $uploadDirectory]);
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Server upload folder is not writable. Please contact the administrator.',
                    ]);
                }

                try {
                    $image->move($uploadDirectory, $newName);
                    $newImagePath = 'uploads/news/' . $newName;
                } catch (\Throwable $e) {
                    log_message('error', 'News image update move failed: {msg}', ['msg' => $e->getMessage()]);
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Unable to save the uploaded image. Please try again.',
                    ]);
                }
            }

            if ($imagePath !== '') {
                if ($this->storage->isSupabaseReference($imagePath)) {
                    $this->storage->deleteReference($imagePath);
                } else {
                    $oldImageFile = ROOTPATH . 'public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);
                    if (is_file($oldImageFile)) {
                        @unlink($oldImageFile);
                    }
                }
            }

            $imagePath = $newImagePath;
        } elseif ($removeImage && $imagePath !== '') {
            if ($this->storage->isSupabaseReference($imagePath)) {
                $this->storage->deleteReference($imagePath);
            } else {
                $oldImageFile = ROOTPATH . 'public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);
                if (is_file($oldImageFile)) {
                    @unlink($oldImageFile);
                }
            }
            $imagePath = '';
        }

        $this->newsModel->update($id, [
            'title' => $title,
            'content' => $content,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'image_path' => $imagePath !== '' ? $imagePath : null,
        ]);

        $updated = $this->newsModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'News post updated successfully.',
            'data' => [
                'id' => (int) ($updated['id'] ?? $id),
                'title' => (string) ($updated['title'] ?? ''),
                'content' => (string) ($updated['content'] ?? ''),
                'link_url' => (string) ($updated['link_url'] ?? ''),
                'image_url' => $this->storage->resolvePublicUrl($updated['image_path'] ?? null),
                'author' => (string) ($updated['author_name'] ?? 'Staff Office'),
                'author_role' => (string) ($updated['author_role'] ?? 'staff'),
                'created_at' => (string) ($updated['created_at'] ?? ''),
            ],
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $post = $this->newsModel->find($id);
        if (! $post) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'News post not found.',
            ]);
        }

        if (! empty($post['image_path'])) {
            $storedPath = (string) $post['image_path'];
            if ($this->storage->isSupabaseReference($storedPath)) {
                $this->storage->deleteReference($storedPath);
            } else {
                $imageFile = ROOTPATH . 'public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath);
                if (is_file($imageFile)) {
                    @unlink($imageFile);
                }
            }
        }

        $this->newsModel->delete($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'News post deleted successfully.',
        ]);
    }

    private function isStaffAuthorized(): bool
    {
        if (! session()->has('auth_user')) {
            return false;
        }

        return strtolower((string) (session('auth_user.role') ?? '')) === 'head_admin';
    }
}
