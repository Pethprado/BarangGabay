<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE ai_chat_logs');
$pdo->exec('TRUNCATE TABLE audit_logs');
$pdo->exec('TRUNCATE TABLE media_files');
$pdo->exec('TRUNCATE TABLE notifications');
$pdo->exec('TRUNCATE TABLE ordinances');
$pdo->exec('TRUNCATE TABLE events');
$pdo->exec('TRUNCATE TABLE announcements');
$pdo->exec('TRUNCATE TABLE users');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

function insertUser(PDO $pdo, string $fullName, string $email, string $password, string $role, string $status = 'verified', int $emailVerified = 1, ?string $zone = null): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, phone, address, zone, role, status, email_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([
        $fullName,
        $email,
        password_hash($password, PASSWORD_BCRYPT),
        '+63 919 123 4567',
        'Brgy. Hall Road, Barangay Bayogo, Madrid',
        $zone,
        $role,
        $status,
        $emailVerified,
    ]);
    return (int) $pdo->lastInsertId();
}

function insertAnnouncement(PDO $pdo, string $title, string $slug, string $body, string $category, string $urgency, int $authorId, string $status, string $publishedAt): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO announcements (title, slug, body, category, urgency, author_id, status, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$title, $slug, $body, $category, $urgency, $authorId, $status, $publishedAt]);
}

function insertEvent(PDO $pdo, string $title, string $slug, string $description, string $venue, string $latitude, string $longitude, string $eventDate, ?string $endDate, int $createdBy, string $status): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO events (title, slug, description, venue, latitude, longitude, event_date, end_date, created_by, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$title, $slug, $description, $venue, $latitude, $longitude, $eventDate, $endDate, $createdBy, $status]);
}

function insertOrdinance(PDO $pdo, string $title, string $ordinanceNo, ?string $description, ?string $category, string $fileUrl, ?string $enactedDate, ?string $aiSummary, int $uploadedBy, string $status): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO ordinances (title, ordinance_no, description, category, file_url, enacted_date, ai_summary, ai_summary_at, uploaded_by, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([
        $title,
        $ordinanceNo,
        $description,
        $category,
        $fileUrl,
        $enactedDate,
        $aiSummary,
        $aiSummary ? date('Y-m-d H:i:s') : null,
        $uploadedBy,
        $status,
    ]);
}

$superadminId = insertUser($pdo, 'Super Admin', 'admin@baranggabay.ph', 'Admin@1234', 'superadmin', 'verified', 1);
$staff1Id = insertUser($pdo, 'Rosa Santos', 'rosa.santos@baranggabay.ph', 'Staff@1234', 'staff');
$staff2Id = insertUser($pdo, 'Miguel dela Cruz', 'miguel.delacruz@baranggabay.ph', 'Staff@1234', 'staff');

$residentIds = [];
$residentIds[] = insertUser($pdo, 'Carla Rivera', 'carla.rivera@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'John Paolo Gutierrez', 'johnpaolo.gutierrez@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Liza Navarro', 'liza.navarro@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Elias Mercado', 'elias.mercado@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Aileen Ramos', 'aileen.ramos@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Noel Bautista', 'noel.bautista@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Mara Velasco', 'mara.velasco@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Renan Magtulis', 'renan.magtulis@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Kristine Oliva', 'kristine.oliva@barangay.ph', 'Resident@123', 'resident');
$residentIds[] = insertUser($pdo, 'Joel Martinez', 'joel.martinez@barangay.ph', 'Resident@123', 'resident');

insertAnnouncement(
    $pdo,
    'Paglilinis ng Pasukan ng Barangay',
    'paglilinis-ng-pasukan-ng-barangay',
    'Inaanyayahan ang lahat ng residente na dumalo sa barangay clean-up sa darating na Sabado. Magdala ng face mask, guwantes, at sariling tubig.',
    'government',
    'important',
    $staff1Id,
    'published',
    date('Y-m-d H:i:s', strtotime('-2 days'))
);

insertAnnouncement(
    $pdo,
    'Libreng Health Check-up sa Barangay Bayogo',
    'libreng-health-checkup-sa-zone-3',
    'Magkakaroon ng libreng blood pressure at blood sugar screening sa Barangay Hall mula 8:00 AM hanggang 12:00 NN. Halina at magpa-rehistro sa health desk.',
    'health',
    'normal',
    $staff2Id,
    'published',
    date('Y-m-d H:i:s', strtotime('-1 day'))
);

insertAnnouncement(
    $pdo,
    'Pagsasara ng Kalsadang Asahan',
    'pagsasara-ng-kalsadang-asahan',
    'Pansamantalang isasara ang kalsada sa Barangay Asahan para sa emergency road repair. Mangyaring magtakda ng alternatibong ruta.',
    'infrastructure',
    'urgent',
    $staff1Id,
    'published',
    date('Y-m-d H:i:s', strtotime('today'))
);

insertAnnouncement(
    $pdo,
    'Barangay Fiesta Program Schedule',
    'barangay-fiesta-program-schedule',
    'Narito ang opisyal na programa ng Barangay Fiesta para sa susunod na linggo. Makibahagi sa misa, parada, at konsyerto sa plaza.',
    'social',
    'normal',
    $staff2Id,
    'published',
    date('Y-m-d H:i:s', strtotime('+2 days'))
);

insertAnnouncement(
    $pdo,
    'Paalaala: Dapat Magmaskara sa Public Market',
    'paalaala-magmaskara-sa-public-market',
    'Ipagpapatupad muli ang mandatory mask protocol sa public market habang may kaso ng flu virus. Tiyaking may naka-face mask at sanitaizer.',
    'safety',
    'important',
    $staff1Id,
    'published',
    date('Y-m-d H:i:s', strtotime('+1 day'))
);

insertEvent(
    $pdo,
    'Ligtas na Pamamahagi ng Relief Goods',
    'ligtas-na-pamamahagi-ng-relief-goods',
    'Magkakaroon ng malinis at organisadong pamimigay ng relief goods para sa mga pamilyang naapektuhan ng dagdag na ulan. Dapat magdala ng valid ID.',
    'Barangay Hall Plaza',
    '9.2263000',
    '126.0900000',
    date('Y-m-d H:i:s', strtotime('+3 days 08:00')),
    date('Y-m-d H:i:s', strtotime('+3 days 12:00')),
    $staff1Id,
    'upcoming'
);

insertEvent(
    $pdo,
    'Training sa Disaster Preparedness',
    'training-sa-disaster-preparedness',
    'Isang maikling seminar para sa mga kabahayan tungkol sa evacuation plan, first aid, at risk reduction ng baha at landslide.',
    'Covered Court, Barangay Bayogo',
    '9.2275000',
    '126.0950000',
    date('Y-m-d H:i:s', strtotime('+5 days 09:00')),
    date('Y-m-d H:i:s', strtotime('+5 days 13:00')),
    $staff2Id,
    'upcoming'
);

insertEvent(
    $pdo,
    'Pambatang Palaro at Hulaan',
    'pambatang-palaro-at-hulaan',
    'Magkakaroon ng larong pang-komunidad para sa mga bata at magulang. May premyo para sa pinaka-mabuting dula at talumpati.',
    'Barangay Covered Court',
    '9.2275000',
    '126.0950000',
    date('Y-m-d H:i:s', strtotime('+7 days 15:00')),
    date('Y-m-d H:i:s', strtotime('+7 days 18:00')),
    $staff1Id,
    'upcoming'
);

insertOrdinance(
    $pdo,
    'Ordinance No. 2026-001: Anti-Littering Policy',
    'Ordinance No. 2026-001',
    'Nagpapatupad ng mahigpit na pagbabawal sa pagtatapon ng basura sa pampublikong lugar at sumusunod na parusa para sa mga lumalabag.',
    'Environmental',
    '/uploads/ordinances/ordinance-2026-001.pdf',
    date('Y-m-d', strtotime('-30 days')),
    'Ang Ordinansa No. 2026-001 ay nagtatakda ng pagbabawal sa pagtatapon ng basura sa kalsada, estero, at pampublikong lugar. May paunang babala at multa para sa hindi sumunod. Mahalaga ang tamang segregation at pakikipagkoordinasyon sa barangay sanitation team.',
    $staff2Id,
    'active'
);

insertOrdinance(
    $pdo,
    'Ordinance No. 2026-002: Emergency Evacuation Protocol',
    'Ordinance No. 2026-002',
    'Nagbibigay ng alituntunin para sa mabilis at maayos na paglikas ng mga residente sa panahon ng malakas na ulan at baha.',
    'Safety',
    '/uploads/ordinances/ordinance-2026-002.pdf',
    date('Y-m-d', strtotime('-20 days')),
    'Ang Ordinansa No. 2026-002 ay nag-uutos ng listahan ng mga evacuation center, responsibilidad ng barangay at residente, at mga paalala sa pagdadala ng mahahalagang gamit. Layuning mabawasan ang panganib sa kaligtasan ng komunidad.',
    $staff2Id,
    'active'
);

insertOrdinance(
    $pdo,
    'Ordinance No. 2026-003: Barangay Health Outreach',
    'Ordinance No. 2026-003',
    'Nagpapalawak ng libreng health check-up at vaccination outreach para sa lahat ng residente ng Barangay Bayogo.',
    'Health',
    '/uploads/ordinances/ordinance-2026-003.pdf',
    date('Y-m-d', strtotime('-10 days')),
    null,
    $staff1Id,
    'active'
);

insertOrdinance(
    $pdo,
    'Ordinance No. 2026-004: Barangay Plaza Use Policy',
    'Ordinance No. 2026-004',
    'Nagpapataw ng gabay sa paggamit ng Barangay Plaza para sa aktibidad, clean-up, at pag-aalaga upang mapanatiling maayos ang pampublikong lugar.',
    'Government',
    '/uploads/ordinances/ordinance-2026-004.pdf',
    date('Y-m-d', strtotime('-5 days')),
    null,
    $staff1Id,
    'draft'
);

echo "Demo data seeded successfully.\n";
