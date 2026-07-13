<section class="contact-hero">
    <div class="shell contact-hero-inner">
        <div class="contact-hero-copy"><p class="eyebrow">SUNTEM APROAPE</p><h1>Cu ce te putem ajuta?</h1><p>Fie că alegi un dar, configurezi un Gift Box sau ai o întrebare despre comandă, îți răspundem cu aceeași grijă cu care pregătim fiecare colet.</p></div>
        <div class="contact-quick-grid">
            <a href="mailto:<?= e($contactEmail) ?>"><span class="contact-icon" aria-hidden="true">✉</span><small>Email</small><strong><?= e($contactEmail) ?></strong></a>
            <a href="tel:<?= e(preg_replace('/\s+/', '', $contactPhone)) ?>"><span class="contact-icon" aria-hidden="true">☎</span><small>Telefon</small><strong><?= e($contactPhone) ?></strong></a>
            <div><span class="contact-icon" aria-hidden="true">◷</span><small>Program</small><strong>Luni–Vineri, 09:00–17:00</strong></div>
        </div>
    </div>
</section>
<section class="contact-main shell section-space-small">
    <div class="contact-promise"><p class="eyebrow">UN MESAJ, APOI NE OCUPĂM NOI</p><h2>Scrie-ne liniștit.</h2><p>Completează formularul, iar mesajul ajunge direct la echipa Maison Bébé. De regulă răspundem în aceeași zi lucrătoare.</p><ol>
        <li><span>01</span><div><strong>Ne spui cu ce te ajutăm</strong><small>Alege subiectul și descrie pe scurt situația.</small></div></li>
        <li><span>02</span><div><strong>Verificăm cu atenție</strong><small>Consultăm comanda sau detaliile produsului.</small></div></li>
        <li><span>03</span><div><strong>Revenim cu o soluție</strong><small>Primești răspuns pe email sau telefon.</small></div></li>
    </ol></div>
    <form class="contact-form-card" method="post" action="<?= e(url('/contact')) ?>"><?= csrf_field() ?><div class="contact-form-head"><p class="eyebrow">MESAJUL TĂU</p><h2>Spune-ne cum te putem ajuta</h2></div>
        <?php if($sent): ?><div class="form-success" role="status">Mesajul a fost trimis cu succes. Îți răspundem cât mai curând.</div><?php endif; ?><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid"><label>Nume și prenume *<input name="name" autocomplete="name" required placeholder="Cum te numești?"></label><label>Email *<input type="email" name="email" autocomplete="email" required placeholder="adresa@email.ro"></label><label>Telefon<input type="tel" name="phone" autocomplete="tel" placeholder="07xx xxx xxx"></label><label>Subiect *<select name="subject" required><option value="">Alege subiectul</option><option>O comandă</option><option>Gift Box personalizat</option><option>Produse și mărimi</option><option>Livrare și retur</option><option>Altceva</option></select></label><label class="span-2">Mesaj *<textarea name="message" minlength="10" required placeholder="Scrie aici detaliile care ne ajută să îți răspundem cât mai bine..."></textarea></label></div>
        <button class="button contact-submit" type="submit">Trimite mesajul <span aria-hidden="true">→</span></button><small class="contact-privacy">Prin trimitere ești de acord ca datele să fie folosite pentru a răspunde solicitării tale.</small>
    </form>
</section>