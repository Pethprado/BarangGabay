<?php
/**
 * Seeds demo announcements, events, and ordinances without touching users.
 * Safe to run on a live DB that already has registered accounts.
 */
require __DIR__ . '/../app/bootstrap.php';

$pdo = db();

// Find the superadmin to use as author / uploader
$admin = $pdo->query("SELECT id FROM users WHERE role = 'superadmin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    // Create one if missing
    $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, phone, address, zone, role, status, email_verified, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())"
    )->execute([
        'Super Admin',
        'admin@baranggabay.ph',
        password_hash('Admin@1234', PASSWORD_BCRYPT),
        '+63 919 123 4567',
        'Brgy. Hall Road, Barangay Bayogo, Madrid',
        null,
        'superadmin',
        'verified',
    ]);
    $adminId = (int) $pdo->lastInsertId();
    echo "Created superadmin account.\n";
} else {
    $adminId = (int) $admin['id'];
}

// Add a staff account if none exists
$staff = $pdo->query("SELECT id FROM users WHERE role = 'staff' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$staff) {
    $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, phone, address, zone, role, status, email_verified, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())"
    )->execute([
        'Rosa Santos',
        'rosa.santos@baranggabay.ph',
        password_hash('Staff@1234', PASSWORD_BCRYPT),
        '+63 919 234 5678',
        'Barangay Bayogo, Madrid',
        null,
        'staff',
        'verified',
    ]);
    $staffId = (int) $pdo->lastInsertId();
    echo "Created staff account (rosa.santos@baranggabay.ph / Staff@1234).\n";
} else {
    $staffId = (int) $staff['id'];
}

// ── Announcements ────────────────────────────────────────────────────────────
$insertAnn = $pdo->prepare(
    "INSERT IGNORE INTO announcements
     (title, slug, body, category, urgency, author_id, status, published_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
);

$announcements = [
    [
        'Paglilinis ng Pasukan ng Barangay',
        'paglilinis-ng-pasukan-ng-barangay',
        '<p>Inaanyayahan ang lahat ng residente na dumalo sa barangay clean-up sa darating na Sabado. Magdala ng face mask, guwantes, at sariling tubig. Ang lahat ng kalahok ay makakatanggap ng meryenda mula sa barangay.</p>',
        'government', 'important', $staffId, 'published',
        date('Y-m-d H:i:s', strtotime('-2 days')),
    ],
    [
        'Libreng Health Check-up sa Barangay Bayogo',
        'libreng-health-checkup-sa-zone-3',
        '<p>Magkakaroon ng libreng blood pressure at blood sugar screening sa Barangay Hall mula 8:00 AM hanggang 12:00 NN. Halina at magpa-rehistro sa health desk. Ang serbisyong ito ay libre para sa lahat ng residente ng Barangay Bayogo.</p>',
        'health', 'normal', $staffId, 'published',
        date('Y-m-d H:i:s', strtotime('-1 day')),
    ],
    [
        'Pagsasara ng Kalsadang Asahan',
        'pagsasara-ng-kalsadang-asahan',
        '<p><strong>MAHALAGANG PAUNAWA:</strong> Pansamantalang isasara ang kalsada sa Barangay Asahan para sa emergency road repair. Mangyaring magtakda ng alternatibong ruta. Inaasahang matatapos ang gawain sa loob ng 3 araw.</p>',
        'infrastructure', 'urgent', $adminId, 'published',
        date('Y-m-d H:i:s', strtotime('today')),
    ],
    [
        'Barangay Fiesta Program Schedule',
        'barangay-fiesta-program-schedule',
        '<p>Narito ang opisyal na programa ng Barangay Fiesta para sa susunod na linggo. Makibahagi sa misa, parada, at konsyerto sa plaza. Inaasahan ang lahat ng residente na makilahok sa selebrasyon ng ating barangay.</p>',
        'social', 'normal', $staffId, 'published',
        date('Y-m-d H:i:s', strtotime('+2 days')),
    ],
    [
        'Paalaala: Dapat Magmaskara sa Public Market',
        'paalaala-magmaskara-sa-public-market',
        '<p>Ipagpapatupad muli ang mandatory mask protocol sa public market habang may kaso ng flu virus sa komunidad. Tiyaking may naka-face mask at sanitaizer bago pumasok sa anumang pampublikong lugar.</p>',
        'safety', 'important', $staffId, 'published',
        date('Y-m-d H:i:s', strtotime('+1 day')),
    ],
];

foreach ($announcements as $ann) {
    $insertAnn->execute($ann);
}
echo "Seeded " . count($announcements) . " announcements.\n";

// ── Events ───────────────────────────────────────────────────────────────────
$insertEv = $pdo->prepare(
    "INSERT IGNORE INTO events
     (title, slug, description, venue, latitude, longitude,
      event_date, end_date, created_by, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
);

$events = [
    [
        'Ligtas na Pamamahagi ng Relief Goods',
        'ligtas-na-pamamahagi-ng-relief-goods',
        'Magkakaroon ng malinis at organisadong pamimigay ng relief goods para sa mga pamilyang naapektuhan ng dagdag na ulan. Dapat magdala ng valid ID at barangay clearance.',
        'Barangay Hall Plaza', '9.2263000', '126.0900000',
        date('Y-m-d H:i:s', strtotime('+3 days 08:00')),
        date('Y-m-d H:i:s', strtotime('+3 days 12:00')),
        $staffId, 'upcoming',
    ],
    [
        'Training sa Disaster Preparedness',
        'training-sa-disaster-preparedness',
        'Isang maikling seminar para sa mga kabahayan tungkol sa evacuation plan, first aid, at risk reduction ng baha at landslide. Libreng pagkain para sa lahat ng kalahok.',
        'Covered Court, Barangay Bayogo', '9.2275000', '126.0950000',
        date('Y-m-d H:i:s', strtotime('+5 days 09:00')),
        date('Y-m-d H:i:s', strtotime('+5 days 13:00')),
        $staffId, 'upcoming',
    ],
    [
        'Pambatang Palaro at Hulaan',
        'pambatang-palaro-at-hulaan',
        'Magkakaroon ng larong pang-komunidad para sa mga bata at magulang. May premyo para sa pinaka-mabuting dula at talumpati. Lahat ng bata mula 5-15 taong gulang ay maaaring sumali.',
        'Barangay Covered Court', '9.2275000', '126.0950000',
        date('Y-m-d H:i:s', strtotime('+7 days 15:00')),
        date('Y-m-d H:i:s', strtotime('+7 days 18:00')),
        $adminId, 'upcoming',
    ],
];

foreach ($events as $ev) {
    $insertEv->execute($ev);
}
echo "Seeded " . count($events) . " events.\n";

// ── Ordinances ───────────────────────────────────────────────────────────────
$insertOrd = $pdo->prepare(
    "INSERT IGNORE INTO ordinances
     (title, ordinance_no, description, category, file_url,
      enacted_date, ai_summary, ai_summary_at, uploaded_by, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
);

$ordinances = [
    [
        'Ordinance No. 2026-001: Anti-Littering Policy',
        'Ordinance No. 2026-001',
        'Nagpapatupad ng mahigpit na pagbabawal sa pagtatapon ng basura sa pampublikong lugar at sumusunod na parusa para sa mga lumalabag.',
        'Environmental',
        '/uploads/d0abac181695dc6f9f569f1b08a5fb8a-dummy.pdf',
        date('Y-m-d', strtotime('-30 days')),
        "Ang Ordinansa No. 2026-001 ay nagtatakda ng:\n• Pagbabawal sa pagtatapon ng basura sa kalsada, estero, at pampublikong lugar\n• Multa para sa mga lumalabag (P500 unang pagkakataon, P1,000 ikalawa)\n• Tamang segregation ng basura\n• Pakikipagkoordinasyon sa barangay sanitation team\n\nPara sa karagdagang impormasyon, makipag-ugnayan sa Barangay Hall.",
        date('Y-m-d H:i:s'),
        $adminId, 'active',
    ],
    [
        'Ordinance No. 2026-002: Emergency Evacuation Protocol',
        'Ordinance No. 2026-002',
        'Nagbibigay ng alituntunin para sa mabilis at maayos na paglikas ng mga residente sa panahon ng malakas na ulan at baha.',
        'Safety',
        '/uploads/d0abac181695dc6f9f569f1b08a5fb8a-dummy.pdf',
        date('Y-m-d', strtotime('-20 days')),
        "Ang Ordinansa No. 2026-002 ay nag-uutos ng:\n• Listahan ng mga evacuation center sa Barangay Bayogo\n• Responsibilidad ng barangay at residente sa panahon ng kalamidad\n• Mga paalala sa pagdadala ng mahahalagang gamit (ID, gamot, tubig)\n• Proseso ng pagbibilang ng mga residente pagkatapos ng evacuation\n\nPara sa karagdagang impormasyon, makipag-ugnayan sa Barangay Hall.",
        date('Y-m-d H:i:s'),
        $adminId, 'active',
    ],
    [
        'Ordinance No. 2026-003: Barangay Health Outreach',
        'Ordinance No. 2026-003',
        'Nagpapalawak ng libreng health check-up at vaccination outreach para sa lahat ng residente ng Barangay Bayogo.',
        'Health',
        '/uploads/d0abac181695dc6f9f569f1b08a5fb8a-dummy.pdf',
        date('Y-m-d', strtotime('-10 days')),
        null, null,
        $staffId, 'active',
    ],
    [
        'Ordinance No. 2026-004: Barangay Plaza Use Policy',
        'Ordinance No. 2026-004',
        'Nagpapataw ng gabay sa paggamit ng Barangay Plaza para sa aktibidad, clean-up, at pag-aalaga upang mapanatiling maayos ang pampublikong lugar.',
        'Government',
        '/uploads/d0abac181695dc6f9f569f1b08a5fb8a-dummy.pdf',
        date('Y-m-d', strtotime('-5 days')),
        null, null,
        $staffId, 'draft',
    ],
];

foreach ($ordinances as $ord) {
    $insertOrd->execute($ord);
}
echo "Seeded " . count($ordinances) . " ordinances.\n";

echo "\nDone! Admin login: admin@baranggabay.ph / Admin@1234\n";