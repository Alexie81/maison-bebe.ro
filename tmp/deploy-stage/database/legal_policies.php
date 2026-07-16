<?php
declare(strict_types=1);

return [
    'livrare-si-retur' => [
        'title' => 'Livrare și retur',
        'meta_title' => 'Livrare și retur | Maison Bébé',
        'meta_description' => 'Află termenele și costurile de livrare, condițiile de retur și drepturile consumatorilor pentru comenzile Maison Bébé.',
        'content_html' => <<<'HTML'
<p><strong>Ultima actualizare: {{legal.updated_at}}</strong></p>
<p>Această politică se aplică produselor comandate de pe maison-bebe.ro, magazin operat de <strong>{{company.legal_name}}</strong> sub brandul {{company.trade_name}}. CUI {{company.tax_id}}, nr. Registrul Comerțului {{company.registration_number}}, sediul în {{company.address}}. Ne poți contacta la <a href="mailto:{{company.email}}">{{company.email}}</a> sau la <a href="tel:{{company.phone}}">{{company.phone}}</a>.</p>
<h2>1. Pregătirea și confirmarea comenzii</h2>
<p>Comenzile pot fi plasate online în orice moment. După plasare primești o confirmare automată de înregistrare. Acceptarea comenzii are loc după verificarea disponibilității și confirmarea procesării ori expedierii. În mod obișnuit, pregătirea durează 1–2 zile lucrătoare; produsele personalizate sau Gift Box-urile pot necesita un termen suplimentar, indicat înainte de comandă.</p>
<h2>2. Livrarea</h2>
<p>Livrarea este realizată prin {{shipping.courier}}, la adresa comunicată în checkout. Costul standard configurat este <strong>{{shipping.standard_price}}</strong>, iar livrarea devine gratuită pentru comenzile eligibile de cel puțin <strong>{{shipping.free_threshold}}</strong>. Valoarea exactă și eventualele excepții sunt afișate înaintea butonului de plasare a comenzii și prevalează față de această prezentare generală.</p>
<p>Termenul estimat de transport este, de regulă, 2–3 zile lucrătoare de la predarea către curier. Perioadele aglomerate, localitățile greu accesibile și evenimentele independente de noi pot prelungi termenul. Clientul trebuie să furnizeze o adresă și date de contact corecte.</p>
<h2>3. Primirea coletului</h2>
<p>Verifică ambalajul la livrare. Dacă observi deteriorări evidente, fotografiază coletul, solicită curierului consemnarea situației și contactează-ne cât mai repede. Drepturile legale privind neconformitățile care nu puteau fi observate la livrare nu sunt afectate.</p>
<h2>4. Dreptul de retragere în 14 zile</h2>
<p>Dacă ești consumator, te poți retrage din contractul la distanță fără a indica un motiv în termen de 14 zile calendaristice de la data la care tu sau persoana desemnată de tine intră în posesia produsului, în condițiile OUG nr. 34/2014.</p>
<p>Trimite o declarație neechivocă la <a href="mailto:{{company.returns_email}}">{{company.returns_email}}</a>, menționând numărul comenzii, produsele returnate și datele de contact. Este suficient să transmiți notificarea înainte de expirarea termenului.</p>
<h2>5. Condițiile returului</h2>
<ul><li>Expediază produsele în cel mult 14 zile de la comunicarea retragerii, la adresa {{company.return_address}}.</li><li>Poți inspecta produsul numai atât cât este necesar pentru a-i stabili natura, caracteristicile și funcționarea.</li><li>Articolele nu trebuie purtate peste limita unei probe, spălate, murdărite, deteriorate ori lipsite de accesorii.</li><li>Te rugăm să incluzi etichetele și ambalajul original când acestea mai există; lipsa ambalajului original nu anulează automat dreptul de retragere.</li><li>Poți răspunde pentru diminuarea valorii cauzată de o manipulare mai amplă decât cea necesară verificării.</li></ul>
<p>Costul direct al returului este suportat de client, cu excepția produselor neconforme, deteriorate înainte de livrare sau livrate greșit.</p>
<h2>6. Rambursarea</h2>
<p>Rambursăm sumele datorate, inclusiv costul livrării standard atunci când legea îl include, fără întârzieri nejustificate și cel târziu în 14 zile de la informarea privind retragerea. Putem amâna rambursarea până la primirea produselor sau până la prezentarea dovezii expedierii. Rambursarea se face, de regulă, prin aceeași metodă de plată, fără comisioane impuse de noi.</p>
<h2>7. Produse personalizate și excepții</h2>
<p>Dreptul de retragere nu se aplică produselor realizate după specificațiile consumatorului ori personalizate în mod clar și produselor sigilate care, din motive de protecție a sănătății sau igienă, nu pot fi returnate după desigilare, numai în măsura permisă de lege și dacă excepția a fost indicată clar înainte de cumpărare. Simpla alegere a unor produse standard pentru un Gift Box nu transformă automat produsul într-unul exceptat.</p>
<h2>8. Produse neconforme</h2>
<p>Pentru un produs defect, incomplet sau diferit de cel comandat, scrie la <a href="mailto:{{company.email}}">{{company.email}}</a>. Drepturile privind aducerea în conformitate, înlocuirea, reducerea prețului sau încetarea contractului se aplică potrivit OUG nr. 140/2021 și legislației de protecție a consumatorilor.</p>
HTML,
    ],
    'termeni-si-conditii' => [
        'title' => 'Termeni și condiții',
        'meta_title' => 'Termeni și condiții | Maison Bébé',
        'meta_description' => 'Condițiile de utilizare și cumpărare aplicabile magazinului online Maison Bébé, operat de TERAUNIS MITRAS SRL.',
        'content_html' => <<<'HTML'
<p><strong>Ultima actualizare: {{legal.updated_at}}</strong></p>
<p>Website-ul maison-bebe.ro este operat de <strong>{{company.legal_name}}</strong>, sub brandul {{company.trade_name}}, CUI {{company.tax_id}}, nr. Registrul Comerțului {{company.registration_number}}, sediul {{company.address}}, email <a href="mailto:{{company.email}}">{{company.email}}</a>, telefon <a href="tel:{{company.phone}}">{{company.phone}}</a>.</p>
<h2>1. Domeniul de aplicare</h2>
<p>Termenii reglementează accesarea website-ului, contul de client și cumpărarea produselor. Prin plasarea comenzii confirmi că ai citit Termenii, Politica de livrare și retur și Politica de confidențialitate. Nicio clauză nu limitează drepturile obligatorii acordate consumatorilor.</p>
<h2>2. Produse, imagini și disponibilitate</h2>
<p>Comercializăm articole și accesorii pentru bebeluși, Gift Box-uri și produse conexe. Facem eforturi ca descrierile, culorile și imaginile să fie corecte; afișarea poate varia în funcție de ecran. Produsele sunt disponibile în limita stocului. Coșul și favoritele nu rezervă stocul. Dacă un produs achitat nu mai poate fi furnizat, clientul va fi informat și suma aferentă va fi rambursată.</p>
<h2>3. Prețuri, TVA și promoții</h2>
<p>Prețurile sunt exprimate în lei și {{company.vat_text}}. Costurile de livrare, reducerile și totalul sunt afișate înainte de comandă. Cupoanele sunt valabile în perioada, limita de utilizări și pentru produsele ori categoriile indicate. În cazul unei erori evidente de preț sau configurare, vom informa clientul, care poate confirma prețul corect sau anula comanda.</p>
<h2>4. Contul de client</h2>
<p>Poți utiliza un cont, autentificarea Google sau fluxul disponibil fără cont. Ești responsabil pentru corectitudinea datelor, confidențialitatea parolei și activitatea din cont. Putem suspenda accesul în caz de fraudă, abuz sau încălcare gravă a acestor termeni.</p>
<h2>5. Plasarea și acceptarea comenzii</h2>
<p>Înainte de confirmare sunt afișate produsele, opțiunile, cantitățile, prețurile, reducerile, livrarea, totalul și metoda de plată. Confirmarea automată arată că solicitarea a fost primită; contractul se consideră acceptat când confirmăm procesarea sau expedierea, fără a afecta situațiile în care plata și acceptarea rezultă expres din fluxul folosit.</p>
<h2>6. Plata</h2>
<p>Metodele disponibile sunt afișate în checkout și pot include plata online cu cardul prin Stripe și ramburs la curier. Datele complete ale cardului sunt introduse în infrastructura securizată a procesatorului și nu sunt stocate de {{company.legal_name}}. O comandă cu cardul este marcată plătită numai după confirmarea procesatorului.</p>
<h2>7. Gift Box-uri și personalizare</h2>
<p>Configuratorul permite selectarea cutiei, produselor și, unde este disponibil, a unui mesaj. Clientul trebuie să verifice selecția înainte de plată. Mesajele ilegale, discriminatorii sau care încalcă drepturile altora pot fi refuzate. Excluderea de la retur pentru personalizare se aplică numai în condițiile legii și când a fost comunicată clar înainte de comandă.</p>
<h2>8. Facturare</h2>
<p>Factura este emisă pe baza datelor furnizate de client și poate fi transmisă electronic prin email sau pusă la dispoziție în cont. Clientul trebuie să verifice datele de facturare înaintea comenzii.</p>
<h2>9. Livrare, retragere și conformitate</h2>
<p>Se aplică <a href="/politici/livrare-si-retur">Politica de livrare și retur</a>. Răspundem pentru conformitatea bunurilor potrivit OUG nr. 140/2021. Clientul trebuie să respecte instrucțiunile de utilizare, întreținere și spălare.</p>
<h2>10. Recenzii și conținut trimis de utilizatori</h2>
<p>Recenziile trebuie să reflecte experiențe reale și să folosească un limbaj decent. Sunt interzise conținutul fals, ilegal, amenințător, discriminatoriu, publicitar sau care divulgă datele altor persoane. Putem modera conținutul care încalcă aceste reguli, fără a elimina o recenzie doar fiindcă este negativă.</p>
<h2>11. Proprietate intelectuală</h2>
<p>Marca, textele, fotografiile, grafica, codul și structura website-ului sunt protejate. Copierea, republicarea sau exploatarea comercială fără acord este interzisă, în afara utilizărilor permise de lege.</p>
<h2>12. Răspundere și forță majoră</h2>
<p>Nu răspundem pentru indisponibilități temporare, întârzieri ori prejudicii provocate exclusiv de evenimente în afara controlului rezonabil, fără a exclude răspunderea care nu poate fi limitată prin lege. Legăturile către servicii terțe sunt supuse și condițiilor acelor furnizori.</p>
<h2>13. Reclamații și litigii</h2>
<p>Trimite reclamațiile la <a href="mailto:{{company.email}}">{{company.email}}</a>; răspundem într-un termen rezonabil, în funcție de complexitate. Consumatorii se pot adresa Autorității Naționale pentru Protecția Consumatorilor și mecanismelor de soluționare alternativă a litigiilor. Se aplică legea română, fără a înlătura protecția obligatorie oferită consumatorului de legea aplicabilă.</p>
HTML,
    ],
    'confidentialitate' => [
        'title' => 'Politica de confidențialitate',
        'meta_title' => 'Politica de confidențialitate | Maison Bébé',
        'meta_description' => 'Cum colectează, folosește și protejează Maison Bébé datele personale ale clienților și vizitatorilor.',
        'content_html' => <<<'HTML'
<p><strong>Ultima actualizare: {{legal.updated_at}}</strong></p>
<p>Operatorul datelor este <strong>{{company.legal_name}}</strong>, sub brandul {{company.trade_name}}, CUI {{company.tax_id}}, sediul {{company.address}}. Pentru orice solicitare privind datele personale: <a href="mailto:{{company.privacy_email}}">{{company.privacy_email}}</a> sau <a href="tel:{{company.phone}}">{{company.phone}}</a>.</p>
<h2>1. Datele pe care le prelucrăm</h2>
<ul><li>Date de identificare și contact: nume, prenume, email, telefon.</li><li>Date de cont: identificator, parolă stocată criptografic, preferințe, favorite și consimțăminte.</li><li>Date de comandă: produse, variante, personalizări, mesaje cadou, adrese, livrare, AWB, retururi și reclamații.</li><li>Date de facturare: adresă, CNP sau datele firmei numai când sunt necesare și furnizate pentru factură.</li><li>Date despre plată: status, sumă și identificatori de tranzacție; datele complete ale cardului sunt procesate de Stripe și nu ajung în baza noastră de date.</li><li>Date tehnice și de securitate: adresă IP, data și ora cererilor, browser, dispozitiv, jurnale de securitate și cookie-uri necesare.</li><li>Comunicări, recenzii și înscrierea la newsletter.</li></ul>
<h2>2. Scopuri și temeiuri juridice</h2>
<table><thead><tr><th>Scop</th><th>Temei</th></tr></thead><tbody><tr><td>Cont, coș, comandă, plată, livrare și retur</td><td>Executarea contractului și demersuri precontractuale</td></tr><tr><td>Facturare, contabilitate, fiscalitate și solicitări ale autorităților</td><td>Obligație legală</td></tr><tr><td>Securitate, prevenirea fraudei, apărarea drepturilor și îmbunătățirea serviciului</td><td>Interes legitim, cu evaluarea impactului asupra drepturilor tale</td></tr><tr><td>Newsletter și comunicări comerciale</td><td>Consimțământ, care poate fi retras oricând</td></tr><tr><td>Cookie-uri neesențiale, dacă vor fi activate</td><td>Consimțământ</td></tr></tbody></table>
<h2>3. Sursele datelor</h2>
<p>Primim date direct de la tine, din utilizarea website-ului, de la procesatorul de plăți și curier pentru actualizarea tranzacției ori livrării și, dacă alegi autentificarea Google, de la Google în limitele permisiunilor afișate.</p>
<h2>4. Cui transmitem datele</h2>
<p>Putem transmite strict datele necesare către furnizorul de hosting și mentenanță, Stripe pentru plăți și prevenirea fraudei, Google pentru autentificarea aleasă, curieri, furnizori email/SMS, servicii de facturare și contabilitate, consultanți și autorități. Furnizorii acționează conform contractelor și obligațiilor legale proprii.</p>
<h2>5. Transferuri internaționale</h2>
<p>Unii furnizori globali pot prelucra date în afara Spațiului Economic European. În asemenea cazuri se folosesc mecanisme recunoscute de GDPR, precum decizii de adecvare sau clauze contractuale standard, împreună cu măsuri suplimentare când sunt necesare.</p>
<h2>6. Cât timp păstrăm datele</h2>
<ul><li>Contul: până la închiderea lui, cu excepția datelor care trebuie păstrate legal.</li><li>Comenzile, facturile și documentele contabile: pe durata impusă de legislația fiscală și contabilă aplicabilă.</li><li>Solicitările și reclamațiile: pe perioada soluționării și a termenelor legale de apărare a drepturilor.</li><li>Newsletterul: până la dezabonare sau retragerea consimțământului.</li><li>Jurnalele tehnice: pentru o perioadă limitată, proporțională cu scopul de securitate.</li></ul>
<h2>7. Drepturile tale</h2>
<p>În condițiile GDPR, poți cere accesul, rectificarea, ștergerea, restricționarea, portabilitatea și opoziția și îți poți retrage consimțământul fără a afecta prelucrarea anterioară. Poți depune o plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal. Putem solicita informații rezonabile pentru verificarea identității.</p>
<h2>8. Marketing și dezabonare</h2>
<p>Newsletterele sunt trimise abonaților eligibili și includ un link direct de dezabonare. Dezabonarea oprește comunicările comerciale, nu și mesajele necesare despre comenzi, plăți, securitate sau obligații legale.</p>
<h2>9. Datele minorilor</h2>
<p>Magazinul se adresează adulților care cumpără pentru copii. Nu solicităm în mod intenționat copiilor crearea unui cont sau furnizarea directă de date personale.</p>
<h2>10. Securitate și decizii automate</h2>
<p>Folosim conexiuni criptate, control al accesului, parole protejate criptografic, jurnalizare, backup și actualizări. Stripe poate aplica verificări automate antifraudă; nu luăm decizii exclusiv automate cu efect juridic semnificativ în afara mecanismelor necesare plății și prevenirii fraudei.</p>
<h2>11. Actualizări</h2>
<p>Politica poate fi actualizată pentru modificări tehnice, comerciale sau legislative. Versiunea curentă și data actualizării sunt publicate pe această pagină.</p>
HTML,
    ],
    'cookies' => [
        'title' => 'Politica de cookie-uri',
        'meta_title' => 'Politica de cookie-uri | Maison Bébé',
        'meta_description' => 'Lista cookie-urilor utilizate de maison-bebe.ro, scopul, durata și opțiunile de administrare.',
        'content_html' => <<<'HTML'
<p><strong>Ultima actualizare: {{legal.updated_at}}</strong></p>
<p>Această politică descrie cookie-urile și tehnologii similare folosite pe maison-bebe.ro de {{company.legal_name}}. Pentru întrebări: <a href="mailto:{{company.privacy_email}}">{{company.privacy_email}}</a>.</p>
<h2>1. Ce este un cookie</h2>
<p>Un cookie este un fișier de mici dimensiuni salvat de browser. Stocarea locală a browserului funcționează asemănător, dar nu transmite automat informația la fiecare cerere. Cookie-urile pot fi proprii site-ului sau setate de un serviciu terț accesat de utilizator.</p>
<h2>2. Cookie-uri proprii strict necesare</h2>
<table><thead><tr><th>Nume</th><th>Scop</th><th>Durată</th></tr></thead><tbody><tr><td><strong>maison_session</strong></td><td>Sesiune, autentificare, securitatea formularelor și memorarea temporară a fluxului de cumpărare.</td><td>Până la închiderea browserului; până la 30 de zile dacă alegi „Ține-mă minte”.</td></tr><tr><td><strong>maison_cart</strong></td><td>Asociază browserul cu produsele și configurările din coș.</td><td>30 de zile.</td></tr><tr><td><strong>maison_wishlist</strong></td><td>Păstrează favoritele vizitatorului care nu este autentificat.</td><td>90 de zile.</td></tr></tbody></table>
<p>Aceste cookie-uri sunt necesare pentru serviciul solicitat și nu sunt folosite pentru publicitate comportamentală.</p>
<h2>3. Stripe — plăți și prevenirea fraudei</h2>
<p>Când alegi plata cu cardul, ești direcționat către infrastructura Stripe. Stripe poate utiliza cookie-uri precum <strong>__stripe_mid</strong>, <strong>__stripe_sid</strong> și <strong>m</strong> pentru procesarea plății, securitate și prevenirea fraudei. Durata și denumirile pot fi actualizate de Stripe; acestea sunt administrate pe domeniile și conform politicii Stripe. Sunt utilizate numai când accesezi fluxul de plată.</p>
<h2>4. Google — autentificare opțională</h2>
<p>Dacă alegi „Continuă cu Google”, Google poate seta cookie-uri pe propriile domenii pentru autentificare, securitate și preferințele contului. Maison Bébé primește numai datele prezentate în ecranul de autorizare. Cookie-urile Google nu sunt instalate de pagina magazinului înainte să alegi această funcție.</p>
<h2>5. Analiză și publicitate</h2>
<p><strong>În prezent, website-ul nu are instalate Google Analytics, Meta Pixel sau alte cookie-uri de analiză ori publicitate comportamentală.</strong> Dacă asemenea servicii vor fi activate, politica și mecanismul de consimțământ vor fi actualizate înainte de folosirea lor, iar cookie-urile neesențiale nu vor fi încărcate fără opțiunea utilizatorului, când consimțământul este cerut de lege.</p>
<h2>6. Stocare locală</h2>
<p>Zona de administrare poate folosi localStorage pentru preferința temei editorului, starea ghidului și identificarea ultimei notificări văzute. Aceste valori sunt accesibile doar în browserul administratorului și nu sunt folosite pentru urmărirea clienților.</p>
<h2>7. Cum le controlezi</h2>
<p>Poți șterge sau bloca cookie-urile din setările browserului. Blocarea cookie-urilor strict necesare poate împiedica autentificarea, coșul, favoritele și finalizarea comenzii. Cookie-urile serviciilor Stripe și Google pot fi gestionate și prin opțiunile oferite de acești furnizori.</p>
<h2>8. Temeiul utilizării</h2>
<p>Cookie-urile strict necesare sunt folosite pentru transmiterea comunicației și furnizarea serviciului solicitat. Cookie-urile neesențiale vor fi folosite numai pe baza consimțământului, dacă vor fi introduse. Retragerea consimțământului nu afectează legalitatea utilizării anterioare.</p>
HTML,
    ],
];
