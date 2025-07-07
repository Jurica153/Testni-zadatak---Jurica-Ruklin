<?php
include_once '../../includes/db.php';
include_once '../../includes/functions.php';

require_admin_login();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = null;
$error = '';
$success = '';

if ($user_id > 0) {
    $stmt = $pdo->prepare("SELECT id, username, email, role, is_active FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Korisnik nije pronađen.";
    }
} else {
    $error = "Nevažeći ID korisnika.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $role = sanitize_input($_POST['role'] ?? 'user');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email)) {
        $error = "Korisničko ime i e-mail su obavezni.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Neispravan format e-mail adrese.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Lozinke se ne podudaraju.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = "Nova lozinka mora imati najmanje 6 znakova.";
    } else {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND id != :id");
        $stmt_check->execute(['username' => $username, 'email' => $email, 'id' => $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            $error = "Korisničko ime ili e-mail adresa već postoji za drugog korisnika.";
        } else {
            $sql = "UPDATE users SET username = :username, email = :email, role = :role, is_active = :is_active";
            $params = [
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'is_active' => $is_active,
                'id' => $user_id
            ];

            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $sql .= ", password_hash = :password_hash";
                $params['password_hash'] = $password_hash;
            }

            $sql .= " WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = "Korisnik uspješno ažuriran.";
                $stmt = $pdo->prepare("SELECT id, username, email, role, is_active FROM users WHERE id = :id");
                $stmt->execute(['id' => $user_id]);
                $user = $stmt->fetch();
            } else {
                $error = "Greška prilikom ažuriranja korisnika.";
            }
        }
    }
}

include_once '../../includes/header.php';
?>

<div class="container">
    <h2>Uredi korisnika: <?php echo htmlspecialchars($user['username'] ?? ''); ?></h2>
    <?php if (!empty($error)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <?php if ($user): ?>
        <form action="edit.php?id=<?php echo $user['id']; ?>" method="POST">
            <div class="form-group">
                <label for="username">Korisničko ime:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Nova lozinka (ostavi prazno za nepromijenjeno):</label>
                <input type="password" id="password" name="password">
            </div>
            <div class="form-group">
                <label for="confirm_password">Potvrdi novu lozinku:</label>
                <input type="password" id="confirm_password" name="confirm_password">
            </div>
            <div class="form-group">
                <label for="role">Uloga:</label>
                <select id="role" name="role">
                    <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>Korisnik</option>
                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                </select>
            </div>
            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>> Aktivan
                </label>
            </div>
            <button type="submit">Ažuriraj korisnika</button>
            <a href="index.php" class="button" style="margin-left: 10px;">Natrag na popis</a>
        </form>
    <?php else: ?>
        <p>Korisnik nije pronađen ili je došlo do greške.</p>
        <a href="index.php" class="button">Natrag na popis korisnika</a>
    <?php endif; ?>
</div>

<?php
include_once '../../includes/footer.php';
?>

<head>
    <meta charset="UTF-8">
    <title>Prijava</title>
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
</head>

<head>
    <link rel="stylesheet" href="assets/css/style.css">
</head>