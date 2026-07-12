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
use MaisonBebe\Services\NewsletterService;
use MaisonBebe\Services\UploadService;

final class EditorialController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice')], 'layouts/admin');
    }

    public function index(Request $request): string
    {
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 'p.status=?';
            $params[] = $status;
        }
        $statement = Database::connection()->prepare("SELECT p.*,CONCAT(u.first_name,' ',u.last_name) author_name,COUNT(DISTINCT r.id) revision_count FROM blog_posts p LEFT JOIN users u ON u.id=p.author_user_id LEFT JOIN blog_post_revisions r ON r.post_id=p.id WHERE " . implode(' AND ', $where) . ' GROUP BY p.id ORDER BY COALESCE(p.scheduled_at,p.published_at,p.created_at) DESC');
        $statement->execute($params);
        return $this->admin('admin/editorial-index', ['items' => $statement->fetchAll(), 'q' => $q, 'status' => $status]);
    }

    public function form(Request $request, ?string $id = null): string
    {
        $pdo = Database::connection();
        $post = null;
        $selected = [];
        if ($id !== null) {
            $statement = $pdo->prepare('SELECT p.*,m.path image_path FROM blog_posts p LEFT JOIN media_assets m ON m.id=p.featured_image_id WHERE p.id=? AND p.deleted_at IS NULL');
            $statement->execute([(int) $id]);
            $post = $statement->fetch();
            if (!$post) {
                throw new HttpException(404, 'Articolul nu a fost găsit.');
            }
            $categories = $pdo->prepare('SELECT category_id FROM blog_post_categories WHERE post_id=?');
            $categories->execute([(int) $id]);
            $selected = array_map('intval', $categories->fetchAll(\PDO::FETCH_COLUMN));
        }
        $categories = $pdo->query('SELECT * FROM blog_categories ORDER BY name')->fetchAll();
        return $this->admin('admin/editorial-form', compact('post', 'categories', 'selected'));
    }

    public function save(Request $request, ?string $id = null): never
    {
        $pdo = Database::connection();
        $title = trim((string) $request->input('title', ''));
        $slug = $this->slug((string) $request->input('slug', '') ?: $title);
        $status = (string) $request->input('status', 'draft');
        if ($title === '' || $slug === '' || !in_array($status, ['draft', 'in_review', 'scheduled', 'published', 'archived'], true)) {
            throw new HttpException(422, 'Datele articolului nu sunt valide.');
        }
        $scheduled = trim((string) $request->input('scheduled_at', '')) ?: null;
        if ($status === 'scheduled' && !$scheduled) {
            throw new HttpException(422, 'Alege data publicării pentru articolul programat.');
        }
        $content = HtmlSanitizer::clean((string) $request->input('content_html', ''));
        $imageId = (new UploadService())->image('featured_image', $title);
        (new NewsletterService())->ensureSchema($pdo);
        $pdo->beginTransaction();
        if ($id !== null) {
            $oldStatement = $pdo->prepare('SELECT * FROM blog_posts WHERE id=? FOR UPDATE');
            $oldStatement->execute([(int) $id]);
            $old = $oldStatement->fetch();
            if (!$old) {
                $pdo->rollBack();
                throw new HttpException(404, 'Articolul nu a fost găsit.');
            }
            $this->revision((int) $id, $old, 'Salvare înaintea modificării');
            $notifyNewsletter = $status === 'published' && ($old['status'] ?? '') !== 'published';
            if ($old['slug'] !== $slug) {
                $pdo->prepare('INSERT INTO url_redirects (source_path,target_path,http_status,reason,entity_type,entity_id) VALUES (?,?,301,?,?,?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path),http_status=301')->execute(['/atelier/' . $old['slug'], '/atelier/' . $slug, 'Slug articol modificat', 'blog_post', (int) $id]);
            }
            $sql = 'UPDATE blog_posts SET title=?,slug=?,excerpt=?,content_html=?,status=?,robots_index=?,canonical_url=?,meta_title=?,meta_description=?,og_title=?,og_description=?,scheduled_at=?,published_at=CASE WHEN ?=\'published\' THEN COALESCE(published_at,NOW()) ELSE published_at END' . ($imageId ? ',featured_image_id=?' : '') . ' WHERE id=?';
            $values = [$title, $slug, trim((string) $request->input('excerpt', '')), $content, $status, $request->input('robots_index') ? 1 : 0, trim((string) $request->input('canonical_url', '')) ?: null, trim((string) $request->input('meta_title', '')), trim((string) $request->input('meta_description', '')), trim((string) $request->input('og_title', '')), trim((string) $request->input('og_description', '')), $scheduled, $status];
            if ($imageId) {
                $values[] = $imageId;
            }
            $values[] = (int) $id;
            $pdo->prepare($sql)->execute($values);
            $postId = (int) $id;
        } else {
            $statement = $pdo->prepare("INSERT INTO blog_posts (author_user_id,featured_image_id,title,slug,excerpt,content_html,status,robots_index,canonical_url,meta_title,meta_description,og_title,og_description,scheduled_at,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,CASE WHEN ?='published' THEN NOW() ELSE NULL END)");
            $statement->execute([Auth::id(), $imageId, $title, $slug, trim((string) $request->input('excerpt', '')), $content, $status, $request->input('robots_index') ? 1 : 0, trim((string) $request->input('canonical_url', '')) ?: null, trim((string) $request->input('meta_title', '')), trim((string) $request->input('meta_description', '')), trim((string) $request->input('og_title', '')), trim((string) $request->input('og_description', '')), $scheduled, $status]);
            $postId = (int) $pdo->lastInsertId();
            $notifyNewsletter = $status === 'published';
        }
        $pdo->prepare('DELETE FROM blog_post_categories WHERE post_id=?')->execute([$postId]);
        $categoryStatement = $pdo->prepare('INSERT INTO blog_post_categories (post_id,category_id,is_primary) VALUES (?,?,?)');
        $primary = (int) $request->input('primary_category_id', 0);
        foreach (array_unique(array_map('intval', (array) $request->input('categories', []))) as $categoryId) {
            if ($categoryId > 0) {
                $categoryStatement->execute([$postId, $categoryId, $categoryId === $primary ? 1 : 0]);
            }
        }
        $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('blog_post',?,'upsert',?,'pending',NOW())")->execute([$postId, json_encode(['slug' => $slug, 'status' => $status])]);
        $pdo->prepare('INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,\'article.saved\',\'blog_post\',?,?)')->execute([Auth::id(), $postId, $_SERVER['REMOTE_ADDR'] ?? null]);
        if ($notifyNewsletter) (new NewsletterService())->queueArticle($pdo, $postId);
        $pdo->commit();
        Session::flash('admin_notice', 'Articolul a fost salvat.');
        Response::redirect('/admin/atelier/' . $postId . '/edit');
    }


    public function delete(Request $request, string $id): never
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT id,title,slug FROM blog_posts WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $statement->execute([(int) $id]);
            $post = $statement->fetch();
            if (!$post) {
                throw new HttpException(404, 'Articolul nu există sau a fost deja șters.');
            }
            $current = $pdo->prepare('SELECT * FROM blog_posts WHERE id=?');
            $current->execute([(int) $id]);
            $snapshot = $current->fetch();
            if ($snapshot) {
                $this->revision((int) $id, $snapshot, 'Backup înainte de arhivare');
            }
            $pdo->prepare("UPDATE blog_posts SET status='archived',robots_index=0,deleted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int) $id]);
            $pdo->prepare("INSERT INTO url_redirects (source_path,target_path,http_status,reason,entity_type,entity_id) VALUES (?,?,301,'Articol șters din Atelier','blog_post',?) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path),http_status=301,reason=VALUES(reason)")->execute(['/atelier/' . $post['slug'], '/atelier', (int) $id]);
            $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('blog_post',?,'deleted',?,'pending',NOW())")->execute([(int) $id, json_encode(['slug' => $post['slug']], JSON_UNESCAPED_UNICODE)]);
            $pdo->prepare("INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,'article.deleted','blog_post',?,?)")->execute([Auth::id(), (int) $id, $_SERVER['REMOTE_ADDR'] ?? null]);
            $pdo->commit();
            Session::flash('admin_notice', 'Articolul a fost arhivat.');
            Response::redirect('/admin/atelier');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
    public function taxonomies(Request $request): string
    {
        $categories = Database::connection()->query('SELECT c.*,COUNT(pc.post_id) posts_count FROM blog_categories c LEFT JOIN blog_post_categories pc ON pc.category_id=c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
        return $this->admin('admin/editorial-taxonomies', compact('categories'));
    }

    public function saveTaxonomy(Request $request): never
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new HttpException(422, 'Numele categoriei este obligatoriu.');
        }
        Database::connection()->prepare('INSERT INTO blog_categories (name,slug,description,is_active,is_indexable,meta_title,meta_description) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),is_active=VALUES(is_active),is_indexable=VALUES(is_indexable),meta_title=VALUES(meta_title),meta_description=VALUES(meta_description)')->execute([$name, $this->slug((string) $request->input('slug', '') ?: $name), trim((string) $request->input('description', '')), $request->input('is_active') ? 1 : 0, $request->input('is_indexable') ? 1 : 0, trim((string) $request->input('meta_title', '')), trim((string) $request->input('meta_description', ''))]);
        Session::flash('admin_notice', 'Taxonomia editorială a fost salvată.');
        Response::redirect('/admin/atelier/taxonomii');
    }

    public function calendar(Request $request): string
    {
        $items = Database::connection()->query("SELECT id,title,slug,status,COALESCE(scheduled_at,published_at,created_at) event_at FROM blog_posts WHERE deleted_at IS NULL AND status IN ('scheduled','published','draft') ORDER BY event_at")->fetchAll();
        return $this->admin('admin/editorial-calendar', compact('items'));
    }

    public function revisions(Request $request, string $id): string
    {
        $pdo = Database::connection();
        $post = $pdo->prepare('SELECT id,title,slug FROM blog_posts WHERE id=?');
        $post->execute([(int) $id]);
        $post = $post->fetch();
        if (!$post) {
            throw new HttpException(404, 'Articolul nu a fost găsit.');
        }
        $statement = $pdo->prepare("SELECT r.*,CONCAT(u.first_name,' ',u.last_name) editor_name FROM blog_post_revisions r LEFT JOIN users u ON u.id=r.editor_user_id WHERE r.post_id=? ORDER BY version_no DESC");
        $statement->execute([(int) $id]);
        return $this->admin('admin/editorial-revisions', ['post' => $post, 'items' => $statement->fetchAll()]);
    }

    public function restore(Request $request, string $id, string $revision): never
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT snapshot_json FROM blog_post_revisions WHERE id=? AND post_id=?');
        $statement->execute([(int) $revision, (int) $id]);
        $snapshot = json_decode((string) $statement->fetchColumn(), true);
        if (!$snapshot) {
            throw new HttpException(404, 'Revizia nu a fost găsită.');
        }
        $current = $pdo->prepare('SELECT * FROM blog_posts WHERE id=?');
        $current->execute([(int) $id]);
        $this->revision((int) $id, $current->fetch(), 'Backup înainte de restaurare');
        $fields = ['title','excerpt','content_html','status','robots_index','canonical_url','meta_title','meta_description','og_title','og_description'];
        $sets = [];
        $values = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $snapshot)) {
                $sets[] = $field . '=?';
                $values[] = $snapshot[$field];
            }
        }
        $values[] = (int) $id;
        $pdo->prepare('UPDATE blog_posts SET ' . implode(',', $sets) . ' WHERE id=?')->execute($values);
        Session::flash('admin_notice', 'Revizia a fost restaurată și versiunea anterioară a fost păstrată.');
        Response::redirect('/admin/atelier/' . $id . '/edit');
    }

    private function revision(int $postId, array $snapshot, string $reason): void
    {
        $pdo = Database::connection();
        $version = (int) $pdo->query('SELECT COALESCE(MAX(version_no),0)+1 FROM blog_post_revisions WHERE post_id=' . $postId)->fetchColumn();
        unset($snapshot['updated_at']);
        $pdo->prepare('INSERT INTO blog_post_revisions (post_id,editor_user_id,version_no,snapshot_json,reason) VALUES (?,?,?,?,?)')->execute([$postId, Auth::id(), $version, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $reason]);
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($value))) ?: $value;
        return trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
    }
}
