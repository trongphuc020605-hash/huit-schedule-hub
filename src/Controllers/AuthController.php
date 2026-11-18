<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use HuitSchedule\Core\Auth;
use HuitSchedule\Core\Flash;
use HuitSchedule\Core\Response;
use HuitSchedule\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::isAdmin() ? '/dashboard' : '/portal');
        }

        View::render('auth/login', [
            'pageTitle' => 'Đăng nhập hệ thống',
            'flashes'   => Flash::consume(),
        ], null);
    }

    public function login(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Flash::setOld(['email' => $email]);
            Flash::error('Vui lòng nhập đầy đủ email và mật khẩu.');
            Response::redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            Flash::setOld(['email' => $email]);
            Flash::error('Email hoặc mật khẩu không chính xác.');
            Response::redirect('/login');
        }

        Flash::clearOld();
        $user = Auth::user();
        Flash::success('Xin chào ' . ($user['full_name'] ?? '') . '! Đăng nhập thành công.');
        Response::redirect(Auth::isAdmin() ? '/dashboard' : '/portal');
    }

    public function logout(): void
    {
        Auth::logout();
        Flash::info('Bạn đã đăng xuất khỏi hệ thống.');
        Response::redirect('/login');
    }
}
