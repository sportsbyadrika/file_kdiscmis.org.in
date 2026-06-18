<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\Flash;
use App\View;
use App\Models\User;

final class ProfileController
{
    /** GET /profile */
    public function show(): void
    {
        Auth::requireLogin();
        View::render('profile', [
            'pageTitle' => 'My Profile',
            'active'    => 'profile',
            'user'      => Auth::user(),
        ]);
    }

    /** POST /profile */
    public function update(): void
    {
        Auth::requireLogin();
        Csrf::check();

        $user     = Auth::user();
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));

        $errors = [];
        if ($fullName === '') {
            $errors['full_name'] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (User::emailTakenByOther($email, (int) $user['id'])) {
            $errors['email'] = 'That email is already in use.';
        }

        if ($errors) {
            // Re-render with submitted values so corrections are easy.
            $merged = array_merge($user, ['full_name' => $fullName, 'email' => $email]);
            View::render('profile', [
                'pageTitle' => 'My Profile',
                'active'    => 'profile',
                'user'      => $merged,
                'errors'    => $errors,
            ]);
            return;
        }

        User::updateProfile((int) $user['id'], $fullName, $email);
        Flash::success('Your profile has been updated.');
        redirect('/profile');
    }
}
