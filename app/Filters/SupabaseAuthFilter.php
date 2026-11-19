<?php

namespace App\Filters;

use App\Models\UserAccountModel;
use App\Services\SupabaseAuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CodeIgniter 4 Route Filter that enforces Supabase Auth + Session verification
 * on protected page and API routes.
 */
class SupabaseAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('role');

        $supabaseAuth = new SupabaseAuthService();
        $session = session();

        // 1. Check if a Bearer token was supplied in the Authorization header (stateless API support)
        $bearerToken = $this->extractBearerToken($request);
        if (! $session->has('auth_user') && $bearerToken !== null) {
            if (! $this->authenticateFromBearerToken($bearerToken, $supabaseAuth)) {
                return $this->unauthorizedResponse($request, 'Invalid or expired Supabase access token.');
            }
        }

        // 2. Require an authenticated session user
        $authUser = $session->get('auth_user');
        if (! is_array($authUser) || empty($authUser['email'])) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Please log in.');
        }

        // 3. Validate and refresh the Supabase JWT session if present
        $supabaseSession = $session->get('supabase_session');
        if (is_array($supabaseSession) && ! empty($supabaseSession['access_token'])) {
            $accessToken = (string) $supabaseSession['access_token'];
            $refreshToken = (string) ($supabaseSession['refresh_token'] ?? '');
            $expiresAt = isset($supabaseSession['expires_at']) ? (int) $supabaseSession['expires_at'] : null;

            // Refresh token automatically when expired or within 60s of expiry
            if ($supabaseAuth->isTokenExpired($accessToken, $expiresAt, 60)) {
                if ($refreshToken === '') {
                    $this->clearAuthSession();
                    return $this->unauthorizedResponse($request, 'Session expired. Please log in again.');
                }

                $refreshResult = $supabaseAuth->refreshSession($refreshToken);
                if ($refreshResult['success'] && ! empty($refreshResult['session'])) {
                    $supabaseSession = $refreshResult['session'];
                    $session->set('supabase_session', $supabaseSession);
                    $accessToken = (string) $supabaseSession['access_token'];
                    $this->syncRoleFromSupabaseUser($refreshResult['user'] ?? [], $authUser, $supabaseAuth);
                } elseif (empty($refreshResult['unreachable']) || ! $supabaseAuth->isFallbackAllowed()) {
                    $this->clearAuthSession();
                    return $this->unauthorizedResponse($request, 'Session expired. Please log in again.');
                }
            } else {
                // Periodically verify with GET /auth/v1/user that the session has not been revoked
                $lastVerifiedAt = (int) ($supabaseSession['last_verified_at'] ?? 0);
                if ((time() - $lastVerifiedAt) >= $supabaseAuth->getVerifyIntervalSeconds()) {
                    $userCheck = $supabaseAuth->getUser($accessToken);
                    if ($userCheck['success'] && ! empty($userCheck['user'])) {
                        $supabaseSession['last_verified_at'] = time();
                        $session->set('supabase_session', $supabaseSession);
                        $this->syncRoleFromSupabaseUser($userCheck['user'], $authUser, $supabaseAuth);
                    } elseif (empty($userCheck['unreachable']) && in_array($userCheck['status'], [401, 403], true)) {
                        $this->clearAuthSession();
                        return $this->unauthorizedResponse($request, 'Session is no longer valid. Please log in again.');
                    }
                }
            }
        }

        // Release the PHP session file lock on read-only GET requests so parallel
        // frontend fetches (Promise.all) and page navigations are not serialized.
        if (strtoupper($request->getMethod()) === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Prevent browsers from caching protected HTML pages after logout
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType === '' || str_contains($contentType, 'text/html')) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->setHeader('Pragma', 'no-cache');
        }

        return $response;
    }

    private function extractBearerToken(RequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            $token = trim($matches[1]);
            return $token !== '' ? $token : null;
        }

        return null;
    }

    private function authenticateFromBearerToken(string $accessToken, SupabaseAuthService $supabaseAuth): bool
    {
        $userResult = $supabaseAuth->getUser($accessToken);
        if (! $userResult['success'] || empty($userResult['user']['email'])) {
            return false;
        }

        $supabaseUser = $userResult['user'];
        $email = strtolower(trim((string) $supabaseUser['email']));

        $userAccountModel = new UserAccountModel();
        $localUser = $userAccountModel->findByEmail($email);

        // Determine role strictly from app_metadata or local database (never user_metadata)
        $authorizedRole = $supabaseAuth->extractAuthorizedRole($supabaseUser)
            ?? strtolower(trim((string) ($localUser['Role'] ?? 'alumni')));

        $firstName = (string) ($localUser['First_Name'] ?? $supabaseUser['user_metadata']['first_name'] ?? '');
        $lastName = (string) ($localUser['Last_Name'] ?? $supabaseUser['user_metadata']['last_name'] ?? '');

        session()->set('auth_user', [
            'id'               => $localUser['id'] ?? $supabaseUser['id'] ?? null,
            'supabase_user_id' => $supabaseUser['id'] ?? null,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'email'            => $email,
            'role'             => $authorizedRole,
            'department'       => (string) ($localUser['Department'] ?? $supabaseUser['app_metadata']['department'] ?? ''),
            'campus'           => (string) ($localUser['Campus'] ?? $supabaseUser['app_metadata']['campus'] ?? ''),
            'account_id'       => $localUser['account_id'] ?? $supabaseUser['app_metadata']['account_id'] ?? null,
            'name'             => trim($firstName . ' ' . $lastName),
            'auth_provider'    => 'supabase',
        ]);

        $claims = $supabaseAuth->decodeJwtPayload($accessToken);
        session()->set('supabase_session', [
            'access_token'     => $accessToken,
            'refresh_token'    => '',
            'token_type'       => 'bearer',
            'expires_at'       => (int) ($claims['exp'] ?? (time() + 3600)),
            'supabase_user_id' => (string) ($supabaseUser['id'] ?? ''),
            'last_verified_at' => time(),
        ]);

        return true;
    }

    /**
     * Keep session role synchronized with `app_metadata` (never `user_metadata`).
     *
     * @param array<string, mixed> $supabaseUser
     * @param array<string, mixed> $authUser
     */
    private function syncRoleFromSupabaseUser(array $supabaseUser, array $authUser, SupabaseAuthService $supabaseAuth): void
    {
        $appMetaRole = $supabaseAuth->extractAuthorizedRole($supabaseUser);
        if ($appMetaRole !== null && $appMetaRole !== strtolower((string) ($authUser['role'] ?? ''))) {
            $authUser['role'] = $appMetaRole;
            session()->set('auth_user', $authUser);
        }
    }

    private function clearAuthSession(): void
    {
        session()->remove(['auth_user', 'supabase_session']);
    }

    private function unauthorizedResponse(RequestInterface $request, string $message): ResponseInterface
    {
        if ($this->expectsHtmlRedirect($request)) {
            return redirect()->to(base_url('/'));
        }

        return service('response')->setStatusCode(401)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }

    private function expectsHtmlRedirect(RequestInterface $request): bool
    {
        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return false;
        }

        $method = strtoupper($request->getMethod());
        if ($method !== 'GET') {
            return false;
        }

        $path = strtolower(trim($request->getUri()->getPath(), '/'));
        if (str_ends_with($path, '.html') || str_ends_with($path, 'staff/dashboard')) {
            return true;
        }

        return false;
    }
}

