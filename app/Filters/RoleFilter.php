<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CodeIgniter 4 Route Filter that enforces role-based access control (RBAC)
 * after Supabase Auth verification.
 *
 * Usage in Routes.php:
 *   ['filter' => 'role:staff']
 *   ['filter' => 'role:coordinator']
 *   ['filter' => 'role:developer']
 *   ['filter' => 'role:alumni']
 *   ['filter' => 'role:staff,coordinator']
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('role');

        // Ensure the user is authenticated first (runs SupabaseAuthFilter check)
        $authFilter = new SupabaseAuthFilter();
        $authResponse = $authFilter->before($request, $arguments);
        if ($authResponse instanceof ResponseInterface) {
            return $authResponse;
        }

        $authUser = session('auth_user');
        if (! is_array($authUser)) {
            return $this->forbiddenResponse($request, null, 'Unauthorized. Please log in.');
        }

        $userRole = normalize_role((string) ($authUser['role'] ?? ''));
        $allowedRoles = $this->expandAllowedRoles(is_array($arguments) ? $arguments : []);

        if ($allowedRoles !== [] && ! in_array($userRole, $allowedRoles, true)) {
            return $this->forbiddenResponse($request, $userRole, 'Forbidden. You do not have permission to access this resource.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType === '' || str_contains($contentType, 'text/html')) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->setHeader('Pragma', 'no-cache');
        }

        return $response;
    }

    /**
     * Expand role group aliases ('staff', 'coordinator', 'developer', 'alumni') into concrete role values.
     *
     * @param list<string> $arguments
     * @return list<string>
     */
    private function expandAllowedRoles(array $arguments): array
    {
        $expanded = [];

        foreach ($arguments as $arg) {
            $group = normalize_role((string) $arg);
            if ($group === '') {
                continue;
            }

            switch ($group) {
                case 'staff':
                    $expanded = array_merge($expanded, staff_roles(), ['head_admin', 'staff', 'admin']);
                    break;
                case 'coordinator':
                    $expanded = array_merge($expanded, ['coordinator', 'head_admin', 'staff', 'admin']);
                    break;
                case 'developer':
                    $expanded = array_merge($expanded, developer_roles(), ['developer', 'system_admin']);
                    break;
                case 'alumni':
                    $expanded[] = 'alumni';
                    break;
                default:
                    $expanded[] = $group;
                    break;
            }
        }

        return array_values(array_unique($expanded));
    }

    private function forbiddenResponse(RequestInterface $request, ?string $userRole, string $message): ResponseInterface
    {
        if ($this->expectsHtmlRedirect($request)) {
            if ($userRole === null || $userRole === '') {
                return redirect()->to(base_url('/'));
            }

            return redirect()->to(login_redirect_for_role($userRole));
        }

        $statusCode = ($userRole === null || $userRole === '') ? 401 : 403;

        return service('response')->setStatusCode($statusCode)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }

    private function expectsHtmlRedirect(RequestInterface $request): bool
    {
        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return false;
        }

        if (strtoupper($request->getMethod()) !== 'GET') {
            return false;
        }

        $path = strtolower(trim($request->getUri()->getPath(), '/'));

        return str_ends_with($path, '.html') || str_ends_with($path, 'staff/dashboard');
    }
}

