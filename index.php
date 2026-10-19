<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

$isAdminView = isset($_GET['admin']);
if ($isAdminView) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}
$adminPassword = getenv('SHERIN_ADMIN_PASSWORD');
$adminPassword = $adminPassword === false ? '' : $adminPassword;
$databaseError = null;
$chatError = null;
$leads = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $database = new PDO(
        'mysql:host=localhost;dbname=sherins_boutique;charset=utf8mb4',
        'root',
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS customer_leads (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(32) NOT NULL,
            interest VARCHAR(120) NOT NULL,
            message VARCHAR(1000) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL
        )'
    );
} catch (PDOException $exception) {
    error_log('Sherin boutique database error: ' . $exception->getMessage());
    $databaseError = 'The customer database is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(403);
        $chatError = 'Your session has expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'login') {
        if ($adminPassword === '') {
            $chatError = 'Admin access is not configured yet. Set SHERIN_ADMIN_PASSWORD on the PHP server.';
        } elseif (hash_equals($adminPassword, (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['boutique_admin'] = true;
            header('Location: index.php?admin=1');
            exit;
        } else {
            $chatError = 'The password was not recognized. Please try again.';
        }
    } elseif (($_POST['action'] ?? '') === 'logout') {
        unset($_SESSION['boutique_admin']);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: index.php?admin=1');
        exit;
    } elseif (($_POST['action'] ?? '') === 'save_lead') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $interest = trim((string) ($_POST['interest'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $honeypot = trim((string) ($_POST['website'] ?? ''));

        if ($honeypot !== '') {
            http_response_code(400);
            $chatError = 'We could not submit your request. Please try again.';
        } elseif ($databaseError !== null) {
            http_response_code(503);
            $chatError = $databaseError;
        } elseif ($name === '' || mb_strlen($name) > 100 ||
            $phone === '' || mb_strlen($phone) > 32 ||
            !preg_match('/^[0-9+().\-\s]{7,32}$/', $phone) ||
            $interest === '' || mb_strlen($interest) > 120 ||
            mb_strlen($message) > 1000 ||
            ($_POST['consent'] ?? '') !== 'yes') {
            http_response_code(422);
            $chatError = 'Please check your details and consent, then try again.';
        } else {
            try {
                $statement = $database->prepare(
                    'INSERT INTO customer_leads (name, phone, interest, message, created_at)
                     VALUES (:name, :phone, :interest, :message, :created_at)'
                );
                $statement->execute([
                    ':name' => $name,
                    ':phone' => $phone,
                    ':interest' => $interest,
                    ':message' => $message,
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);
                header('Location: index.php?saved=1#home');
                exit;
            } catch (PDOException $exception) {
                error_log('Sherin boutique lead save error: ' . $exception->getMessage());
                http_response_code(500);
                $chatError = 'We could not save your request. Please try again later.';
            }
        }
    } else {
        http_response_code(400);
        $chatError = 'That request could not be completed.';
    }
}

if ($isAdminView && !empty($_SESSION['boutique_admin']) && $databaseError === null) {
    $leads = $database->query(
        'SELECT id, name, phone, interest, message, created_at
         FROM customer_leads ORDER BY id DESC LIMIT 500'
    )->fetchAll();
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171313">
    <meta name="description" content="Discover thoughtfully made occasionwear and everyday pieces at Sherin's Boutique.">
    <title>Sherin's Boutique | Made to make you feel beautiful</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
  </head>
  <body>
    <?php if ($isAdminView): ?>
    <main class="admin-page">
      <div class="admin-topbar">
        <a class="footer-brand" href="index.php">sherin's <span>B O U T I Q U E</span></a>
        <a class="admin-back-link" href="index.php">Back to boutique</a>
      </div>
      <section class="admin-card">
        <?php if ($databaseError !== null): ?>
          <p class="form-alert" role="alert"><?= escapeHtml($databaseError) ?></p>
        <?php elseif (empty($_SESSION['boutique_admin'])): ?>
          <p class="eyebrow eyebrow-dark"><span></span> PRIVATE AREA</p>
          <h1 class="admin-title">Boutique enquiries</h1>
          <?php if ($adminPassword === ''): ?>
            <p class="admin-note">Admin access is not configured. Set the <code>SHERIN_ADMIN_PASSWORD</code> environment variable on the PHP server, then reload this page.</p>
          <?php else: ?>
            <p class="admin-note">Enter your admin password to view customer enquiries.</p>
            <?php if ($chatError !== null): ?><p class="form-alert" role="alert"><?= escapeHtml($chatError) ?></p><?php endif; ?>
            <form method="post" action="index.php?admin=1" class="admin-login-form">
              <input type="hidden" name="action" value="login">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
              <label for="adminPassword">Admin password</label>
              <input id="adminPassword" name="password" type="password" autocomplete="current-password" required>
              <button class="button-light" type="submit">Sign in <i class="bi bi-arrow-right"></i></button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="admin-heading-row">
            <div>
              <p class="eyebrow eyebrow-dark"><span></span> PRIVATE AREA</p>
              <h1 class="admin-title">Customer enquiries</h1>
              <p class="admin-note"><?= count($leads) ?> most recent enquiries</p>
            </div>
            <form method="post" action="index.php?admin=1">
              <input type="hidden" name="action" value="logout">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
              <button class="admin-logout" type="submit">Sign out</button>
            </form>
          </div>
          <?php if ($chatError !== null): ?><p class="form-alert" role="alert"><?= escapeHtml($chatError) ?></p><?php endif; ?>
          <?php if ($leads === []): ?>
            <p class="admin-empty">No enquiries yet. Customer requests submitted through the chat will appear here.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>Date</th><th>Name</th><th>Phone</th><th>Interest</th><th>Message</th></tr></thead>
                <tbody>
                  <?php foreach ($leads as $lead): ?>
                    <tr>
                      <td><?= escapeHtml($lead['created_at']) ?></td>
                      <td><?= escapeHtml($lead['name']) ?></td>
                      <td><?= escapeHtml($lead['phone']) ?></td>
                      <td><?= escapeHtml($lead['interest']) ?></td>
                      <td><?= escapeHtml($lead['message'] !== '' ? $lead['message'] : '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <p class="admin-note">Showing up to 500 newest enquiries.</p>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </main>
    <?php else: ?>
    <div class="announcement-bar text-center">
      Thoughtfully chosen. Beautifully yours. <span class="announcement-divider">|</span> Complimentary shipping on orders over $100
    </div>

    <header class="site-header sticky-top">
      <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
          <a class="navbar-brand brand-mark" href="#home" aria-label="Sherin's Boutique home">
            <span class="brand-name">sherin's</span>
            <span class="brand-subtitle">B O U T I Q U E</span>
          </a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav mx-auto">
              <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
              <li class="nav-item"><a class="nav-link" href="#collections">Collections</a></li>
              <li class="nav-item"><a class="nav-link" href="#story">Our story</a></li>
              <li class="nav-item"><a class="nav-link" href="#visit">Visit us</a></li>
            </ul>
            <a class="nav-cta" href="#collections">Explore the edit <i class="bi bi-arrow-up-right"></i></a>
          </div>
        </div>
      </nav>
    </header>

    <main>
      <section class="hero-section" id="home">
        <div class="hero-image" role="img" aria-label="A woman in an elegant red evening dress"></div>
        <div class="hero-shade"></div>
        <div class="container hero-content">
          <p class="eyebrow"><span></span> A little more you, in every detail</p>
          <h1>Wear what<br>makes you <em>feel.</em></h1>
          <p class="hero-copy">Considered pieces for your everyday moments<br class="d-none d-md-block"> and the ones worth celebrating.</p>
          <a class="button-light" href="#collections">Discover the collection <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="hero-note"><span>01</span> / A NEW SEASON, YOUR WAY</div>
        <a class="scroll-cue" href="#collections" aria-label="Scroll to collections"><i class="bi bi-arrow-down"></i></a>
      </section>

      <section class="intro-strip">
        <div class="container">
          <p>Personal style, with a little <span>heart.</span></p>
          <span class="intro-rule"></span>
          <p class="intro-small">CURATED WITH CARE IN EVERY STITCH</p>
        </div>
      </section>

      <section class="section-padding collections-section" id="collections">
        <div class="container">
          <div class="section-heading">
            <div>
              <p class="eyebrow eyebrow-dark"><span></span> FIND YOUR FEELING</p>
              <h2>Pieces to make<br>the moment <em>yours.</em></h2>
            </div>
            <p class="section-aside">From the everyday to the unforgettable,<br>find something that feels like you.</p>
          </div>

          <div class="row g-4 collection-grid">
            <div class="col-md-6">
              <a class="collection-card collection-card-large" href="#visit">
                <img src="img3.jpeg" alt="Elegant fashion look in a warm, minimal setting" loading="lazy">
                <span class="collection-overlay"></span>
                <span class="collection-label"><small>01 / EVERYDAY ELEGANCE</small><strong>The ready-to-wear edit</strong><i class="bi bi-arrow-up-right"></i></span>
              </a>
            </div>
            <div class="col-md-6">
              <a class="collection-card collection-card-large" href="#visit">
                <img src="img2.jpeg" alt="A stylish woman wearing a carefully tailored outfit" loading="lazy">
                <span class="collection-overlay"></span>
                <span class="collection-label"><small>02 / MADE FOR YOUR MOMENT</small><strong>Occasion, beautifully</strong><i class="bi bi-arrow-up-right"></i></span>
              </a>
            </div>
          </div>
          <div class="collection-footnote"><span>HANDPICKED, NEVER HURRIED</span><span>01 — 02</span></div>
        </div>
      </section>

      <section class="story-section" id="story">
        <div class="story-image" role="img" aria-label="Thoughtfully styled fashion detail"></div>
        <div class="story-panel">
          <div class="story-content">
            <p class="eyebrow"><span></span> A NOTE FROM SHERIN</p>
            <h2>Style is personal.<br><em>Let’s keep it that way.</em></h2>
            <p>At Sherin’s, we believe getting dressed should feel like coming home to yourself. That’s why every piece is chosen with care—for its quality, its character, and the way it makes you feel.</p>
            <p>Come as you are. We’ll help you find the rest.</p>
            <a class="text-link" href="#visit">Get to know us <i class="bi bi-arrow-right"></i></a>
            <span class="signature">With love, Sherin</span>
          </div>
        </div>
      </section>

      <section class="quote-section">
        <div class="container text-center">
          <span class="quote-mark">“</span>
          <blockquote>“I walked in looking for an outfit<br class="d-none d-md-block"> and left feeling like myself again.”</blockquote>
          <p class="quote-credit">A LITTLE LOVE FROM OUR COMMUNITY</p>
          <div class="quote-stars" aria-label="Five out of five stars">★★★★★</div>
        </div>
      </section>

      <section class="visit-section section-padding" id="visit">
        <div class="container">
          <div class="visit-card">
            <div class="visit-copy">
              <p class="eyebrow"><span></span> YOUR NEXT FAVOURITE IS HERE</p>
              <h2>Come on in.<br><em>Stay a little.</em></h2>
              <p>There’s nothing like finding that just-right piece in person. Drop by, say hello, and let’s find something wonderful together.</p>
              <a class="button-light" href="mailto:hello@sherinsboutique.com">Say hello <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="visit-detail">
              <div>
                <span class="detail-label">FIND OUR BOUTIQUE</span>
                <p>Visit us in-store<br>for a little personal styling.</p>
              </div>
              <div>
                <span class="detail-label">KEEP IN TOUCH</span>
                <a href="mailto:hello@sherinsboutique.com">hello@sherinsboutique.com</a>
                <a href="https://instagram.com/" target="_blank" rel="noreferrer">@sherinsboutique <i class="bi bi-arrow-up-right"></i></a>
              </div>
              <div class="detail-hours">
                <span class="detail-label">A NOTE ON HOURS</span>
                <p>Monday – Saturday<br>10:00 am – 6:00 pm</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="site-footer">
      <div class="container footer-inner">
        <a class="footer-brand" href="#home">sherin's <span>B O U T I Q U E</span></a>
        <p>Made to make you feel beautiful.</p>
        <div class="footer-bottom">
          <span>© <span id="currentYear"></span> Sherin’s Boutique. All the little things, with love.</span>
          <a href="index.php?admin=1">ADMIN</a>
          <a href="#home">BACK TO TOP ↑</a>
        </div>
      </div>
      </footer>
      <button class="chat-launcher" id="chatLauncher" type="button" aria-label="Open Sherin's style assistant" aria-expanded="false">
        <i class="bi bi-chat-heart-fill"></i><span>Ask Sherin</span>
      </button>
      <section class="chat-panel" id="chatPanel" aria-label="Sherin's style assistant" aria-hidden="true">
        <div class="chat-header">
          <div><strong>Sherin’s style assistant</strong><span>Here to help you find your feel</span></div>
          <button class="chat-close" id="chatClose" type="button" aria-label="Close chat"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="chat-messages" id="chatMessages" aria-live="polite">
          <p class="chat-message chat-bot-message">Hello! I can help Sherin find the right pieces for you. What should we call you?</p>
        </div>
        <form class="chat-reply-form" id="chatReplyForm">
          <label class="visually-hidden" for="chatReply">Your reply</label>
          <input id="chatReply" type="text" maxlength="100" placeholder="Type your reply…" autocomplete="off" required>
          <button type="submit" aria-label="Send reply"><i class="bi bi-arrow-up"></i></button>
        </form>
        <p class="chat-privacy">Guided assistant; your details are sent to Sherin only with your permission.</p>
        <form method="post" action="index.php" id="leadForm" class="d-none">
          <input type="hidden" name="action" value="save_lead">
          <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="name" id="leadName">
          <input type="hidden" name="phone" id="leadPhone">
          <input type="hidden" name="interest" id="leadInterest">
          <input type="hidden" name="message" id="leadMessage">
          <input class="honeypot-field" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
          <input type="checkbox" name="consent" id="leadConsent" value="yes">
        </form>
      </section>
      <?php if ($chatError !== null): ?>
        <p class="chat-server-error" role="alert"><?= escapeHtml($chatError) ?></p>
      <?php endif; ?>
      <?php if (isset($_GET['saved'])): ?>
        <p class="chat-server-success" role="status">Thank you! Your details have been sent to Sherin’s Boutique.</p>
      <?php endif; ?>
      <?php endif; ?>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
      <script src="script.js"></script>
  </body>
</html>
