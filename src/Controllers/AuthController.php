<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\Flash;
use App\Session;
use App\View;
use App\Models\User;

final class AuthController
{
    /** GET /login */
    public function showLogin(): void
    {
        if (Auth::check() && Auth::user() !== null) {
            redirect('/dashboard');
        }
        $old = ['login' => (string) Session::get('_old_login', '')];
        Session::remove('_old_login');
        View::render('auth/login', ['old' => $old, 'pageTitle' => 'Sign in'], 'guest');
    }

    /** POST /login */
    public function login(): void
    {
        Csrf::check();

        $login    = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            Flash::error('Please enter your username/email and password.');
            Session::set('_old_login', $login);
            redirect('/login');
        }

        if (!Auth::attempt($login, $password)) {
            Flash::error('Invalid credentials. Please try again.');
            Session::set('_old_login', $login);
            redirect('/login');
        }

        Flash::success('Welcome back!');
        redirect('/dashboard');
    }

    /** POST /logout */
    public function logout(): void
    {
        Csrf::check();
        Auth::logout();
        Flash::success('You have been signed out.');
        redirect('/login');
    }

    /** GET /change-password */
    public function showChangePassword(): void
    {
        Auth::requireLogin();
        View::render('auth/change_password', ['pageTitle' => 'Change Password', 'active' => 'change-password']);
    }

    /** POST /change-password */
    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::check();

        $user    = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $errors = [];

        if ($current === '' || !password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if ($current !== '' && $new !== '' && hash_equals($current, $new)) {
            $errors['new_password'] = 'New password must be different from the current one.';
        }

        if ($errors) {
            View::render('auth/change_password', [
                'pageTitle' => 'Change Password',
                'active'    => 'change-password',
                'errors'    => $errors,
            ]);
            return;
        }

        User::updatePassword((int) $user['id'], password_hash($new, PASSWORD_BCRYPT));
        Flash::success('Your password has been updated.');
        redirect('/change-password');
    }
}
