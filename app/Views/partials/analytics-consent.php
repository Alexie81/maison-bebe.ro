<aside class="consent-layer" data-consent-layer hidden aria-label="Preferințe cookie">
    <section class="consent-card" aria-labelledby="consent-title">
        <div class="consent-summary">
            <span class="consent-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3.25a8.75 8.75 0 1 0 8.75 8.75c-2.16.12-3.87-1.66-3.62-3.81a4.35 4.35 0 0 1-4.19-4.08A4 4 0 0 1 12 3.25Z"/><circle cx="8.1" cy="10.1" r="1"/><circle cx="11.6" cy="15.6" r="1"/><circle cx="7.8" cy="16.1" r=".7"/></svg>
            </span>
            <div class="consent-copy">
                <span class="consent-eyebrow">Confidențialitate</span>
                <strong id="consent-title">O experiență creată cu grijă</strong>
                <p>Cookie-urile ne ajută să înțelegem magazinul și, cu acordul tău, să îți arătăm oferte relevante.</p>
            </div>
        </div>

        <div class="consent-actions">
            <button type="button" class="consent-button consent-button-primary" data-consent-accept>Accept toate</button>
            <button type="button" class="consent-button consent-button-secondary" data-consent-customize aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M6 15v4"/></svg>
                Preferințe
            </button>
            <button type="button" class="consent-button consent-button-quiet" data-consent-reject>Doar necesare</button>
        </div>

        <div class="consent-options" data-consent-options hidden>
            <div class="consent-options-head">
                <div><span class="consent-eyebrow">Controlul este al tău</span><strong>Alege ce ne permiți</strong></div>
                <button type="button" class="consent-collapse" data-consent-customize aria-label="Închide preferințele" aria-expanded="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 14 5-5 5 5"/></svg>
                </button>
            </div>
            <div class="consent-option-grid">
                <label class="consent-option is-required">
                    <span><strong>Necesare</strong><small>Coș, autentificare și securitate</small></span>
                    <span class="consent-switch"><input type="checkbox" checked disabled><i aria-hidden="true"></i></span>
                </label>
                <label class="consent-option">
                    <span><strong>Analiză</strong><small>Performanță și traseul cumpărăturilor</small></span>
                    <span class="consent-switch"><input type="checkbox" name="consent_analytics" checked><i aria-hidden="true"></i></span>
                </label>
                <label class="consent-option">
                    <span><strong>Publicitate</strong><small>Conversii și reclame mai relevante</small></span>
                    <span class="consent-switch"><input type="checkbox" name="consent_ads" checked><i aria-hidden="true"></i></span>
                </label>
            </div>
            <div class="consent-options-footer">
                <a href="<?= e(url('/politici/cookies')) ?>">Despre cookie-uri</a>
                <button type="button" class="consent-button consent-button-primary" data-consent-save>Salvează alegerile</button>
            </div>
        </div>
    </section>
</aside>
