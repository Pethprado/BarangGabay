# CLAUDE.md — BarangGabay
## AI-Based Smart Web Application for Barangay Announcements, Events, and Simplified Policy Communication

> **Project context:** This is a thesis system for Barangay Bayogo, Municipality of Madrid, Surigao del Sur. It is a PHP/MySQL web application with an integrated AI layer (Anthropic Claude API) for ordinance summarization and a community Q&A assistant. Build clean, well-commented, production-quality code suitable for academic review and real community deployment.

---

## Table of contents

1. [Project overview](#1-project-overview)
2. [Tech stack](#2-tech-stack)
3. [Folder structure](#3-folder-structure)
4. [Database schema](#4-database-schema)
5. [Routing & MVC conventions](#5-routing--mvc-conventions)
6. [Authentication & roles](#6-authentication--roles)
7. [Module specifications](#7-module-specifications)
8. [AI service layer](#8-ai-service-layer)
9. [Frontend conventions](#9-frontend-conventions)
10. [Security requirements](#10-security-requirements)
11. [Environment & configuration](#11-environment--configuration)
12. [Composer dependencies](#12-composer-dependencies)
13. [Build & run instructions](#13-build--run-instructions)
14. [Coding standards](#14-coding-standards)
15. [Feature checklist](#15-feature-checklist)

---

## 1. Project overview

**System name:** BarangGabay
**Tagline:** Connecting Residents. Simplifying Governance.
**Scope:** Barangay-level web application serving verified residents of Barangay Bayogo, Municipality of Madrid, Surigao del Sur.

### Core purposes
- Centralized platform for barangay announcements and event notifications
- Policy and ordinance repository with AI-powered plain-language summaries
- Resident registration with staff-verified account approval
- Administrative dashboard for barangay staff to manage and broadcast content
- AI community Q&A assistant for resident queries about local policies

### User roles
| Role | Description |
|------|-------------|
| `resident` | Verified community member. Can browse, receive notifications, use AI summarizer |
| `staff` | Barangay staff. Can create and manage announcements, events, ordinances |
| `admin` | Barangay admin. Full content control + resident verification + analytics |
| `superadmin` | System owner. Manages staff accounts, system configuration |

---

## 2. Tech stack

### Backend
- **PHP 8.2** — server-side logic, MVC pattern
- **MySQL 8.0** — relational database via PDO with prepared statements
- **Apache** (XAMPP for local dev, cPanel for deployment)
- **Composer** — dependency management

### Frontend
- **HTML5 + CSS3** — semantic markup, custom properties for theming
- **Bootstrap 5.3** — responsive grid, components
- **Alpine.js 3** — lightweight reactivity (modals, tabs, live search, toggles)
- **Chart.js 4** — admin analytics charts
- **Quill.js** — rich text editor for announcement body
- **PDF.js** — in-browser PDF viewer for ordinances

### AI & external services
- **Anthropic Claude API** (`claude-sonnet-4-20250514`) — ordinance summarization + Q&A
- **Guzzle HTTP** — PHP HTTP client for API calls
- **PHPMailer** — transactional email notifications
- **Cloudinary** (optional) — media file storage CDN
- **Google Maps JavaScript API** — event venue map pins

### Security
- **firebase/php-jwt** — JWT token generation and validation
- **ezyang/htmlpurifier** — sanitize rich-text HTML input
- **vlucas/phpdotenv** — environment variable management

---

## 3. Folder structure

```
baranggabay/
│
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── AnnouncementController.php
│   │   ├── EventController.php
│   │   ├── OrdinanceController.php
│   │   ├── NotificationController.php
│   │   ├── ResidentController.php
│   │   ├── AdminController.php
│   │   └── AIController.php
│   │
│   ├── models/
│   │   ├── User.php
│   │   ├── Announcement.php
│   │   ├── Event.php
│   │   ├── Ordinance.php
│   │   ├── Notification.php
│   │   ├── MediaFile.php
│   │   └── AuditLog.php
│   │
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── main.php           (resident layout: nav, footer)
│   │   │   └── admin.php          (admin layout: sidebar, topbar)
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   └── register.php
│   │   ├── resident/
│   │   │   ├── home.php
│   │   │   ├── announcements.php
│   │   │   ├── announcement-detail.php
│   │   │   ├── events.php
│   │   │   ├── event-detail.php
│   │   │   ├── ordinances.php
│   │   │   ├── ordinance-detail.php
│   │   │   ├── profile.php
│   │   │   └── notifications.php
│   │   ├── admin/
│   │   │   ├── dashboard.php
│   │   │   ├── announcements/
│   │   │   │   ├── index.php
│   │   │   │   ├── create.php
│   │   │   │   └── edit.php
│   │   │   ├── events/
│   │   │   │   ├── index.php
│   │   │   │   ├── create.php
│   │   │   │   └── edit.php
│   │   │   ├── ordinances/
│   │   │   │   ├── index.php
│   │   │   │   └── upload.php
│   │   │   ├── residents/
│   │   │   │   ├── index.php
│   │   │   │   └── verify.php
│   │   │   └── reports/
│   │   │       └── index.php
│   │   └── shared/
│   │       ├── _toast.php
│   │       ├── _pagination.php
│   │       ├── _ai-chat-widget.php
│   │       └── _loading-spinner.php
│   │
│   ├── services/
│   │   ├── AIService.php          (Anthropic API wrapper)
│   │   ├── MailService.php        (PHPMailer wrapper)
│   │   ├── FileService.php        (upload, validate, store)
   │   └── NotificationService.php
│   │
│   └── middleware/
│       ├── AuthMiddleware.php     (check JWT / session)
│       ├── RoleMiddleware.php     (check role access)
│       └── RateLimitMiddleware.php
│
├── config/
│   ├── database.php               (PDO connection)
│   ├── app.php                    (app name, timezone, base URL)
│   └── ai.php                     (Claude API key, model, max_tokens)
│
├── public/
│   ├── index.php                  (front controller — all requests pass through here)
│   ├── assets/
│   │   ├── css/
│   │   │   ├── main.css
│   │   │   └── admin.css
│   │   ├── js/
│   │   │   ├── main.js
│   │   │   ├── admin.js
│   │   │   └── ai-widget.js
│   │   └── img/
│   │       ├── logo.png
│   │       └── barangay-seal.png
│   └── uploads/                   (local fallback if no CDN)
│
├── routes/
│   ├── web.php                    (all named routes → controller@method)
   └── api.php                    (AJAX/API-only routes)
│
├── database/
│   ├── schema.sql                 (full CREATE TABLE statements)
│   └── seeders/
│       └── DemoDataSeeder.php
│
├── .env.example
├── .env                           (never commit — in .gitignore)
├── .htaccess                      (mod_rewrite: all traffic → public/index.php)
├── composer.json
└── README.md
```

---

## 4. Database schema

Run `database/schema.sql` to initialize. All tables use `utf8mb4_unicode_ci`. Use `UNSIGNED` for all foreign key IDs.

```sql
-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150)    NOT NULL,
    email           VARCHAR(191)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    phone           VARCHAR(20)     NULL,
    address         TEXT            NULL,
    zone            VARCHAR(50)     NULL DEFAULT NULL,            -- purok/sitio; see barangay_subdivisions()
    role            ENUM('resident','staff','admin','superadmin') NOT NULL DEFAULT 'resident',
    status          ENUM('pending','verified','suspended')        NOT NULL DEFAULT 'pending',
    id_photo_url    VARCHAR(500)    NULL,            -- uploaded valid ID
    avatar_url      VARCHAR(500)    NULL,
    email_verified  TINYINT(1)      NOT NULL DEFAULT 0,
    last_login_at   DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role   (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- ANNOUNCEMENTS
-- ============================================================
CREATE TABLE announcements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255)    NOT NULL,
    slug            VARCHAR(255)    NOT NULL UNIQUE,
    body            LONGTEXT        NOT NULL,        -- sanitized HTML from Quill
    category        ENUM('general','health','safety','government','infrastructure','social') NOT NULL DEFAULT 'general',
    urgency         ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',
    author_id       INT UNSIGNED    NOT NULL,
    cover_image_url VARCHAR(500)    NULL,
    status          ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at    DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_status_published (status, published_at),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- ============================================================
-- EVENTS
-- ============================================================
CREATE TABLE events (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255)    NOT NULL,
    slug            VARCHAR(255)    NOT NULL UNIQUE,
    description     LONGTEXT        NOT NULL,
    venue           VARCHAR(255)    NULL,
    latitude        DECIMAL(10,7)   NULL,
    longitude       DECIMAL(10,7)   NULL,
    event_date      DATETIME        NOT NULL,
    end_date        DATETIME        NULL,
    cover_image_url VARCHAR(500)    NULL,
    created_by      INT UNSIGNED    NOT NULL,
    status          ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB;

-- ============================================================
-- ORDINANCES (policies)
-- ============================================================
CREATE TABLE ordinances (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255)    NOT NULL,
    ordinance_no    VARCHAR(100)    NOT NULL UNIQUE,  -- e.g. "Ordinance No. 2024-001"
    description     TEXT            NULL,
    category        VARCHAR(100)    NULL,             -- e.g. "Environmental", "Public Order"
    file_url        VARCHAR(500)    NOT NULL,          -- PDF path or CDN URL
    enacted_date    DATE            NULL,
    ai_summary      LONGTEXT        NULL,              -- cached Claude summary
    ai_summary_at   DATETIME        NULL,              -- when summary was generated
    uploaded_by     INT UNSIGNED    NOT NULL,
    status          ENUM('active','repealed','draft') NOT NULL DEFAULT 'active',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    title           VARCHAR(255)    NOT NULL,
    message         TEXT            NOT NULL,
    type            ENUM('announcement','event','ordinance','system','verification') NOT NULL DEFAULT 'system',
    related_id      INT UNSIGNED    NULL,             -- FK to related content
    related_type    VARCHAR(50)     NULL,             -- 'announcement' | 'event' | 'ordinance'
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ============================================================
-- MEDIA FILES
-- ============================================================
CREATE TABLE media_files (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    related_type    VARCHAR(50)     NOT NULL,         -- 'announcement' | 'event' | 'ordinance' | 'user'
    related_id      INT UNSIGNED    NOT NULL,
    file_url        VARCHAR(500)    NOT NULL,
    file_type       ENUM('image','pdf','document','other') NOT NULL DEFAULT 'image',
    file_size_kb    INT UNSIGNED    NULL,
    original_name   VARCHAR(255)    NULL,
    uploaded_by     INT UNSIGNED    NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_related (related_type, related_id)
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,             -- NULL for guest actions
    action          VARCHAR(100)    NOT NULL,         -- e.g. 'announcement.created'
    description     TEXT            NULL,
    ip_address      VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- AI CHAT LOGS (optional — store Q&A history)
-- ============================================================
CREATE TABLE ai_chat_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,
    question        TEXT            NOT NULL,
    answer          LONGTEXT        NOT NULL,
    context_used    TEXT            NULL,             -- which posts were injected
    tokens_used     INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
```

---

## 5. Routing & MVC conventions

All HTTP requests are routed through `public/index.php` via `.htaccess` mod_rewrite.

### .htaccess (root)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]
```

### Route definitions (`routes/web.php`)

```php
// Format: ['METHOD', '/path', 'Controller@method', ['middleware']]

// --- Public ---
['GET',  '/',                          'ResidentController@home',              []],
['GET',  '/login',                     'AuthController@showLogin',             []],
['POST', '/login',                     'AuthController@login',                 []],
['GET',  '/register',                  'AuthController@showRegister',          []],
['POST', '/register',                  'AuthController@register',              []],
['GET',  '/logout',                    'AuthController@logout',                ['auth']],

// --- Resident (verified only) ---
['GET',  '/announcements',             'AnnouncementController@index',         ['auth', 'verified']],
['GET',  '/announcements/{slug}',      'AnnouncementController@show',          ['auth', 'verified']],
['GET',  '/events',                    'EventController@index',                ['auth', 'verified']],
['GET',  '/events/{slug}',             'EventController@show',                 ['auth', 'verified']],
['GET',  '/ordinances',                'OrdinanceController@index',            ['auth', 'verified']],
['GET',  '/ordinances/{id}',           'OrdinanceController@show',             ['auth', 'verified']],
['GET',  '/notifications',             'NotificationController@index',         ['auth', 'verified']],
['GET',  '/profile',                   'ResidentController@profile',           ['auth', 'verified']],
['POST', '/profile',                   'ResidentController@updateProfile',     ['auth', 'verified']],

// --- Admin ---
['GET',  '/admin',                     'AdminController@dashboard',            ['auth', 'role:admin,staff']],
['GET',  '/admin/announcements',       'AnnouncementController@adminIndex',    ['auth', 'role:admin,staff']],
['GET',  '/admin/announcements/create','AnnouncementController@create',        ['auth', 'role:admin,staff']],
['POST', '/admin/announcements',       'AnnouncementController@store',         ['auth', 'role:admin,staff']],
['GET',  '/admin/announcements/{id}/edit','AnnouncementController@edit',       ['auth', 'role:admin,staff']],
['POST', '/admin/announcements/{id}',  'AnnouncementController@update',        ['auth', 'role:admin,staff']],
['POST', '/admin/announcements/{id}/delete','AnnouncementController@destroy',  ['auth', 'role:admin']],
['GET',  '/admin/events',              'EventController@adminIndex',           ['auth', 'role:admin,staff']],
['GET',  '/admin/events/create',       'EventController@create',               ['auth', 'role:admin,staff']],
['POST', '/admin/events',              'EventController@store',                ['auth', 'role:admin,staff']],
['GET',  '/admin/ordinances',          'OrdinanceController@adminIndex',       ['auth', 'role:admin,staff']],
['POST', '/admin/ordinances',          'OrdinanceController@store',            ['auth', 'role:admin,staff']],
['GET',  '/admin/residents',           'ResidentController@adminIndex',        ['auth', 'role:admin']],
['POST', '/admin/residents/{id}/verify','ResidentController@verify',           ['auth', 'role:admin']],
['POST', '/admin/residents/{id}/suspend','ResidentController@suspend',         ['auth', 'role:admin']],
['GET',  '/admin/reports',             'AdminController@reports',              ['auth', 'role:admin']],

// --- API (AJAX) ---
['POST', '/api/ai/summarize',          'AIController@summarize',              ['auth', 'verified', 'rate-limit']],
['POST', '/api/ai/chat',               'AIController@chat',                   ['auth', 'verified', 'rate-limit']],
['GET',  '/api/notifications/unread',  'NotificationController@unreadCount',  ['auth']],
['POST', '/api/notifications/mark-read','NotificationController@markRead',    ['auth']],
```

---

## 6. Authentication & roles

### Session-based auth (primary)
Use PHP sessions (`$_SESSION`) for web requests. On login, store `user_id`, `role`, `status` in session.

### JWT (for AJAX API endpoints)
Generate a JWT on login and store it in `localStorage`. AJAX requests send it as `Authorization: Bearer <token>`.

### Middleware pattern

```php
// app/middleware/AuthMiddleware.php
class AuthMiddleware {
    public function handle(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
}

// app/middleware/RoleMiddleware.php
class RoleMiddleware {
    public function handle(array $allowedRoles): void {
        $userRole = $_SESSION['role'] ?? 'guest';
        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            require 'app/views/errors/403.php';
            exit;
        }
    }
}
```

### Verified-only access
Residents with `status = 'pending'` are redirected to a "Waiting for verification" page. They cannot access announcements, events, or AI features until approved by admin.

---

## 7. Module specifications

### 7.1 Announcement module

**Admin — create announcement**
- Fields: title, body (Quill rich text), category (dropdown), urgency (radio), cover image (upload), publish date (datetime picker), status (draft/publish now)
- On save: generate slug from title, sanitize body with HTMLPurifier, store cover image via FileService, dispatch notifications to all verified residents via NotificationService
- Validation: title required (max 255), body required (min 50 chars), category required

**Resident — view announcements**
- Paginated list (10 per page), filter by category and urgency
- Search bar (live search via Alpine.js + AJAX against title/body)
- Urgency badges: normal = gray, important = yellow, urgent = red
- Detail page shows full rich-text body, cover image, posted date, author name

### 7.2 Events module

**Admin — create event**
- Fields: title, description (Quill), venue (text), latitude/longitude (Google Maps picker widget), event date, end date, cover image, status
- Map widget: resident clicks on the embedded Google Maps to set pin; lat/lng auto-fill hidden inputs
- On save: dispatch event notifications to verified residents

**Resident — view events**
- Upcoming events sorted by date ascending
- Event detail page shows embedded Google Maps with the venue pin
- Status badge: upcoming (blue), ongoing (green), completed (gray), cancelled (red)

### 7.3 Ordinance / Policy module

**Admin — upload ordinance**
- Fields: title, ordinance number, description, category, enacted date, PDF file upload, status
- File validation: PDF only, max 10MB
- PDF stored in `public/uploads/ordinances/` or Cloudinary
- After upload, `ai_summary` column is NULL — summary is generated on-demand

**Resident — view ordinances**
- Searchable, filterable list by category and status
- PDF.js viewer embedded in the detail page (no download required to read)
- "Summarize with AI" button — calls `/api/ai/summarize` with the ordinance ID
- If `ai_summary` already exists in DB, return cached version instantly
- Display summary in a styled callout box below the PDF viewer

### 7.4 Resident registration & verification

**Registration flow:**
1. Resident fills form: full name, email, password, phone, address, zone, upload valid ID (image/PDF)
2. Account created with `status = 'pending'`, `email_verified = 0`
3. Verification email sent via PHPMailer with a signed link
4. Resident clicks link → `email_verified = 1`, but `status` stays `pending`
5. Admin reviews uploaded ID in the `/admin/residents` panel
6. Admin clicks "Verify" → `status = 'verified'` → resident receives notification + email
7. Resident can now access full system

### 7.5 Notification system

**NotificationService** creates a record in `notifications` table for the target user(s).

**Broadcast types:**
- `all` — insert notification for every user with `status = 'verified'`
- `zone` — filter by `users.zone`
- `single` — target one user (e.g., verification approved)

**Frontend unread badge:**
Alpine.js polls `/api/notifications/unread` every 60 seconds and updates the bell icon badge count.

### 7.6 Admin analytics dashboard

Charts rendered with Chart.js. Data fetched via AJAX on page load.

| Chart | Type | Data |
|-------|------|------|
| Resident registrations over time | Line | `COUNT(id)` grouped by `DATE(created_at)` |
| Announcements by category | Doughnut | `COUNT(id)` grouped by `category` |
| Events this month | Bar | Events per week |
| Ordinances by status | Pie | active / repealed / draft counts |
| Verification queue | Stat card | `COUNT` where `status = 'pending'` |

---

## 8. AI service layer

### AIService.php

```php
<?php
// app/services/AIService.php

namespace App\Services;

use GuzzleHttp\Client;

class AIService
{
    private Client $client;
    private string $model    = 'claude-sonnet-4-20250514';
    private int    $maxTokens = 1024;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers'  => [
                'x-api-key'         => $_ENV['ANTHROPIC_API_KEY'],
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Summarize an ordinance text into plain language.
     * Returns cached summary if already stored in DB.
     */
    public function summarizeOrdinance(int $ordinanceId, string $ordinanceText): string
    {
        $systemPrompt = <<<PROMPT
You are BarangGabay, a friendly community assistant for Barangay Bayogo, Madrid, Surigao del Sur.
Your job is to explain local government ordinances and policies in simple, clear language 
that any resident can understand — even without a legal background.
Always respond in clear Filipino or simple English (mix is fine, Taglish is acceptable).
Keep summaries under 250 words. Use bullet points for key points.
Do not add legal advice. End with "Para sa karagdagang impormasyon, makipag-ugnayan sa Barangay Hall."
PROMPT;

        $userPrompt = "Ipaliwanag ang sumusunod na ordinansa sa simpleng salita para sa mga residente ng barangay:\n\n{$ordinanceText}";

        $response = $this->client->post('/v1/messages', [
            'json' => [
                'model'      => $this->model,
                'max_tokens' => $this->maxTokens,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
        ]);

        $data    = json_decode($response->getBody(), true);
        $summary = $data['content'][0]['text'] ?? 'Hindi ma-generate ang buod. Subukan muli mamaya.';

        // Cache summary in DB
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE ordinances SET ai_summary = ?, ai_summary_at = NOW() WHERE id = ?");
        $stmt->execute([$summary, $ordinanceId]);

        return $summary;
    }

    /**
     * Community Q&A — answer resident questions using recent announcements as context.
     */
    public function chat(string $question, array $recentPosts): string
    {
        $context = implode("\n\n", array_map(function ($post) {
            return "### {$post['title']}\n{$post['body']}";
        }, array_slice($recentPosts, 0, 5)));

        $systemPrompt = <<<PROMPT
You are BarangGabay, a helpful assistant for residents of Barangay Bayogo, Madrid.
Answer questions based only on the barangay information provided below.
If the answer is not in the provided context, say: 
"Pasensya na, wala akong impormasyon tungkol diyan. Mangyaring makipag-ugnayan sa Barangay Hall."
Be friendly, concise, and use simple Filipino or Taglish.
Keep answers under 150 words.

BARANGAY CONTEXT:
{$context}
PROMPT;

        $response = $this->client->post('/v1/messages', [
            'json' => [
                'model'      => $this->model,
                'max_tokens' => 512,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $question],
                ],
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['content'][0]['text'] ?? 'Hindi ko masagot ang iyong tanong sa ngayon.';
    }
}
```

### AIController.php

```php
<?php
// app/controllers/AIController.php

namespace App\Controllers;

use App\Services\AIService;
use App\Models\Ordinance;
use App\Models\Announcement;

class AIController
{
    private AIService $ai;

    public function __construct()
    {
        $this->ai = new AIService();
    }

    /** POST /api/ai/summarize */
    public function summarize(): void
    {
        header('Content-Type: application/json');

        $ordinanceId = (int) ($_POST['ordinance_id'] ?? 0);
        if (!$ordinanceId) {
            echo json_encode(['error' => 'Missing ordinance ID.']);
            return;
        }

        $ordinance = Ordinance::find($ordinanceId);
        if (!$ordinance) {
            echo json_encode(['error' => 'Ordinance not found.']);
            return;
        }

        // Return cached summary if available
        if (!empty($ordinance['ai_summary'])) {
            echo json_encode(['summary' => $ordinance['ai_summary'], 'cached' => true]);
            return;
        }

        // Extract text from PDF (use pdftotext shell command or parse stored description)
        $text = $ordinance['description'] ?? $ordinance['title'];

        try {
            $summary = $this->ai->summarizeOrdinance($ordinanceId, $text);
            echo json_encode(['summary' => $summary, 'cached' => false]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'AI service unavailable. Please try again later.']);
        }
    }

    /** POST /api/ai/chat */
    public function chat(): void
    {
        header('Content-Type: application/json');

        $question = trim($_POST['question'] ?? '');
        if (strlen($question) < 3) {
            echo json_encode(['error' => 'Please enter a valid question.']);
            return;
        }

        // Fetch 5 most recent published announcements as context
        $recentPosts = Announcement::getPublished(5);

        try {
            $answer = $this->ai->chat($question, $recentPosts);
            echo json_encode(['answer' => $answer]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'AI service unavailable. Please try again later.']);
        }
    }
}
```

### Rate limiting for AI endpoints

```php
// app/middleware/RateLimitMiddleware.php
// Allow max 10 AI requests per user per hour. Store count in DB or APC cache.
class RateLimitMiddleware {
    public function handle(int $userId, int $maxPerHour = 10): void {
        $pdo = db();
        $stmt = $pdo->prepare("\n            SELECT COUNT(*) FROM ai_chat_logs 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $maxPerHour) {
            http_response_code(429);
            echo json_encode(['error' => 'Napalampas mo na ang limitasyon ng AI. Subukan muli pagkatapos ng isang oras.']);
            exit;
        }
    }
}
```

---

## 9. Frontend conventions

### Bootstrap 5 theme overrides (`public/assets/css/main.css`)

```css
:root {
    --bg-primary:     #1a6b3a;   /* Barangay green */
    --bg-secondary:   #f0faf4;
    --accent:         #e8a020;   /* Gold accent */
    --text-dark:      #1c2b1e;
    --radius:         10px;
}

.btn-barangay {
    background-color: var(--bg-primary);
    color: #fff;
    border-radius: var(--radius);
}

.urgency-normal   { border-left: 4px solid #6c757d; }
.urgency-important{ border-left: 4px solid #ffc107; }
.urgency-urgent   { border-left: 4px solid #dc3545; }

.ai-summary-box {
    background: #f0faf4;
    border: 1px solid #c3e6cb;
    border-radius: var(--radius);
    padding: 1.25rem;
}
```

### Alpine.js patterns

```html
<!-- Live search on announcements list -->
<div x-data="{ search: '', items: [] }" x-init="fetchAnnouncements()">
    <input x-model="search" @input.debounce.400ms="fetchAnnouncements()"
           type="text" placeholder="Maghanap ng anunsyo...">
    <template x-for="item in items" :key="item.id">
        <div x-text="item.title"></div>
    </template>
</div>

<!-- Notification bell badge -->
<div x-data="{ count: 0 }" x-init="setInterval(() => fetchUnreadCount(), 60000)">
    <i class="bi bi-bell"></i>
    <span x-show="count > 0" x-text="count" class="badge bg-danger"></span>
</div>

<!-- AI Summarize button -->
<div x-data="{ loading: false, summary: null }">
    <button @click="summarize()" :disabled="loading" class="btn btn-barangay">
        <span x-show="!loading">I-summarize ang Ordinansa</span>
        <span x-show="loading">Naglo-load...</span>
    </button>
    <div x-show="summary" class="ai-summary-box mt-3" x-text="summary"></div>
</div>
```

### AI chat widget (`public/assets/js/ai-widget.js`)

The floating chat widget is included in the resident layout via `shared/_ai-chat-widget.php`. It is a fixed-position pill button (bottom-right) that opens a chat drawer. Messages are sent to `/api/ai/chat` via fetch, and responses are displayed in a scrollable message list. The widget stores conversation history in a JS array in memory (not persisted).

---

## 10. Security requirements

Apply every item below. No exceptions.

- **SQL injection** — use PDO prepared statements for all queries. Never concatenate user input into SQL.
- **XSS** — escape all output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`. For rich text stored from Quill, run through HTMLPurifier before storing.
- **CSRF** — generate a token per session (`$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`). Validate on every POST. Add `<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">` to all forms.
- **File uploads** — validate MIME type with `finfo_file()`, not just extension. Whitelist: `image/jpeg`, `image/png`, `application/pdf`. Max size: 5MB for images, 10MB for PDFs. Store outside webroot or use a randomized filename.
- **Password hashing** — use `password_hash($password, PASSWORD_BCRYPT)` and `password_verify()`. Never store plain text.
- **Session security** — call `session_regenerate_id(true)` on login. Set `session.cookie_httponly = 1` and `session.cookie_secure = 1` in `php.ini` or at runtime.
- **Rate limiting** — AI endpoints: 10 requests per user per hour. Login: 5 failed attempts triggers a 15-minute cooldown (store in `audit_logs`).
- **API key protection** — never expose `ANTHROPIC_API_KEY` in frontend code or JS. All Claude API calls are made server-side only.
- **Input validation** — validate and sanitize every form field server-side. Client-side validation (HTML5 + JS) is UX only, not security.
- **Error handling** — never display raw PHP errors in production. Use a global exception handler that logs to file and shows a friendly error page.

---

## 11. Environment & configuration

### .env.example

```dotenv
# App
APP_NAME="BarangGabay"
APP_URL=http://localhost/baranggabay
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=Asia/Manila

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=baranggabay
DB_USER=root
DB_PASS=

# AI
ANTHROPIC_API_KEY=sk-ant-...

# Mail (PHPMailer / SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=baranggabay@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=no-reply@baranggabay.ph
MAIL_FROM_NAME="BarangGabay - Barangay Bayogo"

# Google Maps
GOOGLE_MAPS_API_KEY=AIza...

# Cloudinary (optional)
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=

# JWT
JWT_SECRET=your-very-long-random-secret-here
JWT_EXPIRY=3600
```

### config/database.php

```php
<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']);
        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
```

---

## 12. Composer dependencies

```json
{
    "name": "baranggabay/app",
    "description": "BarangGabay - Barangay Smart Web Application",
    "type": "project",
    "require": {
        "php": "^8.2",
        "guzzlehttp/guzzle": "^7.8",
        "phpmailer/phpmailer": "^6.9",
        "firebase/php-jwt": "^6.10",
        "ezyang/htmlpurifier": "^4.17",
        "vlucas/phpdotenv": "^5.6",
        "intervention/image": "^2.7"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

---

## 13. Build & run instructions

### Local setup (XAMPP)

```bash
# 1. Clone into htdocs
cd /xampp/htdocs
git clone <repo-url> baranggabay
cd baranggabay

# 2. Install dependencies
composer install

# 3. Set up environment
cp .env.example .env
# Edit .env with your DB credentials and ANTHROPIC_API_KEY

# 4. Create database
# In phpMyAdmin: create database named 'baranggabay' (utf8mb4_unicode_ci)
# Then import:
mysql -u root baranggabay < database/schema.sql

# 5. (Optional) Seed demo data
php database/seeders/DemoDataSeeder.php

# 6. Set upload folder permissions
chmod -R 775 public/uploads

# 7. Visit in browser
# http://localhost/baranggabay
```

### Default superadmin account (seeded)
- **Email:** admin@baranggabay.ph
- **Password:** Admin@1234 (change immediately)

---

## 14. Coding standards

- Follow **PSR-12** coding style for all PHP files
- Use **type declarations** on all function parameters and return types
- All database queries must use **prepared statements** via PDO — no exceptions
- Controllers are thin — business logic lives in **models and services**
- Every public method must have a **docblock** comment
- Use meaningful variable names. No single-letter variables except loop counters
- All user-facing strings in English **and** Filipino where relevant (bilingual UI)
- Views use PHP short echo tags `<?= ?>` only. No logic in views beyond simple conditionals and loops
- Separate concerns: HTML in views, logic in controllers/services, queries in models
- Log all admin actions to `audit_logs` table (what, who, when, IP)
- AI API calls are always wrapped in `try/catch` with graceful fallback messages

---

## 15. Feature checklist

Use this as your implementation tracker:

### Authentication & users
- [ ] Resident registration form with ID upload
- [ ] Email verification link flow
- [ ] Login with session + JWT for AJAX
- [ ] Admin resident verification panel
- [ ] Role-based middleware on all routes
- [ ] "Pending verification" holding page for unverified residents
- [ ] Profile edit page (name, phone, address, avatar)
- [ ] Password change form
- [ ] Audit log entry on login/logout/failed login

### Announcements
- [ ] Admin create/edit/delete announcement
- [ ] Quill.js rich text editor
- [ ] Cover image upload
- [ ] Category and urgency tagging
- [x] Schedule publish date
- [ ] Slug generation
- [ ] Resident paginated list with category filter
- [ ] Resident live search
- [ ] Announcement detail page
- [ ] Notify all verified residents on publish

### Events
- [ ] Admin create/edit event
- [ ] Google Maps venue picker
- [ ] Resident events list (upcoming first)
- [ ] Event detail with embedded map
- [ ] Status management (upcoming/ongoing/completed/cancelled)

### Ordinances & policies
- [ ] Admin PDF upload form
- [ ] Ordinance number + metadata fields
- [ ] PDF.js viewer on detail page
- [ ] "Summarize with AI" button (cached after first call)
- [ ] AI summary displayed in styled callout box
- [ ] Category filter and search

### AI features
- [ ] AIService.php with summarize and chat methods
- [ ] AIController with summarize and chat endpoints
- [ ] Rate limiting middleware (10/hour per user)
- [ ] Floating chat widget (resident layout)
- [ ] ai_chat_logs table population
- [ ] Graceful error handling when API is unavailable

### Notifications
- [ ] NotificationService broadcast methods (all / zone / single)
- [ ] In-app notification bell with unread count badge
- [ ] Notification list page (mark as read, mark all read)
- [ ] Email notification via PHPMailer on key events
- [ ] Verification approval email

### Admin dashboard & analytics
- [ ] Stat cards (total residents, pending verifications, total announcements, events this month)
- [ ] Chart.js: registrations over time (line chart)
- [ ] Chart.js: announcements by category (doughnut)
- [ ] Resident management table with verify/suspend actions
- [ ] Reports page with CSV export

### Security & infrastructure
- [ ] CSRF token on all forms
- [ ] HTMLPurifier on rich-text input
- [ ] File upload MIME validation
- [ ] PDO prepared statements on all queries
- [ ] Rate limiting on AI and login endpoints
- [ ] .htaccess front controller routing
- [ ] .env loaded via phpdotenv (never hardcoded credentials)
- [ ] Global error handler (no raw errors in production)
- [ ] Audit log on all admin actions

---

*Last updated: 2026 | BarangGabay Thesis Project — Barangay Bayogo, Madrid*