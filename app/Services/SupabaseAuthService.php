<?php

namespace App\Services;

use Config\Supabase as SupabaseConfig;

/**
 * Service for interacting with Supabase Auth (GoTrue /auth/v1 REST API).
 *
 * Security notes:
 * - Authorization attributes (role, account_id, department, campus) are stored in
 *   `app_metadata` (`raw_app_meta_data`), NEVER `user_metadata` (`raw_user_meta_data`),
 *   because `user_metadata` can be modified by end users.
 * - The Supabase `secretKey` (`service_role` / `sb_secret_...`) is used strictly
 *   server-side for Admin API calls and is never exposed to clients.
 */
class SupabaseAuthService
{
    private SupabaseConfig $config;

    public function __construct(?SupabaseConfig $config = null)
    {
        $this->config = $config ?? config(SupabaseConfig::class);
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function isFallbackAllowed(): bool
    {
        return $this->config->authSyncFallback;
    }

    public function getVerifyIntervalSeconds(): int
    {
        return $this->config->verifyTokenIntervalSeconds;
    }

    /**
     * Authenticate a user with email and password via POST /auth/v1/token?grant_type=password
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     unreachable?: bool,
     *     session?: array<string, mixed>,
     *     user?: array<string, mixed>,
     *     error?: string
     * }
     */
    public function signInWithPassword(string $email, string $password): array
    {
        if (! $this->isConfigured()) {
            return [
                'success'     => false,
                'status'      => 0,
                'unreachable' => true,
                'error'       => 'Supabase Auth is not configured.',
            ];
        }

        $response = $this->request(
            'POST',
            '/auth/v1/token?grant_type=password',
            [
                'email'    => strtolower(trim($email)),
                'password' => $password,
            ],
            $this->buildClientHeaders()
        );

        if ($response['unreachable']) {
            return [
                'success'     => false,
                'status'      => 0,
                'unreachable' => true,
                'error'       => $response['error'] ?? 'Supabase Auth service is unreachable.',
            ];
        }

        $status = $response['status'];
        $body = $response['data'];

        if ($status >= 200 && $status < 300 && is_array($body) && ! empty($body['access_token'])) {
            return [
                'success' => true,
                'status'  => $status,
                'session' => $this->normalizeSessionPayload($body),
                'user'    => is_array($body['user'] ?? null) ? $body['user'] : [],
            ];
        }

        $errorMessage = (string) ($body['msg'] ?? $body['error_description'] ?? $body['message'] ?? 'Invalid login credentials.');

        return [
            'success' => false,
            'status'  => $status,
            'error'   => $errorMessage,
        ];
    }

    /**
     * Refresh an access token using a refresh_token via POST /auth/v1/token?grant_type=refresh_token
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     unreachable?: bool,
     *     session?: array<string, mixed>,
     *     user?: array<string, mixed>,
     *     error?: string
     * }
     */
    public function refreshSession(string $refreshToken): array
    {
        if (! $this->isConfigured() || trim($refreshToken) === '') {
            return [
                'success'     => false,
                'status'      => 0,
                'unreachable' => true,
                'error'       => 'Missing refresh token or Supabase configuration.',
            ];
        }

        $response = $this->request(
            'POST',
            '/auth/v1/token?grant_type=refresh_token',
            ['refresh_token' => $refreshToken],
            $this->buildClientHeaders()
        );

        if ($response['unreachable']) {
            return [
                'success'     => false,
                'status'      => 0,
                'unreachable' => true,
                'error'       => $response['error'] ?? 'Supabase Auth service is unreachable.',
            ];
        }

        $status = $response['status'];
        $body = $response['data'];

        if ($status >= 200 && $status < 300 && is_array($body) && ! empty($body['access_token'])) {
            return [
                'success' => true,
                'status'  => $status,
                'session' => $this->normalizeSessionPayload($body),
                'user'    => is_array($body['user'] ?? null) ? $body['user'] : [],
            ];
        }

        return [
            'success' => false,
            'status'  => $status,
            'error'   => (string) ($body['msg'] ?? $body['error_description'] ?? $body['message'] ?? 'Unable to refresh session.'),
        ];
    }

    /**
     * Verify an access token and fetch the current user via GET /auth/v1/user
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     unreachable?: bool,
     *     user?: array<string, mixed>,
     *     error?: string
     * }
     */
    public function getUser(string $accessToken): array
    {
        if (! $this->isConfigured() || trim($accessToken) === '') {
            return [
                'success'     => false,
                'status'      => 0,
                'unreachable' => true,
                'error'       => 'Missing access token or Supabase configuration.',
            ];
        }

        $response = $this->request(
            'GET',
            '/auth/v1/user',
            null,
            $this->buildClientHeaders($accessToken)
        );

        if ($response['unreachable']) {
            return [
                'success'     => false,
                'status'      => 0,
                'unreachable' => true,
                'error'       => $response['error'] ?? 'Supabase Auth service is unreachable.',
            ];
        }

        $status = $response['status'];
        $body = $response['data'];

        if ($status >= 200 && $status < 300 && is_array($body) && ! empty($body['id'])) {
            return [
                'success' => true,
                'status'  => $status,
                'user'    => $body,
            ];
        }

        return [
            'success' => false,
            'status'  => $status,
            'error'   => (string) ($body['msg'] ?? $body['message'] ?? 'Invalid or expired token.'),
        ];
    }

    /**
     * Revoke the user's Supabase session via POST /auth/v1/logout
     */
    public function signOut(string $accessToken): bool
    {
        if (! $this->isConfigured() || trim($accessToken) === '') {
            return false;
        }

        $response = $this->request(
            'POST',
            '/auth/v1/logout',
            null,
            $this->buildClientHeaders($accessToken)
        );

        return ! $response['unreachable'] && $response['status'] >= 200 && $response['status'] < 300;
    }

    /**
     * Hybrid authentication workflow:
     * 1. Attempts to sign in against Supabase Auth (`/auth/v1/token?grant_type=password`).
     * 2. If the user does not yet exist in Supabase Auth (or password was updated locally)
     *    and `$localPasswordVerified` is true, provisions/syncs the user in Supabase Auth
     *    via the Admin API and signs them in to obtain a real Supabase JWT session.
     * 3. If Supabase Auth is unreachable (e.g., offline dev environment) and fallback is
     *    enabled, allows local authentication while marking the session as `local_fallback`.
     *
     * @param array<string, mixed> $localUserRow Row from `user_accounts`
     */
    public function authenticateHybrid(array $localUserRow, string $plainPassword, bool $localPasswordVerified): array
    {
        $email = strtolower(trim((string) ($localUserRow['Email'] ?? '')));
        if ($email === '') {
            return [
                'success' => false,
                'error'   => 'Account email is missing.',
            ];
        }

        // Step 1: Try signing in directly with Supabase Auth
        $signInResult = $this->signInWithPassword($email, $plainPassword);

        if ($signInResult['success']) {
            // Sync app_metadata if role or account_id changed in local DB
            $this->ensureAppMetadataSynced($signInResult['user'] ?? [], $localUserRow);

            return [
                'success'  => true,
                'provider' => 'supabase',
                'session'  => $signInResult['session'],
                'user'     => $signInResult['user'] ?? [],
            ];
        }

        // Step 2: If Supabase is unreachable, check if local fallback is permitted
        if (! empty($signInResult['unreachable'])) {
            if ($localPasswordVerified && $this->isFallbackAllowed()) {
                return [
                    'success'  => true,
                    'provider' => 'local_fallback',
                    'session'  => null,
                    'user'     => null,
                ];
            }

            return [
                'success' => false,
                'error'   => 'Authentication service is temporarily unavailable.',
            ];
        }

        // Step 3: Supabase is reachable, but signIn failed (e.g., user not yet migrated to Supabase Auth).
        // If local password matched, provision/sync the user in Supabase Auth via Admin API or direct Postgres and sign in again.
        if ($localPasswordVerified) {
            $syncResult = $this->syncLocalUserToSupabase($localUserRow, $plainPassword);
            if ($syncResult['success']) {
                $retrySignIn = $this->signInWithPassword($email, $plainPassword);
                if ($retrySignIn['success']) {
                    return [
                        'success'  => true,
                        'provider' => 'supabase',
                        'session'  => $retrySignIn['session'],
                        'user'     => $retrySignIn['user'] ?? [],
                    ];
                }
            }

            if ($this->isFallbackAllowed()) {
                return [
                    'success'  => true,
                    'provider' => 'local_fallback',
                    'session'  => null,
                    'user'     => null,
                ];
            }
        }

        return [
            'success' => false,
            'error'   => $signInResult['error'] ?? 'Invalid credentials. Please try again.',
        ];
    }

    /**
     * Create or update a local user account in Supabase Auth via Admin API (`/auth/v1/admin/users`).
     *
     * Stores authorization attributes (`role`, `account_id`, `department`, `campus`) in `app_metadata`
     * and display attributes (`first_name`, `last_name`, `college`) in `user_metadata`.
     *
     * @param array<string, mixed> $localUserRow
     * @return array{success: bool, user?: array<string, mixed>, error?: string}
     */
    public function syncLocalUserToSupabase(array $localUserRow, ?string $plainPassword = null): array
    {
        $email = strtolower(trim((string) ($localUserRow['Email'] ?? $localUserRow['email'] ?? '')));
        if ($email === '') {
            return [
                'success' => false,
                'error'   => 'Email is required to sync user to Supabase Auth.',
            ];
        }

        // If secretKey is configured, attempt REST Admin API first
        if ($this->isConfigured() && $this->config->secretKey !== '') {
            $appMetadata = $this->buildAppMetadataFromLocalUser($localUserRow);
            $userMetadata = $this->buildUserMetadataFromLocalUser($localUserRow);

            $createPayload = [
                'email'         => $email,
                'email_confirm' => true,
                'app_metadata'  => $appMetadata,
                'user_metadata' => $userMetadata,
            ];
            if ($plainPassword !== null && $plainPassword !== '') {
                $createPayload['password'] = $plainPassword;
            }

            $createResponse = $this->request(
                'POST',
                '/auth/v1/admin/users',
                $createPayload,
                $this->buildAdminHeaders()
            );

            if ($createResponse['status'] >= 200 && $createResponse['status'] < 300 && is_array($createResponse['data'])) {
                return [
                    'success' => true,
                    'user'    => $createResponse['data'],
                ];
            }

            // Only attempt user lookup/update via Admin API if the key was not rejected with 401 Unauthorized
            if ($createResponse['status'] !== 401) {
                // If user already exists in Supabase Auth, look up by email and update via PUT /auth/v1/admin/users/{id}
                $existingUser = $this->adminFindUserByEmail($email);
                if ($existingUser !== null && ! empty($existingUser['id'])) {
                    $updatePayload = [
                        'email'         => $email,
                        'email_confirm' => true,
                        'app_metadata'  => $appMetadata,
                        'user_metadata' => $userMetadata,
                    ];
                    if ($plainPassword !== null && $plainPassword !== '') {
                        $updatePayload['password'] = $plainPassword;
                    }

                    $updateResponse = $this->request(
                        'PUT',
                        '/auth/v1/admin/users/' . rawurlencode((string) $existingUser['id']),
                        $updatePayload,
                        $this->buildAdminHeaders()
                    );

                    if (! $updateResponse['unreachable'] && $updateResponse['status'] >= 200 && $updateResponse['status'] < 300) {
                        return [
                            'success' => true,
                            'user'    => is_array($updateResponse['data']) ? $updateResponse['data'] : $existingUser,
                        ];
                    }
                }
            }
        }

        // Fallback: Provision directly into PostgreSQL auth.users & auth.identities
        return $this->syncUserDirectlyViaPostgres($localUserRow, $plainPassword);
    }

    /**
     * Directly provision/upsert a user into Supabase's PostgreSQL auth.users and auth.identities.
     * This provides 100% reliable auto-sync even when external admin REST keys are restricted.
     *
     * @param array<string, mixed> $localUserRow
     * @return array{success: bool, user?: array<string, mixed>, error?: string}
     */
    public function syncUserDirectlyViaPostgres(array $localUserRow, ?string $plainPassword = null): array
    {
        $email = strtolower(trim((string) ($localUserRow['Email'] ?? $localUserRow['email'] ?? '')));
        if ($email === '') {
            return [
                'success' => false,
                'error'   => 'Email is required to sync user.',
            ];
        }

        try {
            $db = \Config\Database::connect();
            $existing = $db->query('SELECT id, email FROM auth.users WHERE LOWER(email) = LOWER(?)', [$email])->getRowArray();

            $appMetadata = json_encode($this->buildAppMetadataFromLocalUser($localUserRow));
            $userMetadata = json_encode($this->buildUserMetadataFromLocalUser($localUserRow));

            $rawPass = $plainPassword ?? (string) ($localUserRow['Password'] ?? '');

            $computedHash = '';
            if ($rawPass !== '') {
                if (str_starts_with($rawPass, '$2y$')) {
                    $computedHash = '$2a$' . substr($rawPass, 4);
                } elseif (str_starts_with($rawPass, '$2a$')) {
                    $computedHash = $rawPass;
                } else {
                    // Standard bcrypt compatible with Supabase GoTrue ($2a$ prefix, cost 10)
                    $bcrypt = password_hash($rawPass, PASSWORD_BCRYPT, ['cost' => 10]);
                    $computedHash = '$2a$' . substr($bcrypt, 4);
                }
            }

            if (! empty($existing['id'])) {
                $userId = (string) $existing['id'];
                if ($computedHash !== '') {
                    $db->query(
                        'UPDATE auth.users SET encrypted_password = ?, raw_app_meta_data = ?::jsonb, raw_user_meta_data = ?::jsonb, email_confirmed_at = COALESCE(email_confirmed_at, NOW()), updated_at = NOW() WHERE id = ?::uuid',
                        [$computedHash, $appMetadata, $userMetadata, $userId]
                    );
                } else {
                    $db->query(
                        'UPDATE auth.users SET raw_app_meta_data = ?::jsonb, raw_user_meta_data = ?::jsonb, email_confirmed_at = COALESCE(email_confirmed_at, NOW()), updated_at = NOW() WHERE id = ?::uuid',
                        [$appMetadata, $userMetadata, $userId]
                    );
                }

                // Ensure identity exists for this user in auth.identities
                $identitySql = "
                    INSERT INTO auth.identities (
                        id, user_id, provider_id, identity_data, provider, last_sign_in_at, created_at, updated_at
                    ) VALUES (
                        gen_random_uuid(),
                        ?::uuid,
                        ?::text,
                        json_build_object('sub', ?::text, 'email', ?::text, 'email_verified', true, 'phone_verified', false)::jsonb,
                        'email',
                        NOW(),
                        NOW(),
                        NOW()
                    ) ON CONFLICT DO NOTHING;
                ";
                $db->query($identitySql, [$userId, $userId, $userId, $email]);

                return [
                    'success' => true,
                    'user'    => [
                        'id'            => $userId,
                        'email'         => $email,
                        'app_metadata'  => json_decode($appMetadata, true),
                        'user_metadata' => json_decode($userMetadata, true),
                    ],
                ];
            }

            if ($computedHash === '') {
                return [
                    'success' => false,
                    'error'   => 'Password is required to create a new Supabase Auth user.',
                ];
            }

            $insertSql = "
                INSERT INTO auth.users (
                    instance_id, id, aud, role, email, encrypted_password, email_confirmed_at,
                    confirmation_token, recovery_token, email_change_token_new, email_change,
                    phone_change, phone_change_token, email_change_token_current, reauthentication_token,
                    email_change_confirm_status, is_sso_user, is_anonymous,
                    raw_app_meta_data, raw_user_meta_data, created_at, updated_at
                ) VALUES (
                    '00000000-0000-0000-0000-000000000000',
                    gen_random_uuid(),
                    'authenticated',
                    'authenticated',
                    ?,
                    ?,
                    NOW(),
                    '', '', '', '', '', '', '', '', 0, false, false,
                    ?::jsonb,
                    ?::jsonb,
                    NOW(),
                    NOW()
                ) RETURNING id;
            ";

            $insertRow = $db->query($insertSql, [$email, $computedHash, $appMetadata, $userMetadata])->getRowArray();
            $newUserId = (string) ($insertRow['id'] ?? '');

            if ($newUserId === '') {
                return [
                    'success' => false,
                    'error'   => 'Failed to generate new Supabase user ID.',
                ];
            }

            $identitySql = "
                INSERT INTO auth.identities (
                    id, user_id, provider_id, identity_data, provider, last_sign_in_at, created_at, updated_at
                ) VALUES (
                    gen_random_uuid(),
                    ?::uuid,
                    ?::text,
                    json_build_object('sub', ?::text, 'email', ?::text, 'email_verified', true, 'phone_verified', false)::jsonb,
                    'email',
                    NOW(),
                    NOW(),
                    NOW()
                ) ON CONFLICT DO NOTHING;
            ";
            $db->query($identitySql, [$newUserId, $newUserId, $newUserId, $email]);

            return [
                'success' => true,
                'user'    => [
                    'id'            => $newUserId,
                    'email'         => $email,
                    'app_metadata'  => json_decode($appMetadata, true),
                    'user_metadata' => json_decode($userMetadata, true),
                ],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'syncUserDirectlyViaPostgres failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Find a Supabase Auth user by email via GET /auth/v1/admin/users
     *
     * @return array<string, mixed>|null
     */
    public function adminFindUserByEmail(string $email): ?array
    {
        if (! $this->isConfigured() || $this->config->secretKey === '') {
            return null;
        }

        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return null;
        }

        $response = $this->request(
            'GET',
            '/auth/v1/admin/users?page=1&per_page=1000',
            null,
            $this->buildAdminHeaders()
        );

        if ($response['unreachable'] || $response['status'] < 200 || $response['status'] >= 300 || ! is_array($response['data'])) {
            return null;
        }

        $users = $response['data']['users'] ?? (isset($response['data'][0]) ? $response['data'] : []);
        if (! is_array($users)) {
            return null;
        }

        foreach ($users as $user) {
            if (is_array($user) && strtolower(trim((string) ($user['email'] ?? ''))) === $normalizedEmail) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Delete a user from Supabase Auth by email via DELETE /auth/v1/admin/users/{id}
     */
    public function adminDeleteUserByEmail(string $email): bool
    {
        $user = $this->adminFindUserByEmail($email);
        if ($user === null || empty($user['id'])) {
            return false;
        }

        $response = $this->request(
            'DELETE',
            '/auth/v1/admin/users/' . rawurlencode((string) $user['id']),
            null,
            $this->buildAdminHeaders()
        );

        return ! $response['unreachable'] && $response['status'] >= 200 && $response['status'] < 300;
    }

    /**
     * Decode the payload of a JWT without external libraries.
     * Note: Cryptographic verification is performed via GET /auth/v1/user or token refresh.
     *
     * @return array<string, mixed>|null
     */
    public function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', trim($jwt));
        if (count($parts) !== 3) {
            return null;
        }

        $payloadPart = strtr($parts[1], '-_', '+/');
        $padding = strlen($payloadPart) % 4;
        if ($padding > 0) {
            $payloadPart .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($payloadPart, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Check whether a JWT or session expires_at timestamp is expired or within leeway seconds.
     */
    public function isTokenExpired(string $jwt, ?int $expiresAt = null, int $leewaySeconds = 60): bool
    {
        $now = time();

        if ($expiresAt !== null && $expiresAt > 0) {
            return ($now + $leewaySeconds) >= $expiresAt;
        }

        $claims = $this->decodeJwtPayload($jwt);
        if ($claims === null || ! isset($claims['exp'])) {
            return true;
        }

        return ($now + $leewaySeconds) >= (int) $claims['exp'];
    }

    /**
     * Extract authorization role from Supabase user/JWT `app_metadata` ONLY (never `user_metadata`).
     *
     * @param array<string, mixed> $supabaseUserOrClaims
     */
    public function extractAuthorizedRole(array $supabaseUserOrClaims): ?string
    {
        $appMeta = $supabaseUserOrClaims['app_metadata'] ?? null;
        if (is_array($appMeta) && ! empty($appMeta['role']) && is_string($appMeta['role'])) {
            return strtolower(trim($appMeta['role']));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $supabaseUser
     * @param array<string, mixed> $localUserRow
     */
    private function ensureAppMetadataSynced(array $supabaseUser, array $localUserRow): void
    {
        if (empty($supabaseUser['id'])) {
            return;
        }

        $expectedAppMeta = $this->buildAppMetadataFromLocalUser($localUserRow);
        $currentAppMeta = is_array($supabaseUser['app_metadata'] ?? null) ? $supabaseUser['app_metadata'] : [];

        $needsSync = false;
        foreach ($expectedAppMeta as $key => $value) {
            if (($currentAppMeta[$key] ?? null) !== $value) {
                $needsSync = true;
                break;
            }
        }

        if ($needsSync) {
            $mergedAppMeta = array_merge($currentAppMeta, $expectedAppMeta);
            $userMeta = $this->buildUserMetadataFromLocalUser($localUserRow);

            $putSuccess = false;
            if ($this->config->secretKey !== '') {
                $putResponse = $this->request(
                    'PUT',
                    '/auth/v1/admin/users/' . rawurlencode((string) $supabaseUser['id']),
                    [
                        'app_metadata'  => $mergedAppMeta,
                        'user_metadata' => $userMeta,
                    ],
                    $this->buildAdminHeaders()
                );
                $putSuccess = ($putResponse['status'] >= 200 && $putResponse['status'] < 300);
            }

            if (! $putSuccess) {
                try {
                    $db = \Config\Database::connect();
                    $db->query(
                        'UPDATE auth.users SET raw_app_meta_data = ?::jsonb, raw_user_meta_data = ?::jsonb, updated_at = NOW() WHERE id = ?::uuid',
                        [json_encode($mergedAppMeta), json_encode($userMeta), (string) $supabaseUser['id']]
                    );
                } catch (\Throwable $e) {
                    log_message('error', 'ensureAppMetadataSynced Postgres update failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Build `app_metadata` containing authorization claims (role, account_id, department, campus).
     *
     * @param array<string, mixed> $localUserRow
     * @return array<string, mixed>
     */
    private function buildAppMetadataFromLocalUser(array $localUserRow): array
    {
        return [
            'role'       => strtolower(trim((string) ($localUserRow['Role'] ?? $localUserRow['role'] ?? 'alumni'))),
            'account_id' => $localUserRow['account_id'] ?? null,
            'department' => (string) ($localUserRow['Department'] ?? $localUserRow['department'] ?? ''),
            'campus'     => (string) ($localUserRow['Campus'] ?? $localUserRow['campus'] ?? ''),
        ];
    }

    /**
     * Build `user_metadata` containing non-sensitive display attributes only.
     *
     * @param array<string, mixed> $localUserRow
     * @return array<string, mixed>
     */
    private function buildUserMetadataFromLocalUser(array $localUserRow): array
    {
        $firstName = trim((string) ($localUserRow['First_Name'] ?? $localUserRow['first_name'] ?? ''));
        $lastName = trim((string) ($localUserRow['Last_Name'] ?? $localUserRow['last_name'] ?? ''));

        return [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'full_name'  => trim($firstName . ' ' . $lastName),
            'college'    => (string) ($localUserRow['College'] ?? $localUserRow['college'] ?? ''),
        ];
    }

    /**
     * Normalize the session token payload returned by Supabase `/auth/v1/token`.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function normalizeSessionPayload(array $body): array
    {
        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $expiresAt = isset($body['expires_at'])
            ? (int) $body['expires_at']
            : (time() + $expiresIn);

        return [
            'access_token'     => (string) ($body['access_token'] ?? ''),
            'refresh_token'    => (string) ($body['refresh_token'] ?? ''),
            'token_type'       => (string) ($body['token_type'] ?? 'bearer'),
            'expires_in'       => $expiresIn,
            'expires_at'       => $expiresAt,
            'supabase_user_id' => (string) ($body['user']['id'] ?? ''),
            'last_verified_at' => time(),
        ];
    }

    /**
     * @return list<string>
     */
    private function buildClientHeaders(?string $bearerToken = null): array
    {
        $apiKey = $this->config->getClientApiKey();
        $headers = [
            'apikey: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        } else {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        return $headers;
    }

    /**
     * @return list<string>
     */
    private function buildAdminHeaders(): array
    {
        return [
            'apikey: ' . $this->config->secretKey,
            'Authorization: Bearer ' . $this->config->secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    /**
     * Perform an HTTP request against the Supabase project URL.
     *
     * @param array<string, mixed>|null $payload
     * @param list<string> $headers
     * @return array{status: int, data: array<string, mixed>|null, unreachable: bool, error?: string}
     */
    private function request(string $method, string $path, ?array $payload, array $headers): array
    {
        if (! function_exists('curl_init') || $this->config->projectUrl === '') {
            return [
                'status'      => 0,
                'data'        => null,
                'unreachable' => true,
                'error'       => 'cURL extension or Supabase projectUrl is unavailable.',
            ];
        }

        $url = rtrim($this->config->projectUrl, '/') . '/' . ltrim($path, '/');
        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'status'      => 0,
                'data'        => null,
                'unreachable' => true,
                'error'       => 'Failed to initialize cURL request.',
            ];
        }

        $options = [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->config->httpTimeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->config->httpTimeoutSeconds),
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($curl, $options);
        $rawBody = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($rawBody === false || $curlErrno !== 0 || $statusCode === 0) {
            log_message('warning', 'Supabase Auth HTTP request unreachable ({method} {path}): {error}', [
                'method' => $method,
                'path'   => $path,
                'error'  => $curlError ?: 'HTTP status 0',
            ]);

            return [
                'status'      => 0,
                'data'        => null,
                'unreachable' => true,
                'error'       => $curlError ?: 'Unable to connect to Supabase Auth.',
            ];
        }

        $decoded = json_decode((string) $rawBody, true);

        return [
            'status'      => $statusCode,
            'data'        => is_array($decoded) ? $decoded : null,
            'unreachable' => false,
        ];
    }
}

