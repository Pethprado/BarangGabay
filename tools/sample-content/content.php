<?php
declare(strict_types=1);

/**
 * The sample content, written as a test matrix.
 *
 * Every row exists to exercise something specific that has broken or could
 * break — the `covers` field on each says what, and the seeder prints it. This
 * is not filler: fifteen generic posts would click through fine and tell you
 * nothing.
 *
 * The setting is Barangay Bayogo, Madrid, Surigao del Sur — a coastal,
 * typhoon-exposed barangay on the Pacific side of Mindanao with a
 * Manobo-speaking community. The content reflects that: storm surge, coastal
 * clean-ups, fisherfolk, the Diwata range.
 *
 * Manobo text here is deliberately sparse and only appears where the matrix
 * calls for it. It is illustrative sample text, NOT a vetted translation —
 * the project's standing rule is that Manobo content is a draft awaiting
 * review by a speaker, and seeded demo data must not pretend otherwise.
 */

/** Days back from today, so "latest" ordering and pagination get real spread. */
function sample_announcements(): array
{
    return [
        [
            'key'      => 'storm-surge',
            'covers'   => 'Filipino original - urgent - safety - long body - HAS Manobo - cover image',
            'title'    => 'BABALA: Storm Surge at Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon',
            'category' => 'safety',
            'urgency'  => 'urgent',
            'palette'  => 'storm',
            'kicker'   => 'Babala - Safety',
            'days_ago' => 1,
            'source_lang' => 'fil',
            // Hand-written Manobo below, and no English — so the EN reader
            // gets the "no translation yet" notice, which is itself a case
            // worth being able to see.
            'auto_other'  => false,
            'auto_manobo' => false,
            // PLAIN TEXT, not HTML. Only the source-language body is Quill
            // markup; every translation column is stored as plain text and the
            // detail page escapes it and keeps the line breaks. HTML here is
            // printed to the reader as literal <p> tags.
            'manobo'   => [
                'title' => 'PAHIBALO: Kusog nga ulan ngani nga gabii — panlikas na kamo',
                'body'  => "Kusog nga ulan ngan hangin ang maabot ngani nga gabii. "
                         . "An mga pamilya nga nagpuyo duol sa baybayon, panlikas na kamo pa-barangay hall.\n\n"
                         . "Dad-a an inyo tubig, bulong, ngan mga papeles.",
            ],
            'body' => <<<'HTML'
<p><strong>Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin ng Barangay Bayogo simula alas-otso ngayong gabi.</strong> Ang PAGASA ay nagtaas na ng Signal No. 2 para sa buong Surigao del Sur.</p>

<h3>Sino ang kailangang lumikas ngayon</h3>
<ul>
<li>Lahat ng pamilyang nakatira sa loob ng 50 metro mula sa dalampasigan — Purok Bagong Silang at Purok Mangga.</li>
<li>Mga pamilyang nasa gilid ng sapa, lalo na ang may mga bata at matatanda.</li>
<li>Mga mangingisda — iangat at itali nang mabuti ang mga bangka sa itaas ng high tide line. Huwag nang bumalik sa dagat hanggang alisin ang babala.</li>
</ul>

<h3>Saan pupunta</h3>
<p>Bukas na ang <strong>Barangay Hall ng Bayogo</strong> at ang <strong>paaralang elementarya</strong> bilang evacuation centers simula alas-singko ng hapon. May tubig, sardinas, bigas at banig na nakahanda para sa unang 60 pamilya. Ang barangay tanod ay magro-roving simula alas-sais.</p>

<h3>Ano ang dadalhin</h3>
<ul>
<li>Tubig na maiinom para sa tatlong araw</li>
<li>Gamot na iniinom araw-araw, lalo na para sa may diabetes at altapresyon</li>
<li>Mga mahahalagang papeles sa plastic — birth certificate, 4Ps ID, titulo ng lupa</li>
<li>Damit, kumot at flashlight</li>
<li>Gatas at lampin kung may sanggol</li>
</ul>

<p>Huwag hintayin na tumaas ang tubig bago lumikas. Ang pinakamaraming namamatay sa storm surge ay ang mga naghintay ng huling sandali. Kung may kapitbahay kayong matanda o may kapansanan, tulungan sila.</p>

<p><strong>Kontak:</strong> Barangay Emergency Hotline — nasa Barangay Hall ang radyo 24 oras ngayong gabi. Kung walang signal ang cellphone, pumunta na lang mismo sa hall.</p>

<p>Ang pag-uulat ng mga nawawala o nangangailangan ng tulong ay sa Barangay Disaster Risk Reduction and Management Committee (BDRRMC).</p>
HTML,
        ],

        [
            'key'      => 'medical-mission',
            'covers'   => 'ENGLISH original - normal - health :: proves English -> Filipino auto-translation',
            'title'    => 'Free Medical Mission and Child Vaccination — Saturday at the Barangay Health Station',
            'category' => 'health',
            'urgency'  => 'normal',
            'palette'  => 'forest',
            'kicker'   => 'Health',
            'days_ago' => 4,
            // The whole point of this row: left on auto so the detector has to
            // read the text and file it as English. Filed as Filipino, it would
            // never receive a Filipino translation and residents reading in FIL
            // would be shown English under a Filipino badge.
            'source_lang' => 'auto',
            // And the translation is actually run, because "English -> Filipino
            // now works" is not proven by a row that was never translated. This
            // is the only row that spends the free service's daily allowance on
            // a default run; everything else is seeded untranslated on purpose.
            'auto_other'  => true,
            'auto_manobo' => true,
            'body' => <<<'HTML'
<p>The Municipal Health Office and the Barangay Health Station will hold a free medical mission for all residents of Barangay Bayogo this Saturday, from eight in the morning until three in the afternoon.</p>

<h3>Services available</h3>
<ul>
<li>General check-up and blood pressure screening</li>
<li>Free medicines for hypertension and diabetes, while stocks last</li>
<li>Child immunisation — measles, polio and the routine infant schedule</li>
<li>Prenatal check-up for expectant mothers</li>
<li>Deworming for children aged one to twelve</li>
</ul>

<p>Two doctors and four nurses from the municipal hospital will be here, along with a midwife for the prenatal consultations.</p>

<h3>What to bring</h3>
<p>Please bring any valid identification card, and your child's immunisation record booklet if you still have it. If the booklet is lost, come anyway — the health workers can check the municipal register.</p>

<p>Come early. The queue is usually long by nine in the morning, and priority numbers are given out starting at half past seven. Senior citizens, pregnant women and persons with disabilities are served first regardless of their number.</p>

<p>There is no fee for any of the services listed above.</p>
HTML,
        ],

        [
            'key'      => 'assembly-notice',
            'covers'   => 'important - GOVERNMENT category (indigo badge) - NO cover image (placeholder path)',
            'title'    => 'Paunawa: Barangay Assembly sa Ikalawang Linggo ng Buwan',
            'category' => 'government',
            'urgency'  => 'important',
            'palette'  => null,          // no cover on purpose
            'kicker'   => 'Pamahalaan',
            'days_ago' => 8,
            'source_lang' => 'fil',
            'auto_other'  => false,
            'auto_manobo' => false,
            'body' => <<<'HTML'
<p>Alinsunod sa Section 397 ng Local Government Code, magkakaroon ng <strong>Barangay Assembly</strong> ang Barangay Bayogo sa ikalawang Linggo ng buwang ito, alas-dos ng hapon, sa Barangay Hall.</p>

<h3>Mga paksang tatalakayin</h3>
<ul>
<li>Ulat ng Punong Barangay sa mga proyektong natapos at kasalukuyang isinasagawa</li>
<li>Pagsusuri ng Barangay Development Plan para sa susunod na taon</li>
<li>Katayuan ng Barangay Disaster Preparedness Plan bago magsimula ang tag-ulan</li>
<li>Bukas na talakayan — maaaring magtanong ang sinumang residente</li>
</ul>

<p>Ang assembly ay bukas sa lahat ng residente ng Barangay Bayogo. Hinihikayat ang bawat sambahayan na magpadala ng kahit isang kinatawan. Ang mga desisyong gagawin tungkol sa paggamit ng pondo ng barangay ay batay sa napagkasunduan sa pulong na ito.</p>

<p>Ang minutes ng nakaraang assembly ay maaaring basahin sa Barangay Hall tuwing oras ng opisina.</p>
HTML,
        ],

        [
            'key'      => 'appreciation',
            'covers'   => 'Body with inline style="color:#000" as if pasted from Facebook :: purifier must strip it',
            'title'    => 'Salamat sa Lahat ng Tumulong sa Coastal Clean-Up',
            'category' => 'social',
            'urgency'  => 'normal',
            'palette'  => 'sea',
            'kicker'   => 'Panlipunan',
            'days_ago' => 11,
            'source_lang' => 'fil',
            'auto_other'  => false,
            'auto_manobo' => false,
            // Deliberately dirty: exactly what Quill produces when a caption is
            // pasted out of Facebook. Every one of these colours must be gone
            // by the time this reaches the database, or the post is unreadable
            // on the dark card.
            'body' => <<<'HTML'
<p><span style="color: rgb(0, 0, 0);">|| APPRECIATION POST ||</span></p>
<p style="color:#000000;">Maraming salamat po sa 87 residente, 12 mangingisda at sa mga estudyante ng lokal na high school na sumama sa coastal clean-up noong nakaraang Sabado.</p>
<p><span style="color: rgb(24, 24, 24); background-color: rgb(255, 255, 0);">Mahigit 340 kilo ng basura ang nahakot mula sa dalampasigan — karamihan ay plastik at lumang lambat.</span></p>
<p style="color: #1a1a1a; font-family: Helvetica;">Salamat din sa Barangay Tanod sa pagbabantay at sa mga nagbigay ng pagkain at tubig para sa mga volunteer.</p>
<p><span style="color:#000;">Sa susunod na buwan po ulit. Padayon, Bayogo!</span></p>
HTML,
        ],

        [
            'key'      => 'water-interruption',
            'covers'   => 'VERY LONG body (2000+ chars) - NO Manobo :: voice-reader chunking + "no MN translation" notice',
            'title'    => 'Iskedyul ng Pagkukumpuni ng Kalsada at Pansamantalang Pagputol ng Tubig — Purok 1 hanggang Purok 5',
            'category' => 'infrastructure',
            'urgency'  => 'important',
            'palette'  => 'earth',
            'kicker'   => 'Imprastraktura',
            'days_ago' => 15,
            'source_lang' => 'fil',
            // Explicitly left untranslated: this row's job is to show what a
            // reader sees when no Manobo version exists.
            'auto_other'  => false,
            'auto_manobo' => false,
            'body' => <<<'HTML'
<p>Ipinapaalam po ng Barangay Council ng Barangay Bayogo na magsisimula na ang pagkukumpuni ng pangunahing kalsada mula sa kanto ng Barangay Hall hanggang sa dulo ng Purok 5, kasabay ng paglilinis at pagpapalit ng mga lumang tubo ng tubig na sira na mula pa noong nakaraang bagyo.</p>

<h3>Iskedyul ng trabaho</h3>
<p>Ang trabaho ay gagawin nang paunti-unti upang hindi tuluyang masara ang kalsada. Ang bawat bahagi ay tatagal ng humigit-kumulang apat na araw.</p>
<ul>
<li><strong>Unang linggo</strong> — Purok 1 at Purok 2, mula sa kanto ng Barangay Hall hanggang sa tindahan ni Aling Meding. Sarado ang kalsada sa mga sasakyan mula alas-siyete ng umaga hanggang alas-singko ng hapon. Maaari pa ring dumaan ang naglalakad at ang motorsiklo sa gilid.</li>
<li><strong>Ikalawang linggo</strong> — Purok 3, kabilang ang tulay na maliit malapit sa sapa. Dito magkakaroon ng buong pagsasara sa loob ng dalawang araw dahil papalitan ang takip ng kanal.</li>
<li><strong>Ikatlong linggo</strong> — Purok 4 at Purok 5 hanggang sa dulo malapit sa dalampasigan.</li>
</ul>

<h3>Pagputol ng tubig</h3>
<p>Kasabay ng bawat bahagi ng trabaho ay may pansamantalang pagputol ng suplay ng tubig, dahil kailangang patuyuin ang linya bago palitan ang tubo. Ang putol ay mula <strong>alas-otso ng umaga hanggang alas-kuwatro ng hapon</strong> sa mga araw ng trabaho lamang. Bumabalik ang tubig bago gumabi.</p>

<p>Hinihiling po namin sa bawat sambahayan na mag-imbak ng sapat na tubig para sa isang araw bago magsimula ang trabaho sa inyong purok. Ang mga pamilyang walang imbakan ay maaaring kumuha sa reservoir sa likod ng Barangay Hall — may drum doon na pinupuno tuwing umaga habang tumatagal ang proyekto.</p>

<h3>Mga apektadong serbisyo</h3>
<p>Ang Barangay Health Station ay may sariling tangke at magpapatuloy ang serbisyo. Ang Day Care Center ay magsasara nang alas-onse ng umaga sa mga araw na walang tubig, at ipinapaalam po namin nang maaga sa mga magulang upang makapaghanda sila ng susundo sa mga bata.</p>

<p>Ang mga negosyong umaasa sa tubig — karinderya, carwash at ang ice plant — ay pinapayuhang magplano nang maaga. Handa ang barangay na tumulong sa pag-aayos ng iskedyul kung may malaking okasyon na naka-book sa mga petsang iyon.</p>

<h3>Kaligtasan sa lugar ng trabaho</h3>
<p>Magkakaroon ng bukas na kanal at nakalantad na tubo sa ilang bahagi. Pakiusap po sa mga magulang na huwag payagang maglaro ang mga bata malapit sa lugar ng trabaho, lalo na tuwing hapon kung wala nang tao ang mga manggagawa. May ilaw na babala na ikakabit tuwing gabi, ngunit hindi ito sapat na proteksyon para sa isang batang naglalaro sa dilim.</p>

<p>Kung may makitang bahagi na walang babala o nakatumba ang barikada, ipagbigay-alam agad sa Barangay Hall o sa kahit sinong tanod.</p>

<h3>Pasasalamat</h3>
<p>Alam po naming abala ito, lalo na sa mga kailangang magpabalik-balik araw-araw para sa trabaho o paaralan. Ang kalsadang ito ay hindi pa naaayos mula pa noong bagyo dalawang taon na ang nakalilipas, at ang mga tubo ay may tagas na nauubos ang suplay ng buong purok. Salamat po sa pang-unawa.</p>

<p>Para sa anumang katanungan o reklamo, lumapit lamang po sa Barangay Hall tuwing oras ng opisina, o sa Punong Barangay mismo.</p>
HTML,
        ],
    ];
}

/**
 * Events. Dates are expressed as offsets from "now" so the status a row
 * claims and the date it carries always agree, however long after seeding the
 * site is opened.
 */
function sample_events(): array
{
    return [
        [
            'key'      => 'barangay-assembly',
            'covers'   => 'UPCOMING - has venue + lat/lng :: the Google map should render a pin',
            'title'    => 'Barangay Assembly at Konsultasyon sa Badyet ng Barangay Bayogo',
            'status'   => 'upcoming',
            'palette'  => 'slate',
            'kicker'   => 'Event - Upcoming',
            'venue'    => 'Barangay Hall, Barangay Bayogo, Madrid, Surigao del Sur',
            'lat'      => 9.2647000,
            'lng'      => 125.9633000,
            'starts_in_days' => 9,
            'duration_hours' => 3,
            'description' => <<<'HTML'
Taunang pagpupulong ng barangay para sa pagpaplano ng badyet. Tatalakayin ang mga prayoridad na proyekto para sa susunod na taon at ang alokasyon ng Barangay Development Fund.

Bukas sa lahat ng residente. May meryenda pagkatapos ng pulong.
HTML,
        ],

        [
            'key'      => 'coastal-cleanup',
            'covers'   => 'ONGOING / today :: the "happening today" path on the home page',
            'title'    => 'Coastal Clean-Up Drive sa Dalampasigan ng Barangay Bayogo',
            'status'   => 'ongoing',
            'palette'  => 'sea',
            'kicker'   => 'Event - Ngayon',
            'venue'    => 'Dalampasigan ng Barangay Bayogo',
            'lat'      => 9.2631000,
            'lng'      => 125.9658000,
            'starts_in_days' => 0,
            'duration_hours' => 6,
            'description' => <<<'HTML'
Buwanang paglilinis ng dalampasigan kasama ang mga mangingisda, estudyante at volunteer na residente.

Magdala po ng guwantes kung mayroon. May sako, tubig at meryenda na inihanda ang barangay. Magsisimula sa harap ng bangkahan at magtatapos sa dulo malapit sa sapa.
HTML,
        ],

        [
            'key'      => 'foundation-day',
            'covers'   => 'COMPLETED / past :: past-event styling and date sorting',
            'title'    => 'Araw ng Pagkakatatag ng Barangay — Fiesta at Palaro',
            'status'   => 'completed',
            'palette'  => 'forest',
            'kicker'   => 'Event - Tapos Na',
            'venue'    => 'Barangay Plaza at Covered Court, Barangay Bayogo',
            'lat'      => 9.2644000,
            'lng'      => 125.9638000,
            'starts_in_days' => -21,
            'duration_hours' => 10,
            'description' => <<<'HTML'
Selebrasyon ng ika-58 taong pagkakatatag ng Barangay Bayogo. Nagkaroon ng misa sa umaga, palaro para sa mga bata sa tanghali, at pagtatanghal ng kultura ng komunidad na Manobo sa gabi.

Salamat sa lahat ng sumali at sa mga nag-abuloy ng premyo para sa palaro.
HTML,
        ],

        [
            'key'      => 'sports-league',
            'covers'   => 'CANCELLED :: cancelled styling, and the notice must be visibly different from upcoming',
            'title'    => 'Inter-Purok Basketball League — IPINAGPALIBAN',
            'status'   => 'cancelled',
            'palette'  => 'storm',
            'kicker'   => 'Event - Kanselado',
            'venue'    => 'Covered Court, Barangay Hall Compound, Barangay Bayogo',
            'lat'      => 9.2645000,
            'lng'      => 125.9636000,
            'starts_in_days' => 5,
            'duration_hours' => 4,
            'description' => <<<'HTML'
IPINAGPALIBAN ang liga dahil sa masamang panahon at sa nasirang bubong ng covered court.

Ang mga koponang nakarehistro na ay hindi na kailangang magbayad muli. Ipapaalam ang bagong petsa sa sandaling maayos ang bubong — inaasahan sa loob ng isang buwan.

Pasensya na po sa abala.
HTML,
        ],

        [
            'key'      => 'livelihood-seminar',
            'covers'   => 'NO venue, NO coordinates, NO cover image :: every fallback at once',
            'title'    => 'Seminar sa Pangkabuhayan para sa mga Pamilyang Mangingisda',
            'status'   => 'upcoming',
            'palette'  => null,          // no cover on purpose
            'kicker'   => 'Event',
            'venue'    => null,
            'lat'      => null,
            'lng'      => null,
            'starts_in_days' => 17,
            'duration_hours' => 5,
            'description' => <<<'HTML'
Libreng seminar sa pangkabuhayan para sa mga pamilyang umaasa sa pangingisda — pagpoproseso ng isda, pag-iimbak, at maliit na negosyo.

Ang lugar ay ipapaalam pa. Naghihintay pa ng kumpirmasyon mula sa BFAR kung sa Barangay Hall o sa munisipyo gaganapin.
HTML,
        ],
    ];
}

/**
 * Ordinances.
 *
 * Every title and number carries [SAMPLE], and every body says plainly that it
 * is illustrative. An ordinance is a legal instrument; a fabricated one that
 * reads as real barangay law on a government site is worse than having no
 * sample at all, so the marking here is not decoration.
 */
function sample_ordinances(): array
{
    return [
        [
            'key'      => 'coastal-protection',
            'covers'   => 'PDF + ai_summary already filled :: the cached-summary path, no API call',
            'title'    => '[SAMPLE] Ordinansa sa Proteksyon ng Baybayin at Pagbabawal ng Basura sa Dalampasigan',
            'number'   => '[SAMPLE] Ordinance No. 2026-001',
            'category' => 'Environmental',
            'status'   => 'active',
            'days_ago' => 30,
            'summary'  => <<<'TEXT'
Ito ay halimbawang buod para sa pagsubok ng sistema — hindi ito tunay na ordinansa.

• Ipinagbabawal ang pagtatapon ng basura sa dalampasigan at sa loob ng 20 metro mula sa high tide line.
• Ang unang paglabag ay may babala; ang pangalawa ay multang PHP 500; ang pangatlo ay PHP 1,000 at community service.
• Ang bawat sambahayan malapit sa baybayin ay inaasahang lumahok sa buwanang coastal clean-up.
• Ang Barangay Tanod ang magpapatupad, katuwang ang Bantay Dagat.

Para sa karagdagang impormasyon, makipag-ugnayan sa Barangay Hall.
TEXT,
            'description' => 'HALIMBAWANG DOKUMENTO PARA SA PAGSUBOK. Isang ilustratibong ordinansa tungkol sa '
                           . 'paglilinis at proteksyon ng baybayin ng Barangay Bayogo. Hindi ito tunay na batas ng barangay.',
        ],

        [
            'key'      => 'curfew-minors',
            'covers'   => 'PDF but NO ai_summary :: the "Summarize with AI" button, incl. no-credits behaviour',
            'title'    => '[SAMPLE] Ordinansa sa Curfew para sa mga Menor de Edad',
            'number'   => '[SAMPLE] Ordinance No. 2026-002',
            'category' => 'Public Order',
            'status'   => 'active',
            'days_ago' => 22,
            'summary'  => null,          // left empty on purpose
            'description' => 'HALIMBAWANG DOKUMENTO PARA SA PAGSUBOK. Isang ilustratibong ordinansa tungkol sa oras ng '
                           . 'curfew para sa mga wala pang 18 taong gulang, at ang tungkulin ng mga magulang. '
                           . 'Hindi ito tunay na batas ng barangay.',
        ],

        [
            'key'      => 'senior-citizens',
            'covers'   => 'LONG title with n-tilde and accents :: slug generation and title truncation',
            'title'    => '[SAMPLE] Ordinansa sa Pagtatatag ng Programang Pangkalusugan at Buwanang Ayuda para sa mga '
                        . 'Senior Citizen, Nanay na Nagdadalang-tao, at mga Taong may Kapansanan sa Buong Nasasakupan ng Barangay',
            'number'   => '[SAMPLE] Ordinance No. 2026-003-Ñ',
            'category' => 'Social Welfare',
            'status'   => 'active',
            'days_ago' => 14,
            'summary'  => null,
            'description' => 'HALIMBAWANG DOKUMENTO PARA SA PAGSUBOK. Ilustratibong ordinansa tungkol sa programang '
                           . 'pangkalusugan para sa mga senior citizen, buntis at may kapansanan. Hindi ito tunay na batas.',
        ],

        [
            'key'      => 'manobo-heritage',
            'covers'   => 'HAS a Manobo translation :: the MN path end to end on an ordinance',
            'title'    => '[SAMPLE] Ordinansa sa Pagkilala at Pangangalaga sa Kultura ng Pamayanang Manobo',
            'number'   => '[SAMPLE] Ordinance No. 2026-004',
            'category' => 'Culture and Heritage',
            'status'   => 'active',
            'days_ago' => 9,
            'summary'  => null,
            'description' => 'HALIMBAWANG DOKUMENTO PARA SA PAGSUBOK. Ilustratibong ordinansa tungkol sa pagkilala sa '
                           . 'kultura at wika ng pamayanang Manobo sa Barangay Bayogo. Hindi ito tunay na batas ng barangay.',
            // Illustrative sample text, not a vetted translation. See the note
            // at the top of this file.
            'manobo' => [
                'title' => '[SAMPLE] Sugo mahitungod sa pag-amping sa kultura sa mga Manobo',
                'desc'  => 'HALIMBAWA LANG kini nga dokumento para sa pagsulay. Mahitungod sa pag-ila ug '
                         . 'pag-amping sa kultura ug pinulongan sa mga Manobo sa Barangay Bayogo. Dili kini tinuod nga balaod.',
            ],
        ],

        [
            'key'      => 'old-waste',
            'covers'   => 'OLDER / superseded (repealed) :: date sorting and the archive state',
            'title'    => '[SAMPLE] Lumang Ordinansa sa Pagtatapon ng Basura — Pinalitan Na',
            'number'   => '[SAMPLE] Ordinance No. 2019-007',
            'category' => 'Environmental',
            'status'   => 'repealed',
            'days_ago' => 120,
            'enacted_years_ago' => 7,
            'summary'  => null,
            'description' => 'HALIMBAWANG DOKUMENTO PARA SA PAGSUBOK. Lumang ilustratibong ordinansa sa pagtatapon ng '
                           . 'basura, pinalitan ng [SAMPLE] Ordinance No. 2026-001. Hindi ito tunay na batas.',
        ],
    ];
}
