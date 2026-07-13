<?php
declare(strict_types=1);

use MaisonBebe\Core\Database;

require dirname(__DIR__) . '/bootstrap.php';

$fixture = dirname(__DIR__) . '/database/fixtures/legal-pages.json';
$data = json_decode((string) file_get_contents($fixture), true, 512, JSON_THROW_ON_ERROR);
$pdo = Database::connection();

$slugMap = [
    'livrare-si-retur' => 'livrare-si-retur',
    'termeni' => 'termeni-si-conditii',
    'confidentialitate' => 'confidentialitate',
    'cookies' => 'cookies',
];
$meta = [
    'livrare-si-retur' => ['Livrare și retur | Maison Bébé', 'Informații complete despre procesarea comenzilor, livrare, retragere, retur și rambursare.'],
    'termeni-si-conditii' => ['Termeni și condiții | Maison Bébé', 'Condițiile de utilizare și cumpărare din magazinul online Maison Bébé.'],
    'confidentialitate' => ['Politica de confidențialitate | Maison Bébé', 'Cum colectează, folosește și protejează Maison Bébé datele cu caracter personal.'],
    'cookies' => ['Politica de cookie-uri | Maison Bébé', 'Informații despre cookie-urile necesare, de preferințe, analiză și marketing.'],
];

$dynamic = static function (string $text): string {
    $text = str_replace(
        'Costul livrării este afișat înainte de finalizarea comenzii. Livrare standard: [SUMĂ] lei. Livrare gratuită peste [SUMĂ] lei.',
        'Costul livrării este afișat înainte de finalizarea comenzii. Livrarea standard costă {{shipping.standard_price}}, iar livrarea este gratuită pentru comenzi de cel puțin {{shipping.free_threshold}}.',
        $text
    );
    $text = str_replace([
        'TERAUNIS MITRAS SRL', 'Maison Bébé',
        '[ADRESA COMPLETĂ]', '[ADRESA]', '[ADRESA DE RETUR]',
        '[CUI]', '[NR. REGISTRUL COMERȚULUI]', '[NR. REG. COM.]',
        '[EMAIL CONTACT]', '[EMAIL RETURURI]', '[EMAIL CONFIDENȚIALITATE]', '[EMAIL]',
        '[TELEFON]', '[NUME CURIER]', '[INCLUD TVA / NU INCLUD TVA]', '[STRIPE / NETOPIA]',
        '[1–2 zile lucrătoare]', '[2–3 zile lucrătoare]', '[DATA ACTUALIZĂRII]',
    ], [
        '{{company.legal_name}}', '{{company.trade_name}}',
        '{{company.address}}', '{{company.address}}', '{{company.return_address}}',
        '{{company.tax_id}}', '{{company.registration_number}}', '{{company.registration_number}}',
        '{{company.email}}', '{{company.returns_email}}', '{{company.privacy_email}}', '{{company.email}}',
        '{{company.phone}}', '{{shipping.courier}}', '{{company.vat_text}}', 'Stripe',
        '1–2 zile lucrătoare', '2–3 zile lucrătoare', '{{legal.updated_at}}',
    ], $text);

    if (str_contains($text, 'Exemplu: nume [COOKIE]')) {
        return 'Lista concretă a cookie-urilor este afișată și actualizată în centrul de preferințe, în funcție de serviciile active pe website.';
    }

    return preg_replace('/\[[^\]]+\]/u', 'informația afișată în website la momentul comenzii', $text) ?? $text;
};

$statement = $pdo->prepare("INSERT INTO pages (slug,title,content_html,status,robots_index,meta_title,meta_description,published_at) VALUES (?,?,?,'published',1,?,?,NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),content_html=VALUES(content_html),status='published',robots_index=1,meta_title=VALUES(meta_title),meta_description=VALUES(meta_description),published_at=COALESCE(published_at,NOW()),deleted_at=NULL");

foreach ($data['pages'] as $page) {
    $slug = $slugMap[(string) $page['id']] ?? trim((string) $page['slug'], '/');
    $html = '<div class="legal-document">';
    $html .= '<p class="legal-updated">Ultima actualizare: {{legal.updated_at}}</p>';
    foreach ($page['sections'] as $section) {
        $html .= '<section class="legal-section"><h2>' . htmlspecialchars($dynamic((string) $section['title']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>';
        foreach ($section['content'] as $paragraph) {
            $html .= '<p>' . htmlspecialchars($dynamic((string) $paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        $html .= '</section>';
    }
    $html .= '</div>';
    [$metaTitle, $metaDescription] = $meta[$slug];
    $statement->execute([$slug, $dynamic((string) $page['title']), $html, $metaTitle, $metaDescription]);
}

echo 'Imported ' . count($data['pages']) . " legal pages.\n";