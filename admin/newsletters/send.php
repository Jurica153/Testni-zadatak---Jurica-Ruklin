<?php
include_once '../../includes/db.php';
include_once '../../includes/functions.php';

require_admin_login();

$newsletter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

if ($newsletter_id > 0) {
    $stmt = $pdo->prepare("SELECT id, title, subject, content, status FROM newsletters WHERE id = :id");
    $stmt->execute(['id' => $newsletter_id]);
    $newsletter = $stmt->fetch();

    if (!$newsletter) {
        $error = "Newsletter nije pronađen.";
    } elseif ($newsletter['status'] === 'sent') {
        $error = "Ovaj newsletter je već poslan.";
    } else {
        $stmt_targets = $pdo->prepare("SELECT group_id FROM newsletter_targets WHERE newsletter_id = :newsletter_id");
        $stmt_targets->execute(['newsletter_id' => $newsletter_id]);
        $target_group_ids = array_column($stmt_targets->fetchAll(), 'group_id');

        $recipients = [];

        if (empty($target_group_ids)) {
            $stmt_subscribers = $pdo->query("SELECT email FROM subscribers WHERE is_active = TRUE");
            $recipients = array_column($stmt_subscribers->fetchAll(), 'email');
            error_log("DEBUG: Newsletter has no target groups, sending to all active subscribers.");
        } else {
            $placeholders = implode(',', array_fill(0, count($target_group_ids), '?'));
            $stmt_users = $pdo->prepare("
                SELECT DISTINCT u.email
                FROM users u
                JOIN user_groups ug ON u.id = ug.user_id
                WHERE ug.group_id IN ($placeholders) AND u.is_active = TRUE
            ");
            $stmt_users->execute($target_group_ids);
            $user_emails = array_column($stmt_users->fetchAll(), 'email');
            error_log("DEBUG: User emails from target groups: " . implode(', ', $user_emails));

            $stmt_subscribers = $pdo->query("SELECT email FROM subscribers WHERE is_active = TRUE");
            $subscriber_emails = array_column($stmt_subscribers->fetchAll(), 'email');
            error_log("DEBUG: All active subscriber emails: " . implode(', ', $subscriber_emails));

            $recipients = array_unique(array_merge($user_emails, $subscriber_emails));
            error_log("DEBUG: Combined unique recipients: " . implode(', ', $recipients));
        }

        if (empty($recipients)) {
            $error = "Nema primatelja za ovaj newsletter (ni u odabranim grupama ni na mailing listi).";
        } else {
            $message = "Newsletter '" . htmlspecialchars($newsletter['title']) . "' je **simulirano** poslan na " . count($recipients) . " adresa.";
            $message .= "<br>Primatelji: " . htmlspecialchars(implode(', ', $recipients));

            $stmt_update = $pdo->prepare("UPDATE newsletters SET status = 'sent', sent_at = NOW() WHERE id = :id");
            $stmt_update->execute(['id' => $newsletter_id]);
        }
    }
} else {
    $error = "Nevažeći ID newslettera za slanje ili newsletter nije pronađen/već poslan.";
}

include_once '../../includes/header.php';
?>

<div class="container">
    <h2>Slanje Newslettera</h2>
    <?php if (!empty($error)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <p class="success-message"><?php echo $message; ?></p>
    <?php endif; ?>

    <p>Ovdje se obrađuje slanje newslettera. U stvarnoj aplikaciji, ovo bi uključivalo integraciju s vanjskim servisom za slanje e-mailova (npr. SendGrid, Mailgun, PHPMailer).</p>
    <a href="<?php echo BASE_URL; ?>/admin/newsletters/index.php" class="button">Natrag na popis newslettera</a>
</div>

<?php
include_once '../../includes/footer.php';
?>

<style>
html, body {
    margin: 0;
    padding: 0;
    font-family: 'Inter', sans-serif;
    height: 100%; 
    overflow: hidden; 
    background-color: #f4f7f6;
    color: #333;
}

.dashboard-layout {
    display: flex;
    height: 100vh; 
}

.sidebar {
    width: 250px;
    background-color: #2c3e50;
    color: #ecf0f1;
    padding: 20px 0;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0; 
    overflow-y: auto; 
}

.sidebar .logo {
    text-align: center;
    margin-bottom: 30px;
    padding: 0 20px;
}

.sidebar .logo a {
    color: #ecf0f1;
    text-decoration: none;
    font-size: 1.8em;
    font-weight: bold;
    display: block;
}

.sidebar .user-info {
    text-align: center;
    margin-bottom: 30px;
    padding: 0 20px;
}

.sidebar .user-info img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #3498db;
    object-fit: cover;
    margin-bottom: 10px;
}

.sidebar .user-info span {
    display: block;
    font-size: 1.1em;
    font-weight: 600;
    color: #ecf0f1;
}

.sidebar .user-info small {
    display: block;
    font-size: 0.9em;
    color: #bdc3c7;
}

.sidebar .nav-links {
    list-style: none;
    padding: 0;
    margin: 0;
    width: 100%;
}

.sidebar .nav-links li {
    width: 100%;
}

.sidebar .nav-links li a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #ecf0f1;
    text-decoration: none;
    transition: background-color 0.3s ease, color 0.3s ease;
    font-weight: 500;
}

.sidebar .nav-links li a:hover,
.sidebar .nav-links li a.active {
    background-color: #34495e; 
    color: #ffffff;
}

.sidebar .nav-links li a i {
    margin-right: 10px;
    font-size: 1.2em;
}

.main-content-wrapper {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden; 
}

.topbar {
    background-color: #34495e; 
    color: #ecf0f1;
    padding: 15px 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0; 
}

.topbar .page-title {
    font-size: 1.5em;
    font-weight: bold;
    margin: 0;
}

.topbar .top-nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.topbar .top-nav-right a {
    color: #ecf0f1;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.topbar .top-nav-right a:hover {
    color: #3498db;
}

.topbar .top-nav-right .user-dropdown {
    position: relative;
}

.topbar .top-nav-right .user-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.topbar .top-nav-right .user-dropdown-toggle img {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
}

.topbar .top-nav-right .user-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background-color: #2c3e50;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    list-style: none;
    padding: 10px 0;
    margin-top: 10px;
    min-width: 150px;
    z-index: 100;
    display: none; 
}

.topbar .top-nav-right .user-dropdown-menu.active {
    display: block; 
}

.topbar .top-nav-right .user-dropdown-menu li a {
    display: block;
    padding: 8px 15px;
    color: #ecf0f1;
    text-decoration: none;
    white-space: nowrap;
}

.topbar .top-nav-right .user-dropdown-menu li a:hover {
    background-color: #34495e;
}


.main-content {
    flex-grow: 1;
    padding: 20px;
    background-color: #f4f7f6; 
    overflow-y: auto; 
}

.container {
    width: 100%; 
    max-width: 1200px;
    margin: 0 auto; 
    padding: 20px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

footer {
    text-align: center;
    padding: 15px;
    background-color: #2c3e50;
    color: #ecf0f1;
    flex-shrink: 0; 
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group select,
.form-group textarea,
.form-group input[type="datetime-local"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-sizing: border-box; 
    background-color: #f9f9f9;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

button[type="submit"], .button {
    background-color: #3498db;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    transition: background-color 0.3s ease;
    text-decoration: none; 
    display: inline-block; 
    text-align: center;
    list-style: none; 
}

button[type="submit"]:hover, .button:hover {
    background-color: #2980b9;
}

.message {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 5px;
}

.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden; 
}

table th, table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

table th {
    background-color: #f2f2f2;
    font-weight: bold;
    color: #555;
}

table tr:nth-child(even) {
    background-color: #f9f9f9;
}

table tr:hover {
    background-color: #f1f1f1;
}

table td a {
    color: #3498db;
    text-decoration: none;
    margin-right: 5px;
}

table td a:hover {
    text-decoration: underline;
}

.login-container, .register-container {
    max-width: 400px;
    margin: 50px auto;
    background-color: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.login-container h2, .register-container h2 {
    color: #2c3e50;
    margin-bottom: 25px;
}

.login-container p, .register-container p {
    margin-top: 20px;
    font-size: 0.9em;
}

.login-container p a, .register-container p a {
    color: #3498db;
    text-decoration: none;
    font-weight: bold;
}

.login-container p a:hover, .register-container p a:hover {
    text-decoration: underline;
}

.dashboard-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.tile {
    background-color: #ecf0f1;
    padding: 25px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tile:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.tile h3 {
    color: #2c3e50;
    margin-top: 0;
    margin-bottom: 15px;
}

.tile p {
    color: #555;
    font-size: 0.95em;
}

.tile a {
    display: inline-block;
    margin-top: 15px;
    background-color: #3498db;
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 0.9em;
    transition: background-color 0.3s ease;
}

.tile a:hover {
    background-color: #2980b9;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: normal;
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
    width: auto; 
}

.tox-tinymce {
    border-radius: 5px !important;
    border: 1px solid #ddd !important;
}

@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');

@media (max-width: 768px) {
    .dashboard-layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        height: auto;
        padding: 15px 0;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        flex-direction: row; 
        justify-content: space-around;
        align-items: center;
        overflow-x: auto; 
        overflow-y: hidden;
    }

    .sidebar .logo,
    .sidebar .user-info {
        display: none; 
    }

    .sidebar .nav-links {
        display: flex;
        flex-direction: row;
        gap: 10px;
        width: auto;
    }

    .sidebar .nav-links li a {
        padding: 8px 12px;
        font-size: 0.9em;
    }

    .sidebar .nav-links li a i {
        margin-right: 5px;
    }

    .topbar {
        padding: 10px 15px;
    }

    .topbar .page-title {
        font-size: 1.2em;
    }

    .topbar .top-nav-right {
        gap: 10px;
    }

    .main-content {
        padding: 15px;
    }

    .container {
        padding: 15px;
    }
}

    </style>