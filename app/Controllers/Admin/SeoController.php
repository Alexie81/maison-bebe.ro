<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;

final class SeoController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice'), 'error' => Session::flash('admin_error')], 'layouts/admin');
    }

    public function indexability(Request $request): string
    {
        $pdo = Database::connection();
        $stats = ['products' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND robots_index=1 AND include_sitemap=1 AND deleted_at IS NULL")->fetchColumn(), 'articles' => (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status='published' AND robots_index=1 AND deleted_at IS NULL")->fetchColumn(), 'redirects' => (int) $pdo->query('SELECT COUNT(*) FROM url_redirects')->fetchColumn(), 'errors' => (int) $pdo->query("SELECT COUNT(*) FROM seo_audit_results WHERE severity='error' AND checked_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn()];
        $products = $pdo->query("SELECT id,name,slug,status,robots_index,include_sitemap,seo_title,seo_description,updated_at FROM products WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 50")->fetchAll();
        $articles = $pdo->query("SELECT id,title,slug,status,robots_index,meta_title,meta_description,updated_at FROM blog_posts WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 30")->fetchAll();
        $audits = $pdo->query('SELECT * FROM seo_audit_results ORDER BY checked_at DESC LIMIT 30')->fetchAll();
        return $this->admin('admin/seo-indexability', compact('stats', 'products', 'articles', 'audits'));
    }

    public function audit(Request $request): never
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO seo_audit_results (url,entity_type,entity_id,http_status,robots_state,canonical,in_sitemap,severity,findings_json,checked_at) SELECT CONCAT('/produs/',slug),'product',id,200,IF(robots_index=1,'index','noindex'),CONCAT('/produs/',slug),include_sitemap,IF(seo_title IS NULL OR seo_title='' OR seo_description IS NULL OR seo_description='','warning','info'),JSON_OBJECT('missing_title',seo_title IS NULL OR seo_title='','missing_description',seo_description IS NULL OR seo_description=''),NOW() FROM products WHERE status='active' AND deleted_at IS NULL");
        $pdo->exec("INSERT INTO seo_audit_results (url,entity_type,entity_id,http_status,robots_state,canonical,in_sitemap,severity,findings_json,checked_at) SELECT CONCAT('/atelier/',slug),'blog_post',id,200,IF(robots_index=1,'index','noindex'),COALESCE(canonical_url,CONCAT('/atelier/',slug)),robots_index,IF(meta_title IS NULL OR meta_title='' OR meta_description IS NULL OR meta_description='','warning','info'),JSON_OBJECT('missing_title',meta_title IS NULL OR meta_title='','missing_description',meta_description IS NULL OR meta_description=''),NOW() FROM blog_posts WHERE status='published' AND deleted_at IS NULL");
        $pdo->commit();
        Session::flash('admin_notice', 'Auditul SEO a fost actualizat pentru produsele și articolele publicate.');
        Response::redirect('/admin/seo/indexabilitate');
    }

    public function product(Request $request, string $id): string
    {
        $statement = Database::connection()->prepare('SELECT id,name,slug,status,seo_title,seo_description,robots_index,include_sitemap,canonical_url,og_title,og_description FROM products WHERE id=? AND deleted_at IS NULL');
        $statement->execute([(int) $id]);
        $product = $statement->fetch();
        if (!$product) {
            throw new HttpException(404, 'Produsul nu a fost găsit.');
        }
        return $this->admin('admin/seo-product', compact('product'));
    }

    public function saveProduct(Request $request, string $id): never
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id,slug FROM products WHERE id=?');
        $statement->execute([(int) $id]);
        $product = $statement->fetch();
        if (!$product) {
            throw new HttpException(404, 'Produsul nu a fost găsit.');
        }
        $pdo->prepare('UPDATE products SET seo_title=?,seo_description=?,canonical_url=?,og_title=?,og_description=?,robots_index=?,include_sitemap=? WHERE id=?')->execute([trim((string) $request->input('seo_title', '')), trim((string) $request->input('seo_description', '')), trim((string) $request->input('canonical_url', '')) ?: null, trim((string) $request->input('og_title', '')), trim((string) $request->input('og_description', '')), $request->input('robots_index') ? 1 : 0, $request->input('include_sitemap') ? 1 : 0, (int) $id]);
        $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('product',?,'upsert',?,'pending',NOW())")->execute([(int) $id, json_encode(['slug' => $product['slug']])]);
        Session::flash('admin_notice', 'Setările SEO ale produsului au fost actualizate.');
        Response::redirect('/admin/produse/' . $id . '/seo');
    }

    public function sitemap(Request $request): string
    {
        $pdo = Database::connection();
        $stats = ['pending' => (int) $pdo->query("SELECT COUNT(*) FROM sitemap_events WHERE status='pending'")->fetchColumn(), 'products' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND robots_index=1 AND include_sitemap=1 AND deleted_at IS NULL")->fetchColumn(), 'articles' => (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status='published' AND robots_index=1 AND deleted_at IS NULL")->fetchColumn()];
        $events = $pdo->query('SELECT * FROM sitemap_events ORDER BY created_at DESC LIMIT 30')->fetchAll();
        return $this->admin('admin/seo-sitemap', compact('stats', 'events'));
    }

    public function rebuildSitemap(Request $request): never
    {
        Database::connection()->exec("UPDATE sitemap_events SET status='processed',processed_at=NOW(),attempts=attempts+1,last_error=NULL WHERE status IN ('pending','failed')");
        Session::flash('admin_notice', 'Sitemap-ul dinamic este sincronizat cu starea curentă a bazei de date.');
        Response::redirect('/admin/seo/sitemap');
    }

    public function redirects(Request $request): string
    {
        $items = Database::connection()->query('SELECT * FROM url_redirects ORDER BY updated_at DESC LIMIT 200')->fetchAll();
        return $this->admin('admin/seo-redirects', compact('items'));
    }

    public function saveRedirect(Request $request): never
    {
        $source = '/' . ltrim(trim((string) $request->input('source_path', '')), '/');
        $target = trim((string) $request->input('target_path', ''));
        $target = $target === '' ? null : '/' . ltrim($target, '/');
        $status = (int) $request->input('http_status', 301);
        if ($source === '/' || !in_array($status, [301, 302, 410], true) || ($status !== 410 && !$target) || $source === $target) {
            throw new HttpException(422, 'Redirectul nu este valid.');
        }
        if ($target) {
            $check = Database::connection()->prepare('SELECT target_path FROM url_redirects WHERE source_path=?');
            $cursor = $target;
            for ($i = 0; $i < 15; $i++) {
                if ($cursor === $source) {
                    throw new HttpException(422, 'Redirectul ar crea o buclă.');
                }
                $check->execute([$cursor]);
                $cursor = $check->fetchColumn();
                if (!$cursor) {
                    break;
                }
            }
        }
        Database::connection()->prepare('INSERT INTO url_redirects (source_path,target_path,http_status,reason) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path),http_status=VALUES(http_status),reason=VALUES(reason)')->execute([$source, $target, $status, trim((string) $request->input('reason', ''))]);
        Session::flash('admin_notice', 'Redirectul a fost salvat și verificat contra buclelor.');
        Response::redirect('/admin/seo/redirecturi');
    }

    public function searchConsole(Request $request): string
    {
        $connection = Database::connection()->query('SELECT id,site_url,status,token_expires_at,last_error,updated_at,encrypted_credentials IS NOT NULL has_credentials FROM search_console_connections ORDER BY id LIMIT 1')->fetch() ?: null;
        return $this->admin('admin/seo-search-console', compact('connection'));
    }

    public function saveSearchConsole(Request $request): never
    {
        $site = trim((string) $request->input('site_url', absolute_url('/')));
        if (!filter_var($site, FILTER_VALIDATE_URL)) {
            throw new HttpException(422, 'Adresa proprietății nu este validă.');
        }
        $credentials = trim((string) $request->input('credentials_json', ''));
        if ($credentials !== '' && json_decode($credentials, true) === null) {
            throw new HttpException(422, 'Credențialele trebuie să fie JSON valid.');
        }
        $encrypted = $credentials !== '' ? Encryptor::encrypt($credentials) : null;
        Database::connection()->prepare("INSERT INTO search_console_connections (site_url,encrypted_credentials,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE encrypted_credentials=COALESCE(VALUES(encrypted_credentials),encrypted_credentials),status=VALUES(status),last_error=NULL")->execute([$site, $encrypted, $encrypted ? 'connected' : 'not_configured']);
        Session::flash('admin_notice', 'Conexiunea Search Console a fost salvată.');
        Response::redirect('/admin/seo/search-console');
    }
}
