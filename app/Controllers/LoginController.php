<?php

namespace App\Controllers;

use App\Models\AlumniModel;
use CodeIgniter\HTTP\ResponseInterface;

class LoginController extends BaseController
{
    private AlumniModel $alumniModel;

    public function __construct()
    {
        $this->alumniModel = new AlumniModel();
    }

    public function index(): ResponseInterface|string
    {
        if (session()->has('auth_user')) {
            $role = strtolower((string) (session('auth_user.role') ?? 'alumni'));
            $redirect = login_redirect_for_role($role);

            return redirect()->to($redirect);
        }

        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');

        return view('index', [
            'signupOptions' => $this->alumniModel->getSignupOptions(),
        ]);
    }
}
