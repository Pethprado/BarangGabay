<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Announcement;
use App\Models\DocumentFee;
use App\Models\EvacuationCenter;
use App\Models\Event;
use App\Models\Ordinance;

/**
 * BarangayGuide — what the BarangGabay AI assistant knows about the barangay.
 *
 * Two jobs:
 *  1. context()  — a plain-text briefing (how to use the system + the live
 *     announcements, events, ordinances, fees and evacuation centres) that is
 *     handed to Claude so its answers are grounded in real barangay data.
 *  2. answer()   — a built-in guide that answers WITHOUT the AI. It matches the
 *     question to a help topic and searches the live posts. The chat uses it
 *     whenever the Claude API is unavailable (no key, no credits, outage), so
 *     residents always get a useful answer instead of an error.
 */
class BarangayGuide
{
    /** Words too common to be worth searching posts for. */
    private const STOPWORDS = [
        'ang', 'ng', 'mga', 'sa', 'na', 'at', 'ay', 'ko', 'mo', 'ba', 'po', 'opo', 'kung', 'paano', 'saan',
        'ano', 'sino', 'kailan', 'bakit', 'ito', 'iyan', 'yung', 'yan', 'may', 'meron', 'wala', 'para',
        'naman', 'lang', 'din', 'rin', 'pala', 'kasi', 'akin', 'ako', 'ikaw', 'siya', 'kami', 'tayo',
        'the', 'and', 'for', 'what', 'where', 'when', 'how', 'who', 'why', 'can', 'does', 'this', 'that',
        'with', 'about', 'there', 'have', 'from', 'your', 'you', 'are', 'any', 'please', 'pls', 'barangay',
        'unsa', 'asa', 'kanus-a', 'unsaon', 'nako', 'nimo', 'bayogo',
    ];

    /** Resident's locale: 'en', 'fil' or 'msm' (msm answers fall back to Filipino offline). */
    private string $locale;

    /** @param array<string,mixed>|null $user The signed-in user row, for purok-aware answers. */
    public function __construct(private ?array $user = null, ?string $locale = null)
    {
        $locale       = $locale ?? (\function_exists('current_locale') ? current_locale() : 'fil');
        $this->locale = $locale === 'en' ? 'en' : 'fil';
    }

    // ── Help topics ──────────────────────────────────────────────────────

    /**
     * Every help topic the guide can explain. Keywords are matched against
     * the question (English, Filipino and common Bisaya words). Answers name
     * the page path so the chat widget can turn it into a link.
     *
     * @return array<string, array{keywords: list<string>, en: string, fil: string}>
     */
    private function topics(): array
    {
        return [
            'documents' => [
                'keywords' => ['clearance', 'certificate', 'certification', 'sertipiko', 'residency', 'indigency', 'business', 'permit',
                    'dokumento', 'document', 'request', 'kumuha', 'makakuha', 'mag-request', 'cedula', 'requirements', 'requirement', 'kailangan'],
                'en'  => "To request a barangay document:\n1. Open Documents (/documents).\n2. Choose the type (Barangay Clearance, Certificate of Residency, Certificate of Indigency or Business Clearance) and write the purpose.\n3. Submit the request. There is no online payment.\n4. Barangay staff review it and approve it when the document is ready; you get a notification.\n5. Pick it up at the Barangay Hall with a valid ID, and pay any fee there.\nTrack it in My Requests (/documents?tab=requests).",
                'fil' => "Para humiling ng dokumento sa barangay:\n1. Buksan ang Documents (/documents).\n2. Piliin ang uri (Barangay Clearance, Certificate of Residency, Certificate of Indigency o Business Clearance) at isulat ang layunin.\n3. Isumite ang request. Walang online na bayad.\n4. Susuriin at aaprubahan ito ng staff kapag handa na ang dokumento; makakatanggap ka ng abiso.\n5. Kunin ito sa Barangay Hall dala ang valid ID, at doon bayaran ang anumang bayarin.\nSubaybayan ito sa My Requests (/documents?tab=requests).",
            ],
            'payment' => [
                'keywords' => ['gcash', 'bayad', 'magbayad', 'payment', 'pay', 'paid', 'fee', 'fees', 'presyo', 'magkano', 'price', 'cost',
                    'resibo', 'receipt', 'proof', 'paymongo', 'libre', 'free', 'tagpila'],
                'en'  => "Paying for a document:\n- There is no online payment. Document fees are paid in cash at the Barangay Hall when you pick the document up.\n- The fee for each document is shown on the Documents page (/documents) before you submit. Free documents have no fee.",
                'fil' => "Pagbabayad ng dokumento:\n- Walang online na bayad. Ang bayarin sa dokumento ay binabayaran nang cash sa Barangay Hall pagkuha mo nito.\n- Makikita ang bayarin ng bawat dokumento sa Documents page (/documents) bago ka magsumite. Walang bayad ang mga libreng dokumento.",
            ],
            'document_status' => [
                'keywords' => ['status', 'history', 'download', 'i-download', 'claim', 'ready', 'handa', 'pickup', 'kunin', 'approved',
                    'rejected', 'tapos', 'na-approve', 'nasaan', 'tracking', 'request ko'],
                'en'  => "Check your document requests in My Requests (/documents?tab=requests). Each request shows its status (submitted, under review, ready for pickup, completed). When it says Ready for Pickup, bring a valid ID to the Barangay Hall and pay any fee there.",
                'fil' => "Tingnan ang iyong mga request sa My Requests (/documents?tab=requests). Makikita roon ang status (submitted, under review, ready for pickup, completed). Kapag Ready for Pickup na, magdala ng valid ID sa Barangay Hall at doon bayaran ang anumang bayarin.",
            ],
            'evacuation' => [
                'keywords' => ['evacuation', 'evacuate', 'lumikas', 'likas', 'bakwit', 'evacuation center', 'ligtas', 'safety',
                    'check-in', 'checkin', 'baha', 'flood', 'bagyo', 'typhoon', 'storm', 'landslide', 'emergency', 'sakuna', 'disaster'],
                'en'  => "Open Evacuation (/evacuation) to see the evacuation centres for your purok, with address, capacity and contact person. During an emergency post you can tap \"I'm safe\" (safety check-in) so the barangay knows you are okay. Always follow instructions from barangay officials and MDRRMO.",
                'fil' => "Buksan ang Evacuation (/evacuation) para makita ang mga evacuation center para sa iyong purok, kasama ang address, kapasidad at contact person. Sa emergency post, pindutin ang \"Ligtas ako\" (safety check-in) para malaman ng barangay na ligtas ka. Sundin palagi ang utos ng mga opisyal ng barangay at MDRRMO.",
            ],
            'hazard' => [
                'keywords' => ['hazard', 'warning', 'advisory', 'alert', 'signal', 'lindol', 'earthquake', 'tsunami', 'pagasa', 'phivolcs',
                    'ulan', 'rain', 'weather', 'panahon', 'babala'],
                'en'  => "Hazard advisories (typhoons, heavy rain, earthquakes) appear at the top of your Dashboard (/) and as urgent Announcements (/announcements). They come from PAGASA and PHIVOLCS. Turn on notifications so you get them right away, and check Evacuation (/evacuation) for where to go.",
                'fil' => "Ang mga hazard advisory (bagyo, malakas na ulan, lindol) ay nasa itaas ng Dashboard (/) at bilang urgent na Announcements (/announcements). Galing ito sa PAGASA at PHIVOLCS. Buksan ang notifications para agad mong matanggap, at tingnan ang Evacuation (/evacuation) kung saan pupunta.",
            ],
            'announcements' => [
                'keywords' => ['announcement', 'announcements', 'anunsyo', 'anunsiyo', 'balita', 'news', 'update', 'updates', 'bago', 'latest', 'pahibalo'],
                'en'  => "All barangay announcements are in Announcements (/announcements). You can filter by category (health, safety, government…) and urgency, or search. Urgent posts are marked in red.",
                'fil' => "Lahat ng anunsyo ng barangay ay nasa Announcements (/announcements). Maaari kang mag-filter ayon sa kategorya (health, safety, government…) at urgency, o maghanap. Ang urgent na post ay may pulang marka.",
            ],
            'events' => [
                'keywords' => ['event', 'events', 'kaganapan', 'aktibidad', 'activity', 'activities', 'schedule', 'iskedyul', 'meeting',
                    'pulong', 'assembly', 'program', 'programa', 'fiesta', 'pista', 'vaccination', 'bakuna', 'calendar', 'kalendaryo',
                    'hitabo', 'panghitabo', 'kalihokan', 'tigom'],
                'en'  => "Upcoming activities are in Events (/events), sorted by date. Each event shows the date, time, venue and a map. You can add an event to your phone's calendar from its page.",
                'fil' => "Ang mga paparating na aktibidad ay nasa Events (/events), ayon sa petsa. Makikita sa bawat event ang petsa, oras, lugar at mapa. Maaari mo itong idagdag sa calendar ng iyong cellphone mula sa page nito.",
            ],
            'ordinances' => [
                'keywords' => ['ordinance', 'ordinances', 'ordinansa', 'batas', 'law', 'laws', 'policy', 'policies', 'patakaran', 'resolution',
                    'resolusyon', 'bawal', 'multa', 'balaod', 'ipasabut', 'penalty', 'fine', 'curfew', 'summary', 'summarize', 'buod'],
                'en'  => "Barangay laws are in Ordinances (/ordinances). Open one to read the full PDF, then tap \"Summarize with AI\" for a short plain-language explanation. You can search by title, number or category.",
                'fil' => "Ang mga batas ng barangay ay nasa Ordinances (/ordinances). Buksan ang isa para mabasa ang buong PDF, at pindutin ang \"Summarize with AI\" para sa maikling paliwanag sa simpleng salita. Maaari kang maghanap ayon sa pamagat, numero o kategorya.",
            ],
            'account' => [
                'keywords' => ['verify', 'verified', 'verification', 'pending', 'approval', 'approve', 'register', 'registration', 'account',
                    'mag-register', 'magparehistro', 'valid id', 'id', 'resident', 'residente'],
                'en'  => "New accounts start as Pending. Barangay staff check the valid ID you uploaded and then verify your account — you get a notification and email when it's approved. If it takes long, visit the Barangay Hall with your ID.",
                'fil' => "Ang bagong account ay Pending muna. Susuriin ng staff ng barangay ang valid ID na in-upload mo at saka ive-verify ang account — makakatanggap ka ng abiso at email kapag na-approve. Kung matagal, pumunta sa Barangay Hall dala ang iyong ID.",
            ],
            'password' => [
                'keywords' => ['password', 'forgot', 'nakalimutan', 'reset', 'login', 'log in', 'mag-login', 'sign in', 'locked', 'hindi makapasok'],
                'en'  => "Forgot your password? On the login page tap \"Forgot password\" (/forgot-password), enter your email, and follow the reset link we send. After several wrong attempts, login is paused for a few minutes for your safety.",
                'fil' => "Nakalimutan ang password? Sa login page, pindutin ang \"Forgot password\" (/forgot-password), ilagay ang email, at sundin ang reset link na ipapadala namin. Pagkatapos ng ilang maling subok, pansamantalang naka-pause ang login para sa iyong seguridad.",
            ],
            'profile' => [
                'keywords' => ['profile', 'address', 'phone', 'number', 'pangalan', 'name', 'change', 'palitan', 'update', 'edit', 'avatar',
                    'picture', 'litrato', 'purok', 'zone', 'lumipat'],
                'en'  => "Update your details in Profile (/profile). Some details (like name or purok) need staff approval, so the change is sent as a request and you're notified when it's approved.",
                'fil' => "I-update ang iyong detalye sa Profile (/profile). Ang ilang detalye (gaya ng pangalan o purok) ay kailangang aprubahan ng staff, kaya ipinapadala ito bilang request at aabisuhan ka kapag naaprubahan.",
            ],
            'notifications' => [
                'keywords' => ['notification', 'notifications', 'abiso', 'bell', 'sms', 'text', 'txt', 'message', 'mensahe', 'email', 'alert ko'],
                'en'  => "The bell icon shows new notifications; see them all in Notifications (/notifications). Urgent announcements can also be sent by SMS to your registered phone number, so keep it updated in Profile (/profile).",
                'fil' => "Ang bell icon ay nagpapakita ng bagong abiso; makikita lahat sa Notifications (/notifications). Ang urgent na anunsyo ay maaari ring ipadala sa SMS sa iyong rehistradong numero, kaya panatilihin itong updated sa Profile (/profile).",
            ],
            'language' => [
                'keywords' => ['language', 'wika', 'lengguwahe', 'english', 'tagalog', 'filipino', 'manobo', 'bisaya', 'cebuano', 'translate',
                    'isalin', 'voice', 'boses', 'listen', 'pakinggan', 'basahin', 'read aloud', 'dictionary', 'diksyunaryo'],
                'en'  => "Switch the language with the EN / FIL / MN buttons at the top. Posts can be read aloud in your language with the voice player on each post. The Manobo–Bisaya–Filipino word list is in Dictionary (/dictionary).",
                'fil' => "Palitan ang wika gamit ang EN / FIL / MN na button sa itaas. Maaaring pakinggan ang mga post sa iyong wika gamit ang voice player sa bawat post. Ang listahan ng salitang Manobo–Bisaya–Filipino ay nasa Dictionary (/dictionary).",
            ],
            'feedback' => [
                'keywords' => ['feedback', 'complaint', 'reklamo', 'suggestion', 'mungkahi', 'suhestiyon', 'concern', 'report', 'problem',
                    'problema', 'sumbong', 'reklamo ko'],
                'en'  => "Send a concern, complaint or suggestion through Feedback (/feedback). Staff can reply, and you'll see their answer on the same page.",
                'fil' => "Magpadala ng concern, reklamo o mungkahi sa Feedback (/feedback). Maaaring sumagot ang staff, at makikita mo ang sagot sa parehong page.",
            ],
            'bookmarks' => [
                'keywords' => ['bookmark', 'bookmarks', 'save', 'saved', 'i-save', 'favorite', 'paborito'],
                'en'  => "Tap the bookmark icon on any announcement, event or ordinance to save it. Find saved items in My Bookmarks (/bookmarks).",
                'fil' => "Pindutin ang bookmark icon sa anumang anunsyo, event o ordinansa para i-save. Makikita ang mga na-save sa My Bookmarks (/bookmarks).",
            ],
            'contact' => [
                'keywords' => ['contact', 'hall', 'office', 'opisina', 'captain', 'kapitan', 'kagawad', 'official', 'opisyal', 'tawag', 'call',
                    'hotline', 'oras', 'hours', 'open', 'bukas', 'address ng barangay'],
                'en'  => "For anything not covered here, visit the Barangay Bayogo Hall (Madrid, Surigao del Sur) during office hours, or send a message through Feedback (/feedback). For emergencies, contact the evacuation centre contacts listed in Evacuation (/evacuation).",
                'fil' => "Para sa mga bagay na wala rito, pumunta sa Barangay Hall ng Bayogo (Madrid, Surigao del Sur) sa oras ng opisina, o magpadala ng mensahe sa Feedback (/feedback). Sa emergency, tawagan ang mga contact sa Evacuation (/evacuation).",
            ],
            'help' => [
                'keywords' => ['help', 'tulong', 'tabang', 'hi', 'hello', 'kumusta', 'kamusta', 'musta', 'good morning', 'magandang',
                    'ano kaya mo', 'what can you do', 'guide', 'gabay', 'paano gamitin', 'how to use'],
                'en'  => "Hi! I'm your BarangGabay guide. I can help you with:\n- Requesting documents for pickup at the Barangay Hall\n- Latest announcements and upcoming events\n- Barangay ordinances explained simply\n- Evacuation centres and hazard advisories\n- Your account, profile, password and language\nJust ask, for example: \"How do I get a barangay clearance?\"",
                'fil' => "Kumusta! Ako ang iyong BarangGabay guide. Matutulungan kita sa:\n- Paghiling ng dokumento na kukunin sa Barangay Hall\n- Pinakabagong anunsyo at paparating na event\n- Mga ordinansa ng barangay sa simpleng paliwanag\n- Evacuation center at hazard advisory\n- Iyong account, profile, password at wika\nMagtanong lang, halimbawa: \"Paano kumuha ng barangay clearance?\"",
            ],
        ];
    }

    // ── Context for Claude ───────────────────────────────────────────────

    /**
     * A plain-text briefing for the AI: how the system works plus live data.
     * Every section is guarded so one failing query never breaks the chat.
     */
    public function context(): string
    {
        $parts = [];

        $howTo = [];
        foreach ($this->topics() as $topic) {
            $howTo[] = '- ' . str_replace("\n", ' ', $topic['en']);
        }
        $parts[] = "HOW TO USE BARANGGABAY (page paths in brackets):\n" . implode("\n", $howTo);

        if ($this->user) {
            $parts[] = sprintf(
                "THE RESIDENT ASKING: name %s; purok %s; account status %s.",
                (string) ($this->user['full_name'] ?? 'unknown'),
                (string) ($this->user['zone'] ?? 'not set'),
                (string) ($this->user['status'] ?? 'unknown')
            );
        }

        $parts[] = $this->section('LATEST ANNOUNCEMENTS', function (): array {
            return array_map(fn(array $a) => sprintf(
                '- [%s, %s, %s] %s — %s (/announcements/%s)',
                $this->date($a['published_at'] ?? $a['created_at'] ?? null),
                $a['category'] ?? 'general',
                $a['urgency'] ?? 'normal',
                $a['title'],
                $this->plain((string) $a['body'], 600),
                $a['slug']
            ), Announcement::getPublished(8));
        });

        $parts[] = $this->section('UPCOMING EVENTS', function (): array {
            return array_map(fn(array $ev) => sprintf(
                '- %s — %s%s; venue: %s; status: %s. %s (/events/%s)',
                $ev['title'],
                $this->date($ev['event_date'], true),
                !empty($ev['end_date']) ? ' to ' . $this->date($ev['end_date'], true) : '',
                $ev['venue'] ?: 'to be announced',
                $ev['status'],
                $this->plain((string) $ev['description'], 300),
                $ev['slug']
            ), Event::upcomingFrom(8));
        });

        $parts[] = $this->section('ORDINANCES', function (): array {
            return array_map(fn(array $o) => sprintf(
                '- %s: %s [%s, %s%s]. %s (/ordinances/%d)',
                $o['ordinance_no'],
                $o['title'],
                $o['category'] ?: 'uncategorised',
                $o['status'],
                !empty($o['enacted_date']) ? ', enacted ' . $o['enacted_date'] : '',
                $this->plain((string) ($o['ai_summary'] ?: $o['description'] ?? ''), 400),
                $o['id']
            ), \array_slice(Ordinance::searchFiltered(), 0, 15));
        });

        $parts[] = $this->section('DOCUMENT FEES', function (): array {
            $rows = [];
            foreach (DocumentFee::getAll() as $fee) {
                if (\is_array($fee) && ($fee['is_active'] ?? true)) {
                    $rows[] = '- ' . $fee['label'] . ': ' . ($fee['is_free'] ? 'free' : 'PHP ' . number_format((float) $fee['amount'], 2))
                        . (!empty($fee['requirements']) ? '; requirements: ' . $this->plain($fee['requirements'], 200) : '');
                }
            }
            return $rows;
        });

        $parts[] = $this->section('EVACUATION CENTRES FOR THIS RESIDENT', fn(): array => $this->centreLines());

        return implode("\n\n", array_filter($parts));
    }

    // ── Offline answer ───────────────────────────────────────────────────

    /**
     * Answer a question without the AI: best-matching help topic(s), live data
     * for that topic, and any posts that mention the question's key words.
     */
    public function answer(string $question): string
    {
        $q      = $this->normalise($question);
        $scores = [];
        foreach ($this->topics() as $id => $topic) {
            $score = 0;
            foreach ($topic['keywords'] as $kw) {
                if (preg_match('/(^|\s)' . preg_quote($kw, '/') . '(\s|$)/u', $q)) {
                    $score += str_contains($kw, ' ') ? 3 : 2;
                } elseif (mb_strlen($kw) >= 5 && str_contains($q, $kw)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$id] = $score;
            }
        }
        arsort($scores);

        // A greeting alone gets the menu; a greeting plus a real question
        // gets the real answer.
        if (\count($scores) > 1) {
            unset($scores['help']);
        }

        $topics = $this->topics();
        $out    = [];
        $best   = array_slice(array_keys($scores), 0, 2);
        foreach ($best as $i => $id) {
            // The second topic only when it scores close to the first.
            if ($i === 1 && $scores[$id] < $scores[$best[0]] - 1) {
                break;
            }
            $out[] = $topics[$id][$this->locale];
            $extra = $this->liveFor($id);
            if ($extra !== '') {
                $out[] = $extra;
            }
        }

        $posts = $this->searchPosts($q, $best);
        if ($posts !== '') {
            $out[] = $posts;
        }

        if ($out === []) {
            return $this->t(
                "I'm not sure about that yet. Try asking about documents, payments, announcements, events, ordinances, evacuation, or your account. For other concerns, please visit the Barangay Hall or send a message through Feedback (/feedback).",
                "Pasensya na, hindi ko pa alam ang sagot diyan. Subukang magtanong tungkol sa dokumento, bayad, anunsyo, event, ordinansa, evacuation, o iyong account. Para sa ibang concern, pumunta sa Barangay Hall o magpadala ng mensahe sa Feedback (/feedback)."
            );
        }

        return implode("\n\n", $out);
    }

    /** Live data that makes a topic answer specific (fees, events, centres…). */
    private function liveFor(string $topic): string
    {
        try {
            switch ($topic) {
                case 'documents':
                case 'payment':
                    $lines = [];
                    foreach (DocumentFee::getAll() as $fee) {
                        if (\is_array($fee) && ($fee['is_active'] ?? true) && $fee['document_type'] !== 'other') {
                            $lines[] = '- ' . $fee['label'] . ': ' . ($fee['is_free']
                                ? $this->t('free', 'libre')
                                : '₱' . number_format((float) $fee['amount'], 2));
                        }
                    }
                    return $lines ? $this->t('Current fees:', 'Kasalukuyang bayad:') . "\n" . implode("\n", $lines) : '';

                case 'events':
                    $lines = array_map(fn(array $ev) => '- ' . $this->localised($ev, 'title') . ' — '
                        . $this->date($ev['event_date'], true) . ($ev['venue'] ? ', ' . $ev['venue'] : '')
                        . ' (/events/' . $ev['slug'] . ')', Event::upcomingFrom(3));
                    return $lines
                        ? $this->t('Coming up:', 'Paparating:') . "\n" . implode("\n", $lines)
                        : $this->t('There are no upcoming events posted right now.', 'Wala pang nakapost na paparating na event ngayon.');

                case 'announcements':
                case 'hazard':
                    $rows  = $topic === 'hazard' ? Announcement::activeUrgent(14, 3) : Announcement::getPublished(3);
                    $lines = array_map(fn(array $a) => '- ' . $this->localised($a, 'title')
                        . ' (/announcements/' . $a['slug'] . ')', $rows);
                    if (!$lines) {
                        return $topic === 'hazard'
                            ? $this->t('There is no active hazard advisory right now.', 'Walang aktibong hazard advisory ngayon.')
                            : '';
                    }
                    return $this->t('Latest:', 'Pinakabago:') . "\n" . implode("\n", $lines);

                case 'ordinances':
                    $lines = array_map(fn(array $o) => '- ' . $o['ordinance_no'] . ': ' . $this->localised($o, 'title')
                        . ' (/ordinances/' . $o['id'] . ')', \array_slice(Ordinance::searchFiltered(), 0, 4));
                    return $lines ? $this->t('Some ordinances:', 'Ilang ordinansa:') . "\n" . implode("\n", $lines) : '';

                case 'evacuation':
                    $lines = $this->centreLines(4);
                    return $lines ? $this->t('Evacuation centres for you:', 'Mga evacuation center para sa iyo:') . "\n" . implode("\n", $lines) : '';
            }
        } catch (\Throwable $e) {
            error_log('[BarangayGuide::liveFor] ' . $e->getMessage());
        }
        return '';
    }

    /** Posts whose title or body mention the question's key words. */
    private function searchPosts(string $q, array $matchedTopics): string
    {
        $words = array_values(array_filter(
            explode(' ', $q),
            fn(string $w) => mb_strlen($w) >= 4 && !\in_array($w, self::STOPWORDS, true)
        ));
        if ($words === []) {
            return '';
        }
        // Words that only named a help topic ("clearance", "events") are not
        // worth a post search — the topic answer already covers them.
        $topicWords = [];
        foreach ($matchedTopics as $id) {
            $topicWords = array_merge($topicWords, $this->topics()[$id]['keywords']);
        }
        $words = array_slice(array_values(array_diff($words, $topicWords)), 0, 3);
        if ($words === []) {
            return '';
        }

        $found = [];
        try {
            foreach ($words as $w) {
                $like = '%' . $w . '%';
                foreach (Announcement::search($w, 3) as $a) {
                    $found['a' . $a['id']] = '- ' . $this->localised($a, 'title') . ' (/announcements/' . $a['slug'] . ')';
                }
                $stmt = db()->prepare(
                    "SELECT * FROM events WHERE status != 'cancelled' AND (title LIKE ? OR description LIKE ? OR venue LIKE ?)
                      ORDER BY event_date DESC LIMIT 3"
                );
                $stmt->execute([$like, $like, $like]);
                foreach ($stmt->fetchAll() as $ev) {
                    $found['e' . $ev['id']] = '- ' . $this->localised($ev, 'title') . ' (/events/' . $ev['slug'] . ')';
                }
                foreach (\array_slice(Ordinance::searchFiltered($w), 0, 3) as $o) {
                    $found['o' . $o['id']] = '- ' . $o['ordinance_no'] . ': ' . $this->localised($o, 'title') . ' (/ordinances/' . $o['id'] . ')';
                }
                if (\count($found) >= 5) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            error_log('[BarangayGuide::searchPosts] ' . $e->getMessage());
        }

        if ($found === []) {
            return '';
        }
        return $this->t('Related posts:', 'Kaugnay na post:') . "\n" . implode("\n", \array_slice($found, 0, 5));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return list<string> */
    private function centreLines(int $limit = 6): array
    {
        $centres = EvacuationCenter::forPurok($this->user['zone'] ?? null);
        return array_map(fn(array $c) => sprintf(
            '- %s%s%s%s',
            $c['name'],
            $c['purok'] ? ' (' . $c['purok'] . ')' : '',
            $c['address'] ? ', ' . $c['address'] : '',
            $c['contact_phone'] ? ' — ' . ($c['contact_person'] ? $c['contact_person'] . ' ' : '') . $c['contact_phone'] : ''
        ), \array_slice($centres, 0, $limit));
    }

    /** Run one context query; a failure drops just that section. */
    private function section(string $heading, callable $rows): string
    {
        try {
            $lines = $rows();
            return $lines ? $heading . ":\n" . implode("\n", $lines) : $heading . ': none posted right now.';
        } catch (\Throwable $e) {
            error_log('[BarangayGuide::context] ' . $heading . ': ' . $e->getMessage());
            return '';
        }
    }

    /** Title/body in the reader's language when a translation exists. */
    private function localised(array $row, string $field): string
    {
        if (\function_exists('localised_content')) {
            $text = trim((string) (localised_content($row, $field)['text'] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }
        return (string) ($row[$field] ?? '');
    }

    /** Rich-text HTML → one-line plain text, cut to $max characters. */
    private function plain(string $html, int $max): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', $html)), ENT_QUOTES, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max)) . '…' : $text;
    }

    private function date(?string $value, bool $withTime = false): string
    {
        if (!$value || ($ts = strtotime($value)) === false) {
            return 'date not set';
        }
        return date($withTime ? 'D, M j, Y g:i A' : 'M j, Y', $ts);
    }

    /** Lower-case, punctuation stripped, single-spaced. */
    private function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function t(string $en, string $fil): string
    {
        return $this->locale === 'en' ? $en : $fil;
    }
}
