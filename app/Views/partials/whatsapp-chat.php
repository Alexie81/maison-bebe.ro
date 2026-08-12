<?php
$whatsAppMessage = rawurlencode('Bună! Aș dori mai multe informații despre produsele Maison Bébé.');
?>
<aside class="whatsapp-chat-widget" aria-label="Asistență pe WhatsApp">
    <a class="whatsapp-chat-link" href="https://wa.me/40726760875?text=<?= e($whatsAppMessage) ?>" target="_blank" rel="noopener noreferrer" aria-label="Deschide o conversație WhatsApp cu Maison Bébé">
        <span class="whatsapp-chat-tooltip">
            <span class="whatsapp-chat-live" aria-hidden="true"></span>
            <span><strong>Suntem aici pentru tine</strong><small>Scrie-ne pe WhatsApp<span class="whatsapp-chat-dots whatsapp-chat-tooltip-dots" aria-hidden="true"><i>.</i><i>.</i><i>.</i></span></small></span>
        </span>
        <span class="whatsapp-chat-cloud" aria-hidden="true"><span class="whatsapp-chat-dots"><i>.</i><i>.</i><i>.</i></span></span>
        <span class="whatsapp-chat-button" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479s1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.693.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 7.021 2.91 9.825 9.825 0 0 1 2.9 7.026c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.3-1.652a11.867 11.867 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0 0 20.465 3.4Z"/>
            </svg>
        </span>
    </a>
</aside>
