<?php

namespace App\Controllers;

use App\Models\NewsModel;
use CodeIgniter\HTTP\ResponseInterface;

class NewsController extends BaseController
{
    private NewsModel $newsModel;

    public function __construct()
    {
        helper(['filesystem', 'text']);
        $this->newsModel = new NewsModel();
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
                'image_url' => ! empty($item['image_path']) ? base_url(str_replace('\\', '/', (string) $item['image_path'])) : null,
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
        if ($image && $image->isValid() && ! $image->hasMoved()) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (! in_array($image->getMimeType(), $allowedMimeTypes, true)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.',
                ]);
            }

            $uploadDirectory = ROOTPATH . 'public/uploads/news';
            if (! is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }

            $newName = $image->getRandomName();
            $image->move($uploadDirectory, $newName);
            $imagePath = 'uploads/news/' . $newName;
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
                'image_url' => $imagePath ? base_url($imagePath) : null,
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
        if ($image && $image->isValid() && ! $image->hasMoved()) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (! in_array($image->getMimeType(), $allowedMimeTypes, true)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.',
                ]);
            }

            $uploadDirectory = ROOTPATH . 'public/uploads/news';
            if (! is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }

            $newName = $image->getRandomName();
            $image->move($uploadDirectory, $newName);
            $newImagePath = 'uploads/news/' . $newName;

            if ($imagePath !== '') {
                $oldImageFile = ROOTPATH . 'public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);
                if (is_file($oldImageFile)) {
                    @unlink($oldImageFile);
                }
            }

            $imagePath = $newImagePath;
        } elseif ($removeImage && $imagePath !== '') {
            $oldImageFile = ROOTPATH . 'public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);
            if (is_file($oldImageFile)) {
                @unlink($oldImageFile);
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
                'image_url' => ! empty($updated['image_path']) ? base_url(str_replace('\\', '/', (string) $updated['image_path'])) : null,
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
            $imageFile = ROOTPATH . 'public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $post['image_path']);
            if (is_file($imageFile)) {
                @unlink($imageFile);
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
