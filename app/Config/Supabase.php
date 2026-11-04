<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Supabase extends BaseConfig
{
    /**
     * Supabase Project URL (e.g., https://<project_ref>.supabase.co)
     */
    public string $projectUrl = '';

    /**
     * Supabase Publishable / Anon Key for client/public Auth requests
     */
    public string $publishableKey = '';

    /**
     * Supabase Secret / Service Role Key (Server-side only — NEVER expose to client)
     */
    public string $secretKey = '';

    /**
     * Supabase Storage Bucket Name
     */
    public string $bucketName = 'Suvery-Responses';

    /**
     * Enable Supabase Auth integration
     */
    public bool $authEnabled = true;

    /**
     * Pure Supabase Auth mode (set to false to disable local MySQL password fallback).
     */
    public bool $authSyncFallback = false;

    /**
     * How often (in seconds) the Auth Filter should re-verify the access token
     * with Supabase's /auth/v1/user endpoint.
     */
    public int $verifyTokenIntervalSeconds = 300;

    /**
     * HTTP timeout (in seconds) for Supabase Auth REST API requests.
     */
    public int $httpTimeoutSeconds = 10;

    public function __construct()
    {
        parent::__construct();

        $this->projectUrl = rtrim(trim((string) env('supabase.projectUrl', $this->projectUrl)), '/');
        $this->publishableKey = trim((string) env('supabase.publishableKey', env('supabase.anonKey', $this->publishableKey)));
        $this->secretKey = trim((string) env('supabase.secretKey', $this->secretKey));
        $this->bucketName = trim((string) env('supabase.bucketName', $this->bucketName));
        $this->authEnabled = filter_var(env('supabase.authEnabled', true), FILTER_VALIDATE_BOOLEAN);
        $this->authSyncFallback = filter_var(env('supabase.authSyncFallback', false), FILTER_VALIDATE_BOOLEAN);
        $this->verifyTokenIntervalSeconds = max(30, (int) env('supabase.verifyTokenIntervalSeconds', 300));
        $this->httpTimeoutSeconds = max(3, (int) env('supabase.httpTimeoutSeconds', 10));
    }

    /**
     * Returns the API key to use for standard Auth endpoints (/auth/v1/token, /auth/v1/user, etc.)
     */
    public function getClientApiKey(): string
    {
        return $this->publishableKey !== '' ? $this->publishableKey : $this->secretKey;
    }

    /**
     * Returns whether Supabase Auth is configured with a URL and key.
     */
    public function isConfigured(): bool
    {
        return $this->authEnabled && $this->projectUrl !== '' && $this->getClientApiKey() !== '';
    }
}

