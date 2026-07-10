<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;

final class SecurityController extends Controller
{
    public function page(Request $request): string
    {
        return view('admin/security', ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice')], 'layouts/admin');
    }

    public function changePassword(Request $request): never
    {
        $current = (string) $request->input('current_password', '');
        $password = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');
        $statement = Database::connection()->prepare('SELECT password_hash FROM users WHERE id=?');
        $statement->execute([Auth::id()]);
        $hash = (string) $statement->fetchColumn();
        if (!$hash || !password_verify($current, $hash)) {
            throw new HttpException(422, 'Parola actuală nu este corectă.');
        }
        if (strlen($password) < 12 || $password !== $confirmation) {
            throw new HttpException(422, 'Parola nouă trebuie să aibă cel puțin 12 caractere și confirmarea să coincidă.');
        }
        Database::connection()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), Auth::id()]);
        Database::connection()->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'admin.password_changed','user',?,?)")->execute([Auth::id(), Auth::id(), $_SERVER['REMOTE_ADDR'] ?? null]);
        Session::flash('admin_notice', 'Parola a fost schimbată.');
        Response::redirect('/admin/setari/securitate');
    }
}
