<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Supabase;

class SupabaseStorageService
{
    private Supabase $config;

    public function __construct(?Supabase $config = null)
    {
        $this->config = $config ?? config('Supabase');
    }

    public function isConfigured(): bool
    {
        return $this->config->projectUrl !== ''
            && ($this->config->serviceRoleKey !== '' || $this->config->secretKey !== '' || $this->config->publishableKey !== '')
            && $this->config->bucketName !== ''
            && function_exists('curl_init');
    }

    /**
     * Builds valid Supabase Storage auth headers.
     * Supabase Storage requires a compact JWT in Authorization: Bearer <jwt>.
     * Priority:
     *   1. serviceRoleKey (eyJ...) if configured
     *   2. secretKey if it is a JWT (eyJ...)
     *   3. Logged-in user's Supabase Auth session access_token JWT + apikey
     *   4. Fallback to secretKey
     *
     * @return list<string>
     */
    private function buildAuthHeaders(?string $contentType = null, ?bool $upsert = null): array
    {
        $serviceRoleJwt = $this->config->serviceRoleKey;
        $secretKey = $this->config->secretKey;
        $publishableKey = $this->config->publishableKey;

        $bearerToken = '';
        $apiKey = $secretKey !== '' ? $secretKey : $publishableKey;

        if ($serviceRoleJwt !== '' && substr_count($serviceRoleJwt, '.') === 2) {
            $bearerToken = $serviceRoleJwt;
            $apiKey = $serviceRoleJwt;
        } elseif ($secretKey !== '' && substr_count($secretKey, '.') === 2) {
            $bearerToken = $secretKey;
            $apiKey = $secretKey;
        } else {
            $sessionToken = trim((string) (session('supabase_session.access_token') ?? ''));
            if ($sessionToken !== '' && substr_count($sessionToken, '.') === 2) {
                $bearerToken = $sessionToken;
                $apiKey = $publishableKey !== '' ? $publishableKey : $secretKey;
            } else {
                $bearerToken = $apiKey;
            }
        }

        $headers = [
            'Authorization: Bearer ' . $bearerToken,
            'apikey: ' . $apiKey,
        ];

        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }
        if ($upsert !== null) {
            $headers[] = 'x-upsert: ' . ($upsert ? 'true' : 'false');
        }

        return $headers;
    }

    public function getBucketName(): string
    {
        return $this->config->bucketName;
    }

    public function isSupabaseReference(?string $storedPath): bool
    {
        return is_string($storedPath) && str_starts_with(trim($storedPath), 'supabase:');
    }

    public function extractObjectPath(string $storedPath): string
    {
        return ltrim(substr(trim($storedPath), strlen('supabase:')), '/');
    }

    public function buildReference(string $objectPath): string
    {
        return 'supabase:' . ltrim(str_replace('\\', '/', $objectPath), '/');
    }

    /**
     * Uploads an UploadedFile to Supabase Storage under the given object path
     * (e.g. "news/abc.jpg" or "profile_pictures/xyz.png").
     *
     * @return string|null Returns "supabase:<objectPath>" on success, or null on failure.
     */
    public function uploadUploadedFile(UploadedFile $file, string $objectPath, bool $upsert = false): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $tempPath = $file->getTempName();
        if ($tempPath === '' || ! is_file($tempPath)) {
            return null;
        }

        $contents = @file_get_contents($tempPath);
        if ($contents === false) {
            return null;
        }

        $mimeType = (string) ($file->getMimeType() ?: 'application/octet-stream');

        return $this->uploadBinary($contents, $objectPath, $mimeType, $upsert);
    }

    /**
     * Uploads raw binary content to Supabase Storage.
     *
     * @return string|null Returns "supabase:<objectPath>" on success, or null on failure.
     */
    public function uploadBinary(string $contents, string $objectPath, string $mimeType = 'application/octet-stream', bool $upsert = false): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cleanObjectPath = ltrim(str_replace('\\', '/', $objectPath), '/');
        $uploadUrl = rtrim($this->config->projectUrl, '/')
            . '/storage/v1/object/'
            . rawurlencode($this->config->bucketName)
            . '/'
            . $this->encodeObjectPath($cleanObjectPath);

        $curl = curl_init($uploadUrl);
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->buildAuthHeaders($mimeType, $upsert),
            CURLOPT_POSTFIELDS     => $contents,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($statusCode < 200 || $statusCode >= 300) {
            log_message('error', 'Supabase Storage upload failed ({status}) for {path}: {err} {body}', [
                'status' => $statusCode,
                'path'   => $cleanObjectPath,
                'err'    => $curlError,
                'body'   => is_string($responseBody) ? $responseBody : '',
            ]);
            return null;
        }

        return $this->buildReference($cleanObjectPath);
    }

    /**
     * Deletes an object from Supabase Storage given either a "supabase:<path>" reference or raw object path.
     */
    public function deleteReference(?string $storedPath): void
    {
        if (! $this->isConfigured() || ! is_string($storedPath) || trim($storedPath) === '') {
            return;
        }

        $objectPath = $this->isSupabaseReference($storedPath)
            ? $this->extractObjectPath($storedPath)
            : ltrim(str_replace('\\', '/', $storedPath), '/');

        if ($objectPath === '') {
            return;
        }

        $deleteUrl = rtrim($this->config->projectUrl, '/')
            . '/storage/v1/object/'
            . rawurlencode($this->config->bucketName)
            . '/'
            . $this->encodeObjectPath($objectPath);

        $curl = curl_init($deleteUrl);
        if ($curl === false) {
            return;
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->buildAuthHeaders(),
            CURLOPT_TIMEOUT        => 15,
        ]);

        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * Resolves a stored file path ("supabase:...", full URL, or local relative path) to a browser-loadable URL.
     */
    public function resolvePublicUrl(?string $storedPath): ?string
    {
        if (! is_string($storedPath)) {
            return null;
        }

        $trimmed = trim($storedPath);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        if ($this->isSupabaseReference($trimmed)) {
            $objectPath = $this->extractObjectPath($trimmed);
            return rtrim($this->config->projectUrl, '/')
                . '/storage/v1/object/public/'
                . rawurlencode($this->config->bucketName)
                . '/'
                . $this->encodeObjectPath($objectPath);
        }

        return base_url(str_replace('\\', '/', $trimmed));
    }

    private function encodeObjectPath(string $path): string
    {
        $segments = array_filter(
            explode('/', str_replace('\\', '/', $path)),
            static fn (string $segment): bool => $segment !== ''
        );

        return implode('/', array_map('rawurlencode', $segments));
    }
}
