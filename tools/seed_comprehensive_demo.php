<?php
declare(strict_types=1);

/**
 * Comprehensive Sample Data Seeder for BarangGabay.
 *
 * Fully compatible with BOTH MySQL (local) and PostgreSQL (Render).
 * Populates realistic sample data across the entire system:
 *  - Users: Superadmin, Staff, and Residents (Michelle, Juan, Maria, Pedro)
 *  - Announcements: Urgent Safety, Health, Assembly, and Social with covers & Manobo
 *  - Events: Ongoing, upcoming basketball league, health, livelihood
 *  - Ordinances: Waste management, curfew, stray animal control, noise regulation
 *  - Evacuation Centers: Bayogo Barangay Hall, Elementary Gym, Covered Court
 *  - Document Requests: Ready & Pending requests for testing resident and staff workflows
 *  - Feedback Threads: Two-way resident inquiries and staff responses
 *  - Notifications: Live unread alerts so dashboard badges light up
 */

$root = dirname(__DIR__);
require_once $root . '/app/bootstrap.php';
require_once __DIR__ . '/sample-content/media.php';

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Ordinance;
use App\Services\FileService;
use App\Services\ManoboHybridTranslator;
use App\Services\NotificationService;
use App\Services\PostHtml;

function seed_comprehensive_demo(?PDO $pdo = null, string $driver = 'mysql'): array
{
    $pdo ??= db();
    $translator = new ManoboHybridTranslator($pdo);
    $results = [];

    echo "====================================================\n";
    echo " BarangGabay — Comprehensive Sample Data Seeder\n";
    echo " Database Driver: {$driver}\n";
    echo "====================================================\n\n";

    // ── 1. USERS ────────────────────────────────────────────────────────────
    echo "1. Seeding User Accounts...\n";
    $usersToSeed = [
        [
            'email'       => 'admin@baranggabay.ph',
            'full_name'   => 'Super Admin',
            'password'    => 'Admin@1234',
            'role'        => 'superadmin',
            'status'      => 'verified',
            'designation' => 'Punong Barangay / System Administrator',
            'phone'       => '+63 920 444 5566',
            'zone'        => null,
            'address'     => 'Barangay Hall, Bayogo, Madrid, Surigao del Sur',
        ],
        [
            'email'       => 'rosa.santos@baranggabay.ph',
            'full_name'   => 'Rosa Santos',
            'password'    => 'Staff@1234',
            'role'        => 'staff',
            'status'      => 'verified',
            'designation' => 'Barangay Secretary',
            'phone'       => '+63 919 234 5678',
            'zone'        => 'Purok 1',
            'address'     => 'Purok 1, Barangay Bayogo, Madrid, Surigao del Sur',
        ],
        [
            'email'       => 'michelle@baranggabay.ph',
            'full_name'   => 'Michelle Prado',
            'password'    => 'Resident@1234',
            'role'        => 'resident',
            'status'      => 'verified',
            'designation' => null,
            'phone'       => '+63 917 111 2233',
            'zone'        => 'Purok 1',
            'address'     => 'House 14, Purok 1, Barangay Bayogo, Madrid',
        ],
        [
            'email'       => 'juan.delacruz@baranggabay.ph',
            'full_name'   => 'Juan Dela Cruz',
            'password'    => 'Resident@1234',
            'role'        => 'resident',
            'status'      => 'verified',
            'designation' => null,
            'phone'       => '+63 918 222 3344',
            'zone'        => 'Purok 2',
            'address'     => 'Purok 2, Barangay Bayogo, Madrid, Surigao del Sur',
        ],
        [
            'email'       => 'maria.clara@baranggabay.ph',
            'full_name'   => 'Maria Clara',
            'password'    => 'Resident@1234',
            'role'        => 'resident',
            'status'      => 'verified',
            'designation' => null,
            'phone'       => '+63 919 333 4455',
            'zone'        => 'Purok 3',
            'address'     => 'Purok 3, Barangay Bayogo, Madrid, Surigao del Sur',
        ],
        [
            'email'       => 'pedro.penduko@baranggabay.ph',
            'full_name'   => 'Pedro Penduko',
            'password'    => 'Resident@1234',
            'role'        => 'resident',
            'status'      => 'pending',
            'designation' => null,
            'phone'       => '+63 917 555 6677',
            'zone'        => 'Purok 1',
            'address'     => 'Purok 1, Barangay Bayogo, Madrid, Surigao del Sur',
        ],
    ];

    $userIds = [];
    foreach ($usersToSeed as $u) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$u['email']]);
        $existingId = $stmt->fetchColumn();

        $hash = password_hash($u['password'], PASSWORD_BCRYPT);
        if ($existingId) {
            $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ?, role = ?, status = ?, designation = ?, phone = ?, zone = ?, address = ?, email_verified = 1, totp_enabled = 0 WHERE id = ?")
                ->execute([$u['full_name'], $hash, $u['role'], $u['status'], $u['designation'], $u['phone'], $u['zone'], $u['address'], $existingId]);
            $userIds[$u['email']] = (int) $existingId;
            echo "  [Updated] {$u['full_name']} <{$u['email']}> ({$u['role']}, {$u['status']})\n";
        } else {
            $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status, designation, phone, zone, address, email_verified, totp_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())")
                ->execute([$u['full_name'], $u['email'], $hash, $u['role'], $u['status'], $u['designation'], $u['phone'], $u['zone'], $u['address']]);
            $userIds[$u['email']] = (int) $pdo->lastInsertId();
            echo "  [Created] {$u['full_name']} <{$u['email']}> ({$u['role']}, {$u['status']})\n";
        }
    }
    $results['users'] = count($userIds);

    $adminId = $userIds['admin@baranggabay.ph'] ?? 1;
    $staffId = $userIds['rosa.santos@baranggabay.ph'] ?? $adminId;
    $michelleId = $userIds['michelle@baranggabay.ph'] ?? 2;
    $juanId = $userIds['juan.delacruz@baranggabay.ph'] ?? 3;

    // ── 2. ANNOUNCEMENTS ────────────────────────────────────────────────────
    echo "\n2. Seeding Announcements...\n";
    $announcementsData = [
        [
            'slug'        => 'babala-storm-surge-at-malakas-na-ulan',
            'title'       => 'BABALA: Storm Surge at Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon',
            'title_en'    => 'WARNING: Storm Surge and Heavy Rainfall Tonight — Evacuate Immediately',
            'category'    => 'safety',
            'urgency'     => 'urgent',
            'palette'     => 'storm',
            'kicker'      => 'Babala - Safety',
            'body_html'   => '<p><strong>Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin ng Barangay Bayogo simula alas-otso ngayong gabi.</strong> Ang PAGASA ay nagtaas na ng Tropical Cyclone Signal para sa lalawigan ng Surigao del Sur.</p><h3>Sino ang kailangang lumikas ngayon</h3><ul><li>Lahat ng pamilyang nakatira sa loob ng 50 metro mula sa dalampasigan sa Purok 1 at Purok 2.</li><li>Mga pamilyang nasa gilid ng sapa, lalo na ang may mga bata, buntis, at matatanda.</li><li>Mga mangingisda: iangat at itali nang maayos ang mga bangka sa itaas ng high tide line.</li></ul><h3>Saan pupunta</h3><p>Bukas na ang <strong>Bayogo Barangay Hall</strong> at <strong>Bayogo Elementary School Gym</strong> bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, at banig para sa mga lilikas.</p>',
            'days_ago'    => 1,
            'author_id'   => $adminId,
        ],
        [
            'slug'        => 'libreng-medical-mission-at-bakuna',
            'title'       => 'Libreng Medical Mission at Bakuna para sa mga Bata sa Barangay Health Center',
            'title_en'    => 'Free Medical Mission and Child Vaccination at the Barangay Health Center',
            'category'    => 'health',
            'urgency'     => 'important',
            'palette'     => 'forest',
            'kicker'      => 'Health & Wellness',
            'body_html'   => '<p>Inaanyayahan ang lahat ng residente ng Barangay Bayogo sa gaganaping <strong>Libreng Medical Mission at Bakuna para sa mga Bata</strong> ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.</p><h3>Mga Libreng Serbisyo:</h3><ul><li>Konsultasyon sa Doktor at Libreng Gamot</li><li>Pambatang Bakuna (Routine Immunization)</li><li>Blood Pressure at Fasting Blood Sugar screening</li><li>Dental Check-up at bunot ng ngipin</li></ul><p>Mangyaring magdala ng inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan ng priority number.</p>',
            'days_ago'    => 3,
            'author_id'   => $staffId,
        ],
        [
            'slug'        => 'pangkalahatang-asembleya-ng-barangay-bayogo',
            'title'       => 'Pangkalahatang Asembleya ng Barangay Bayogo para sa Taong 2026',
            'title_en'    => 'Barangay Bayogo General Assembly for the Year 2026',
            'category'    => 'government',
            'urgency'     => 'normal',
            'palette'     => 'sea',
            'kicker'      => 'Barangay Assembly',
            'body_html'   => '<p>Alinsunod sa Local Government Code, ang Sangguniang Barangay ng Bayogo ay magdaraos ng <strong>First Semester Barangay General Assembly</strong> sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.</p><p>Tatalakayin ang mga sumusunod na mahahalagang paksa:</p><ul><li>Ulat sa Pananalapi at Proyekto ng Barangay (State of Barangay Address)</li><li>Plano para sa Bagong Drainage at Kalsada sa Purok 2 at 3</li><li>Open Forum para sa mga katanungan at mungkahi ng mga mamamayan</li></ul><p>Ang inyong pagdalo at boses ay mahalaga sa patuloy na pag-unlad ng ating komunidad.</p>',
            'days_ago'    => 5,
            'author_id'   => $adminId,
        ],
        [
            'slug'        => 'coastal-cleanup-at-mangrove-planting',
            'title'       => 'Bayanihan sa Baybayin: Coastal Clean-Up at Pagtatanim ng Bakawan',
            'title_en'    => 'Coastal Clean-Up and Mangrove Planting Community Activity',
            'category'    => 'social',
            'urgency'     => 'normal',
            'palette'     => 'earth',
            'kicker'      => 'Environment & Community',
            'body_html'   => '<p>Bilang bahagi ng ating adbokasiya para sa pangangalaga ng kalikasan at proteksyon laban sa storm surge, magkakaroon tayo ng <strong>Barangay Clean-Up at Mangrove Planting</strong> sa darating na Sabado ng umaga, 6:00 AM.</p><p>Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.</p><p>Hinihikayat ang mga kabataan, mga samahan ng mangingisda, at bawat pamilya na makiisa sa bayanihan na ito. Magdala ng bota, guwantes, at reusable water tumbler. May libreng almusal at meryenda para sa mga kalahok.</p>',
            'days_ago'    => 7,
            'author_id'   => $staffId,
        ],
    ];

    $fileService = new FileService();
    $seededAnnouncements = 0;

    foreach ($announcementsData as $a) {
        $stmt = $pdo->prepare("SELECT id, cover_image_url FROM announcements WHERE slug = ? LIMIT 1");
        $stmt->execute([$a['slug']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // Generate Manobo hybrid translation using translator
        $mnTitleRes = $translator->translate($a['title'], 'fil');
        $mnBodyRes  = $translator->translate(strip_tags($a['body_html']), 'fil');
        $mnTitle    = $mnTitleRes['translation'] ?: $a['title'];
        $mnBody     = $mnBodyRes['translation'] ?: strip_tags($a['body_html']);

        // Generate cover image if missing
        $coverUrl = $existing['cover_image_url'] ?? null;
        if (empty($coverUrl)) {
            try {
                $png = sample_cover_png($a['title'], $a['palette'], $a['kicker']);
                $coverUrl = $fileService->storeFetched($png, 'announcements', ['image/png'], 8 * 1024 * 1024);
            } catch (\Throwable $e) {
                $coverUrl = null;
            }
        }

        $pubDate = date('Y-m-d H:i:s', strtotime("-{$a['days_ago']} days"));

        if ($existing) {
            $id = (int) $existing['id'];
            $pdo->prepare("UPDATE announcements SET title = ?, title_fil = ?, title_en = ?, title_manobo = ?, body = ?, body_fil = ?, body_manobo = ?, category = ?, urgency = ?, author_id = ?, cover_image_url = ?, status = 'published', published_at = ?, is_sample = 1 WHERE id = ?")
                ->execute([$a['title'], $a['title'], $a['title_en'], $mnTitle, $a['body_html'], $a['body_html'], $mnBody, $a['category'], $a['urgency'], $a['author_id'], $coverUrl, $pubDate, $id]);
            echo "  [Updated] Announcement #{$id}: {$a['title']}\n";
        } else {
            $pdo->prepare("INSERT INTO announcements (title, title_fil, title_en, title_manobo, slug, body, body_fil, body_manobo, category, urgency, author_id, cover_image_url, status, published_at, is_sample, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, 1, NOW(), NOW())")
                ->execute([$a['title'], $a['title'], $a['title_en'], $mnTitle, $a['slug'], $a['body_html'], $a['body_html'], $mnBody, $a['category'], $a['urgency'], $a['author_id'], $coverUrl, $pubDate]);
            $id = (int) $pdo->lastInsertId();
            echo "  [Created] Announcement #{$id}: {$a['title']}\n";
        }
        $seededAnnouncements++;
    }
    $results['announcements'] = $seededAnnouncements;

    // ── 3. EVENTS ───────────────────────────────────────────────────────────
    echo "\n3. Seeding Events...\n";
    $eventsData = [
        [
            'slug'        => 'inter-purok-basketball-league-2026',
            'title'       => 'Barangay Bayogo Inter-Purok Basketball League 2026',
            'title_en'    => 'Barangay Bayogo Inter-Purok Basketball League 2026',
            'venue'       => 'Bayogo Multi-Purpose Covered Court',
            'lat'         => 9.2741,
            'lng'         => 125.9612,
            'palette'     => 'earth',
            'kicker'      => 'Sports & Youth',
            'desc_html'   => '<p>Opisyal nang magbubukas ang taunang <strong>Inter-Purok Basketball Tournament</strong> kung saan maglalaban-laban ang mga kinatawan mula Purok 1, 2, at 3 para sa kampeonato. Hinihikayat ang lahat na manood at suportahan ang inyong mga purok teams!</p>',
            'status'      => 'ongoing',
            'starts_at'   => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'ends_at'     => date('Y-m-d H:i:s', strtotime('+6 hours')),
            'creator_id'  => $staffId,
        ],
        [
            'slug'        => 'dengue-prevention-at-misting-operation',
            'title'       => 'Operasyon Kontra Dengue: Fogging at Misting sa Buong Barangay',
            'title_en'    => 'Anti-Dengue Operation: Fogging and Misting Across the Barangay',
            'venue'       => 'Purok 1, Purok 2, at Purok 3',
            'lat'         => 9.2745,
            'lng'         => 125.9615,
            'palette'     => 'forest',
            'kicker'      => 'Health & Sanitation',
            'desc_html'   => '<p>Magsasagawa ang Barangay Health Sanitation Team ng malawakang fogging at misting operation upang sugpuin ang mga lamok na nagdadala ng dengue. Pakiusap sa lahat na takpan ang mga imbakan ng tubig at pagkain habang isinasagawa ang operasyon.</p>',
            'status'      => 'upcoming',
            'starts_at'   => date('Y-m-d 08:00:00', strtotime('+2 days')),
            'ends_at'     => date('Y-m-d 12:00:00', strtotime('+2 days')),
            'creator_id'  => $staffId,
        ],
        [
            'slug'        => 'livelihood-workshop-organic-farming',
            'title'       => 'Pagsasanay sa Pangkabuhayan: Organic Vegetable Farming & Backyard Gardening',
            'title_en'    => 'Livelihood Workshop: Organic Vegetable Farming & Backyard Gardening',
            'venue'       => 'Barangay Bayogo Demo Farm & Hall',
            'lat'         => 9.2738,
            'lng'         => 125.9608,
            'palette'     => 'sea',
            'kicker'      => 'Livelihood & Agriculture',
            'desc_html'   => '<p>Libreng seminar at praktikal na pagsasanay sa organikong pagtatanim ng gulay at paggawa ng compost fertilizer. Bawat kalahok ay makakatanggap ng libreng binhi at starter planting kit mula sa Department of Agriculture.</p>',
            'status'      => 'upcoming',
            'starts_at'   => date('Y-m-d 09:00:00', strtotime('+5 days')),
            'ends_at'     => date('Y-m-d 16:00:00', strtotime('+5 days')),
            'creator_id'  => $adminId,
        ],
        [
            'slug'        => 'mother-and-child-health-day',
            'title'       => 'Araw ng Kalusugan para sa Ina at Sanggol',
            'title_en'    => 'Mother and Child Health and Nutrition Day',
            'venue'       => 'Bayogo Barangay Health Station',
            'lat'         => 9.2740,
            'lng'         => 125.9610,
            'palette'     => 'slate',
            'kicker'      => 'Maternal Health',
            'desc_html'   => '<p>Espesyal na araw ng prenatal check-up, pamamahagi ng bitamina para sa mga buntis at nagpapasusong ina, at feeding program para sa mga batang edad 0 hanggang 5 taon.</p>',
            'status'      => 'upcoming',
            'starts_at'   => date('Y-m-d 08:30:00', strtotime('+8 days')),
            'ends_at'     => date('Y-m-d 14:00:00', strtotime('+8 days')),
            'creator_id'  => $staffId,
        ],
    ];

    $seededEvents = 0;
    foreach ($eventsData as $ev) {
        $stmt = $pdo->prepare("SELECT id, cover_image_url FROM events WHERE slug = ? LIMIT 1");
        $stmt->execute([$ev['slug']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $mnTitleRes = $translator->translate($ev['title'], 'fil');
        $mnDescRes  = $translator->translate(strip_tags($ev['desc_html']), 'fil');
        $mnTitle    = $mnTitleRes['translation'] ?: $ev['title'];
        $mnDesc     = $mnDescRes['translation'] ?: strip_tags($ev['desc_html']);

        $coverUrl = $existing['cover_image_url'] ?? null;
        if (empty($coverUrl)) {
            try {
                $png = sample_cover_png($ev['title'], $ev['palette'], $ev['kicker']);
                $coverUrl = $fileService->storeFetched($png, 'events', ['image/png'], 8 * 1024 * 1024);
            } catch (\Throwable $e) {
                $coverUrl = null;
            }
        }

        if ($existing) {
            $id = (int) $existing['id'];
            $pdo->prepare("UPDATE events SET title = ?, title_fil = ?, title_en = ?, title_manobo = ?, description = ?, description_fil = ?, description_manobo = ?, venue = ?, latitude = ?, longitude = ?, event_date = ?, end_date = ?, status = ?, cover_image_url = ?, is_sample = 1 WHERE id = ?")
                ->execute([$ev['title'], $ev['title'], $ev['title_en'], $mnTitle, $ev['desc_html'], $ev['desc_html'], $mnDesc, $ev['venue'], $ev['lat'], $ev['lng'], $ev['starts_at'], $ev['ends_at'], $ev['status'], $coverUrl, $id]);
            echo "  [Updated] Event #{$id}: {$ev['title']} ({$ev['status']})\n";
        } else {
            $pdo->prepare("INSERT INTO events (title, title_fil, title_en, title_manobo, slug, description, description_fil, description_manobo, venue, latitude, longitude, event_date, end_date, status, cover_image_url, created_by, is_sample, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())")
                ->execute([$ev['title'], $ev['title'], $ev['title_en'], $mnTitle, $ev['slug'], $ev['desc_html'], $ev['desc_html'], $mnDesc, $ev['venue'], $ev['lat'], $ev['lng'], $ev['starts_at'], $ev['ends_at'], $ev['status'], $coverUrl, $ev['creator_id']]);
            $id = (int) $pdo->lastInsertId();
            echo "  [Created] Event #{$id}: {$ev['title']} ({$ev['status']})\n";
        }
        $seededEvents++;
    }
    $results['events'] = $seededEvents;

    // ── 4. ORDINANCES ───────────────────────────────────────────────────────
    echo "\n4. Seeding Ordinances...\n";
    $ordinancesData = [
        [
            'number'      => 'ORD-2026-001',
            'title'       => 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon ng Basura at Pagbabawal sa Single-Use Plastics',
            'title_en'    => 'Barangay Ordinance No. 01-2026: Ecological Solid Waste Management and Single-Use Plastic Regulation',
            'category'    => 'Kalinisan at Kapaligiran',
            'description' => 'Ipinagbabawal ang pagtatapon ng basura sa mga kanal, estero, baybayin, at pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay ng nabubulok (biodegradable) at di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul ng koleksyon.',
            'ai_summary'  => 'Mahigpit na ipinagbabawal ang pagkakalat sa kalsada at dalampasigan. Obligado ang pagbubukod ng basura sa tahanan bago ang araw ng koleksyon. May kaukulang multa mula Php 500 hanggang Php 1,500 o community service para sa lalabag.',
            'enacted'     => '2026-01-15',
            'status'      => 'active',
            'uploader_id' => $adminId,
        ],
        [
            'number'      => 'ORD-2026-002',
            'title'       => 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa mga Kabataan mula 10:00 PM hanggang 4:00 AM',
            'title_en'    => 'Barangay Ordinance No. 02-2026: Curfew Hours for Minors from 10:00 PM to 4:00 AM',
            'category'    => 'Kapayapaan at Kaayusan',
            'description' => 'Para sa kaligtasan at kapakanan ng kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan at pampublikong lugar mula alas-diyes ng gabi hanggang alas-kuwatro ng madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad ng emergency o pag-aaral.',
            'ai_summary'  => 'Bawal gumala ang mga 17 anyos pababa mula 10 PM hanggang 4 AM para sa kanilang kaligtasan. Ang mga magulang ng paulit-ulit na mahuhuli ay sasailalim sa counseling at posibleng pananagutan.',
            'enacted'     => '2026-02-01',
            'status'      => 'active',
            'uploader_id' => $adminId,
        ],
        [
            'number'      => 'ORD-2026-003',
            'title'       => 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga ng Hayop at Paghuli sa Pagala-galang Aso',
            'title_en'    => 'Barangay Ordinance No. 03-2026: Responsible Pet Ownership and Stray Animal Control',
            'category'    => 'Kaligtasan at Kalusugan',
            'description' => 'Inaatasan ang lahat ng nagmamay-ari ng aso at pusa sa Barangay Bayogo na iparehistro at pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala ng mga hayop sa labas ng bakuran nang walang tali o tagapangalaga.',
            'ai_summary'  => 'Lahat ng aso at pusa ay dapat nakarehistro, may bakuna sa rabies, at nakatali kung ilalabas sa kalsada. Huhulihin ng barangay tanod ang mga pagala-galang hayop para sa kaligtasan ng mga dumaraan.',
            'enacted'     => '2026-02-20',
            'status'      => 'active',
            'uploader_id' => $staffId,
        ],
        [
            'number'      => 'ORD-2026-004',
            'title'       => 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit ng Videoke at Sound System sa mga Pamayanan',
            'title_en'    => 'Barangay Ordinance No. 04-2026: Noise Regulation and Videoke Hours in Residential Areas',
            'category'    => 'Kapayapaan at Kaayusan',
            'description' => 'Upang matiyak ang kapayapaan at maayos na pamamahinga ng mga mamamayan, pinahihintulutan lamang ang paggamit ng videoke, karaoke, at malalakas na amplifier hanggang alas-diyes ng gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas ng tunog upang hindi makaabala sa mga kapitbahay.',
            'ai_summary'  => 'Hanggang 10:00 PM lamang puwedeng mag-videoke o magpatugtog nang malakas sa pamayanan upang makapagpahinga ang mga mag-aaral at nagtatrabaho. Ang paglabag ay may babala sa unang beses at multa sa susunod.',
            'enacted'     => '2026-03-01',
            'status'      => 'active',
            'uploader_id' => $adminId,
        ],
    ];

    $seededOrdinances = 0;
    foreach ($ordinancesData as $ord) {
        $stmt = $pdo->prepare("SELECT id, file_url FROM ordinances WHERE ordinance_no = ? LIMIT 1");
        $stmt->execute([$ord['number']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $mnTitleRes = $translator->translate($ord['title'], 'fil');
        $mnDescRes  = $translator->translate($ord['description'], 'fil');
        $mnTitle    = $mnTitleRes['translation'] ?: $ord['title'];
        $mnDesc     = $mnDescRes['translation'] ?: $ord['description'];

        $fileUrl = $existing['file_url'] ?? null;
        if (empty($fileUrl)) {
            try {
                $lines = [
                    'Ordinance Reference: ' . $ord['number'],
                    'Category: ' . $ord['category'],
                    'Enacted Date: ' . $ord['enacted'],
                    '',
                    'EXPLANATORY NOTE AND PURPOSE:',
                    ...explode("\n", wordwrap($ord['description'], 70)),
                    '',
                    'Barangay Bayogo, Madrid, Surigao del Sur'
                ];
                $pdf = sample_pdf($ord['title'], $lines);
                $fileUrl = $fileService->storeFetched($pdf, 'ordinances', ['application/pdf'], 8 * 1024 * 1024);
            } catch (\Throwable $e) {
                $fileUrl = '/uploads/ordinances/sample-ordinance.pdf';
            }
        }

        if ($existing) {
            $id = (int) $existing['id'];
            $pdo->prepare("UPDATE ordinances SET title = ?, title_fil = ?, title_en = ?, title_manobo = ?, description = ?, description_fil = ?, description_manobo = ?, category = ?, file_url = ?, enacted_date = ?, ai_summary = ?, ai_summary_at = NOW(), status = ?, is_sample = 1 WHERE id = ?")
                ->execute([$ord['title'], $ord['title'], $ord['title_en'], $mnTitle, $ord['description'], $ord['description'], $mnDesc, $ord['category'], $fileUrl, $ord['enacted'], $ord['ai_summary'], $ord['status'], $id]);
            echo "  [Updated] Ordinance #{$id}: {$ord['number']} - {$ord['title']}\n";
        } else {
            $pdo->prepare("INSERT INTO ordinances (title, title_fil, title_en, title_manobo, ordinance_no, description, description_fil, description_manobo, category, file_url, enacted_date, ai_summary, ai_summary_at, status, uploaded_by, is_sample, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 1, NOW(), NOW())")
                ->execute([$ord['title'], $ord['title'], $ord['title_en'], $mnTitle, $ord['number'], $ord['description'], $ord['description'], $mnDesc, $ord['category'], $fileUrl, $ord['enacted'], $ord['ai_summary'], $ord['status'], $ord['uploader_id']]);
            $id = (int) $pdo->lastInsertId();
            echo "  [Created] Ordinance #{$id}: {$ord['number']} - {$ord['title']}\n";
        }
        $seededOrdinances++;
    }
    $results['ordinances'] = $seededOrdinances;

    // ── 5. EVACUATION CENTERS ───────────────────────────────────────────────
    echo "\n5. Seeding Evacuation Centers...\n";
    $centersData = [
        [
            'name'           => 'Bayogo Barangay Hall (Disaster Command Center)',
            'purok'          => 'Purok 1',
            'address'        => 'Barangay Hall Road, Purok 1, Bayogo, Madrid',
            'lat'            => 9.2741,
            'lng'            => 125.9612,
            'capacity'       => 120,
            'contact_person' => 'Brgy. Captain / BDRRMC Chief',
            'contact_phone'  => '+63 920 444 5566',
        ],
        [
            'name'           => 'Bayogo Elementary School Gymnasium',
            'purok'          => 'Purok 2',
            'address'        => 'Elementary School Compound, Purok 2, Bayogo',
            'lat'            => 9.2748,
            'lng'            => 125.9620,
            'capacity'       => 250,
            'contact_person' => 'School DRRM Coordinator',
            'contact_phone'  => '+63 919 234 5678',
        ],
        [
            'name'           => 'Bayogo Multi-Purpose Covered Court',
            'purok'          => 'Purok 3',
            'address'        => 'Plaza Area, Purok 3, Bayogo, Madrid',
            'lat'            => 9.2735,
            'lng'            => 125.9605,
            'capacity'       => 300,
            'contact_person' => 'Barangay Tanod Officer-in-Charge',
            'contact_phone'  => '+63 919 345 6789',
        ],
    ];

    $seededCenters = 0;
    foreach ($centersData as $c) {
        $stmt = $pdo->prepare("SELECT id FROM evacuation_centers WHERE name = ? LIMIT 1");
        $stmt->execute([$c['name']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE evacuation_centers SET purok = ?, address = ?, latitude = ?, longitude = ?, capacity = ?, contact_person = ?, contact_phone = ?, is_active = 1 WHERE id = ?")
                ->execute([$c['purok'], $c['address'], $c['lat'], $c['lng'], $c['capacity'], $c['contact_person'], $c['contact_phone'], $existingId]);
            echo "  [Updated] Evacuation Center: {$c['name']}\n";
        } else {
            $pdo->prepare("INSERT INTO evacuation_centers (name, purok, address, latitude, longitude, capacity, contact_person, contact_phone, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())")
                ->execute([$c['name'], $c['purok'], $c['address'], $c['lat'], $c['lng'], $c['capacity'], $c['contact_person'], $c['contact_phone']]);
            echo "  [Created] Evacuation Center: {$c['name']}\n";
        }
        $seededCenters++;
    }
    $results['evacuation_centers'] = $seededCenters;

    // ── 6. DOCUMENT REQUESTS ────────────────────────────────────────────────
    echo "\n6. Seeding Document Requests...\n";
    $docsData = [
        [
            'reference_no'  => 'BRGY-2026-001',
            'user_id'       => $michelleId,
            'document_type' => 'Barangay Clearance',
            'purpose'       => 'Local Employment / Job Application',
            'notes'         => 'Kailangan po para sa requirements sa munisipyo.',
            'status'        => 'ready',
            'staff_note'    => 'Handa na po para sa pick-up sa Barangay Hall counter 1. Magdala ng 1 valid ID.',
            'handled_by'    => $staffId,
        ],
        [
            'reference_no'  => 'BRGY-2026-002',
            'user_id'       => $michelleId,
            'document_type' => 'Certificate of Indigency',
            'purpose'       => 'Medical Assistance / PhilHealth Claim',
            'notes'         => 'Para po sa tulong pinansyal sa ospital ng aking lola.',
            'status'        => 'pending',
            'staff_note'    => null,
            'handled_by'    => null,
        ],
        [
            'reference_no'  => 'BRGY-2026-003',
            'user_id'       => $juanId,
            'document_type' => 'Certificate of Residency',
            'purpose'       => 'Bank Account Opening / Valid ID',
            'notes'         => 'Katunayan ng paninirahan sa Purok 2.',
            'status'        => 'released',
            'staff_note'    => 'Nakuha na ng residente noong nakaraang araw.',
            'handled_by'    => $staffId,
        ],
    ];

    $seededDocs = 0;
    foreach ($docsData as $d) {
        $stmt = $pdo->prepare("SELECT id FROM document_requests WHERE reference_no = ? LIMIT 1");
        $stmt->execute([$d['reference_no']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE document_requests SET user_id = ?, document_type = ?, purpose = ?, notes = ?, status = ?, staff_note = ?, handled_by = ? WHERE id = ?")
                ->execute([$d['user_id'], $d['document_type'], $d['purpose'], $d['notes'], $d['status'], $d['staff_note'], $d['handled_by'], $existingId]);
            echo "  [Updated] Document Request: {$d['reference_no']} ({$d['document_type']} - {$d['status']})\n";
        } else {
            $pdo->prepare("INSERT INTO document_requests (reference_no, user_id, document_type, purpose, notes, status, staff_note, handled_by, requested_at, ready_at, released_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())")
                ->execute([$d['reference_no'], $d['user_id'], $d['document_type'], $d['purpose'], $d['notes'], $d['status'], $d['staff_note'], $d['handled_by']]);
            echo "  [Created] Document Request: {$d['reference_no']} ({$d['document_type']} - {$d['status']})\n";
        }
        $seededDocs++;
    }
    $results['document_requests'] = $seededDocs;

    // ── 7. FEEDBACK & MESSAGES ──────────────────────────────────────────────
    echo "\n7. Seeding Feedback Threads & Messages...\n";
    $feedbackThreads = [
        [
            'user_id'     => $michelleId,
            'subject'     => 'Ulat ukol sa pundidong ilaw sa kalsada sa Purok 1',
            'message'     => 'Magandang araw po sa ating barangay council. Nais ko pong i-report ang pundidong ilaw sa poste ng kalsada malapit sa Purok 1 chapel. Medyo madilim po sa gabi at delikado sa mga batang naglalakad pauwi galing eskwela.',
            'staff_reply' => 'Magandang araw Michelle. Maraming salamat sa iyong pag-uulat. Naitala na po ito sa ating maintenance log at nakaiskedyul na bukas ng umaga ang ating barangay electrician upang palitan ang bumbilya.',
        ],
        [
            'user_id'     => $juanId,
            'subject'     => 'Mungkahi para sa karagdagang basurahan sa Purok 2',
            'message'     => 'Maaari po bang maglagay ng karagdagang communal trash bins sa bukana ng Purok 2 para sa mga mangingisda pag-ahon mula sa baybayin? Maraming salamat po.',
            'staff_reply' => 'Magandang araw Juan. Magandang mungkahi ito. Isasama po natin ito sa adyenda ng susunod na barangay session at makikipag-ugnayan sa MENRO.',
        ],
    ];

    $seededFeedback = 0;
    foreach ($feedbackThreads as $fb) {
        $stmt = $pdo->prepare("SELECT id FROM feedbacks WHERE user_id = ? AND message LIKE ? LIMIT 1");
        $stmt->execute([$fb['user_id'], mb_substr($fb['message'], 0, 40) . '%']);
        $existingFbId = $stmt->fetchColumn();

        if (!$existingFbId) {
            $pdo->prepare("INSERT INTO feedbacks (user_id, message, admin_reply, replied_at, replied_by, is_read_admin, created_at) VALUES (?, ?, ?, NOW(), ?, 1, NOW())")
                ->execute([$fb['user_id'], $fb['message'], $fb['staff_reply'], $staffId]);
            $fbId = (int) $pdo->lastInsertId();

            // Insert resident message
            $pdo->prepare("INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at) VALUES (?, ?, 'resident', ?, 1, 1, NOW())")
                ->execute([$fbId, $fb['user_id'], $fb['message']]);

            // Insert staff reply message
            $pdo->prepare("INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at) VALUES (?, ?, 'staff', ?, 1, 1, NOW())")
                ->execute([$fbId, $staffId, $fb['staff_reply']]);

            echo "  [Created] Feedback Thread #{$fbId}: {$fb['subject']}\n";
            $seededFeedback++;
        } else {
            echo "  [Existing] Feedback Thread #{$existingFbId}\n";
        }
    }
    $results['feedbacks'] = $seededFeedback;

    // ── 8. NOTIFICATIONS ────────────────────────────────────────────────────
    echo "\n8. Seeding Notifications...\n";
    $notifications = [
        [
            'user_id'      => $michelleId,
            'title'        => 'Handa na ang inyong Barangay Clearance',
            'message'      => 'Ang inyong hiniling na Barangay Clearance (Ref: BRGY-2026-001) ay handa na para sa pick-up sa Barangay Hall.',
            'type'         => 'system',
            'related_type' => 'document_request',
            'is_read'      => 0, // Unread alert for Michelle
        ],
        [
            'user_id'      => $michelleId,
            'title'        => 'BABALA: Storm Surge at Malakas na Ulan',
            'message'      => 'Inaasahan ang storm surge sa baybayin ng Bayogo. Bukas ang Barangay Hall bilang evacuation center.',
            'type'         => 'announcement',
            'related_type' => 'announcement',
            'is_read'      => 0, // Unread alert for Michelle
        ],
        [
            'user_id'      => $michelleId,
            'title'        => 'Maligayang Pagdating sa BarangGabay!',
            'message'      => 'Na-verify na ang inyong resident account. Maaari na kayong mag-access ng mga anunsyo, kaganapan, at humiling ng mga dokumento.',
            'type'         => 'verification',
            'related_type' => 'user',
            'is_read'      => 1, // Already read
        ],
        [
            'user_id'      => $juanId,
            'title'        => 'Pangkalahatang Asembleya ng Barangay Bayogo',
            'message'      => 'Inaanyayahan ang lahat sa General Assembly ngayong Linggo sa Bayogo Multi-Purpose Covered Court.',
            'type'         => 'announcement',
            'related_type' => 'announcement',
            'is_read'      => 0, // Unread alert for Juan
        ],
    ];

    $seededNotifs = 0;
    foreach ($notifications as $n) {
        $stmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ? LIMIT 1");
        $stmt->execute([$n['user_id'], $n['title']]);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$n['user_id'], $n['title'], $n['message'], $n['type'], $n['related_type'], $n['is_read']]);
            $seededNotifs++;
        }
    }
    echo "  Seeded {$seededNotifs} notification(s).\n";
    $results['notifications'] = $seededNotifs;

    echo "\n====================================================\n";
    echo " Sample Data Seeding Complete!\n";
    echo " Test Accounts:\n";
    echo "  1. Super Admin: admin@baranggabay.ph / Admin@1234 (Staff Door: /login?as=staff)\n";
    echo "  2. Staff:       rosa.santos@baranggabay.ph / Staff@1234 (Staff Door: /login?as=staff)\n";
    echo "  3. Resident:    michelle@baranggabay.ph / Resident@1234 (Resident Door: /login)\n";
    echo "  4. Resident:    juan.delacruz@baranggabay.ph / Resident@1234 (Resident Door: /login)\n";
    echo "====================================================\n\n";

    return $results;
}

// If run from command line directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'mysql';
    seed_comprehensive_demo(db(), $driver);
}
