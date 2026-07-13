<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HtmlSanitizer;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;

final class CmsController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice')], 'layouts/admin');
    }

    public function homepage(Request $request): string
    {
        $sections = Database::connection()->query('SELECT * FROM homepage_sections ORDER BY sort_order')->fetchAll();
        $statement = Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=?');
        $statement->execute(['announcement_bar']);
        $announcement = json_decode((string) ($statement->fetchColumn() ?: ''), true) ?: ['enabled' => true, 'text' => 'Livrare gratuită pentru comenzile de peste 500 lei, pregătite cu grijă ca un cadou.'];
        return $this->admin('admin/cms-homepage', compact('sections','announcement'));
    }

    public function saveHomepage(Request $request, string $key): never
    {
        $json = trim((string) $request->input('content_json', '{}'));
        $content = json_decode($json, true);
        if (!is_array($content)) {
            throw new HttpException(422, 'Conținutul blocului trebuie să fie JSON valid.');
        }
        foreach ($content as $name => $value) {
            if (is_string($value) && str_contains(strtolower((string) $name), 'url') && !preg_match('#^(https?://|/)#', $value)) {
                throw new HttpException(422, 'URL-ul din bloc nu este permis.');
            }
        }
        $statement = Database::connection()->prepare('UPDATE homepage_sections SET title=?,content_json=?,is_active=?,sort_order=?,updated_by=? WHERE section_key=?');
        $statement->execute([trim((string) $request->input('title', '')), json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $request->input('is_active') ? 1 : 0, (int) $request->input('sort_order', 0), Auth::id(), $key]);
        Session::flash('admin_notice', 'Blocul homepage a fost actualizat.');
        Response::redirect('/admin/cms/homepage');
    }

    public function page(Request $request, string $id): string
    {
        $statement = Database::connection()->prepare('SELECT * FROM pages WHERE id=? AND deleted_at IS NULL');
        $statement->execute([(int) $id]);
        $page = $statement->fetch();
        if (!$page) {
            throw new HttpException(404, 'Pagina nu a fost găsită.');
        }
        return $this->admin('admin/cms-page', compact('page'));
    }

    public function savePage(Request $request, string $id): never
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            throw new HttpException(422, 'Titlul paginii este obligatoriu.');
        }
        $status = (string) $request->input('status', 'draft');
        if (!in_array($status, ['draft','published','archived'], true)) {
            throw new HttpException(422, 'Status invalid.');
        }
        Database::connection()->prepare('UPDATE pages SET title=?,content_html=?,status=?,robots_index=?,meta_title=?,meta_description=?,published_at=CASE WHEN ?=\'published\' THEN COALESCE(published_at,NOW()) ELSE published_at END WHERE id=?')->execute([$title, HtmlSanitizer::clean((string) $request->input('content_html', '')), $status, $request->input('robots_index') ? 1 : 0, trim((string) $request->input('meta_title', '')), trim((string) $request->input('meta_description', '')), $status, (int) $id]);
        Session::flash('admin_notice', 'Pagina a fost salvată.');
        Response::redirect('/admin/cms/pagini/' . $id);
    }
}
