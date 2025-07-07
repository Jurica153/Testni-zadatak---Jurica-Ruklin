<?php
include_once '../../includes/db.php';
include_once '../../includes/functions.php';

require_admin_login();

$message = '';
$error = '';
$import_successful = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG: FILES Array: " . print_r($_FILES, true));

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['csv_file']['tmp_name'];
        $file_type = mime_content_type($file_tmp_path);
        $file_name = $_FILES['csv_file']['name'];

        error_log("DEBUG: Detected file type: " . $file_type . ", File name: " . $file_name);

        $allowed_mime_types = [
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
            'text/plain'
        ];

        $is_allowed_mime_type = in_array($file_type, $allowed_mime_types);
        error_log("DEBUG: Is allowed MIME type? " . ($is_allowed_mime_type ? 'Yes' : 'No'));


        if ($is_allowed_mime_type) {
            $handle = false;
            $delimiter = ',';

            if (filesize($file_tmp_path) > 0) {
                $handle = fopen($file_tmp_path, "r");
                if ($handle !== FALSE) {
                    $test_header = fgetcsv($handle, 1000, ',');
                    if ($test_header === FALSE || empty(array_filter($test_header, 'strlen'))) {
                        $delimiter = ';';
                        rewind($handle); 
                        $test_header = fgetcsv($handle, 1000, ';');
                        if ($test_header === FALSE || empty(array_filter($test_header, 'strlen'))) {
                            $error = "Datoteka je prazna ili je format zaglavlja neispravan. Provjerite da datoteka nije prazna i da sadrži valjane podatke.";
                            error_log("ERROR: CSV header could not be read or is empty with both delimiters. File: " . $file_name);
                            fclose($handle);
                            $handle = false; 
                        } else {
                            rewind($handle);
                        }
                    } else {
                        rewind($handle); 
                    }
                }
            } else {
                $error = "Datoteka je prazna.";
                error_log("ERROR: Uploaded file is empty. File: " . $file_name);
            }

            if ($handle !== FALSE) {
                $imported_count = 0;
                $skipped_count = 0;
                $pdo->beginTransaction();
                try {
                    fgetcsv($handle, 1000, $delimiter);

                    error_log("DEBUG: CSV Header (using delimiter '" . $delimiter . "'): " . print_r($test_header, true));


                    while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                        error_log("DEBUG: CSV Row Data (using delimiter '" . $delimiter . "'): " . print_r($data, true));
                       
                        if (empty(array_filter($data, 'strlen'))) {
                            error_log("Skipped empty row.");
                            continue;
                        }

                        if (!isset($data[0]) || empty(trim($data[0]))) {
                            $skipped_count++;
                            error_log("Skipped row with missing or empty email column.");
                            continue;
                        }

                        $email = sanitize_input($data[0] ?? '');
                        $name = sanitize_input($data[1] ?? ''); 

                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $skipped_count++;
                            error_log("Skipped invalid email: " . $email);
                            continue; 
                        }

                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE email = :email");
                        $stmt_check->execute(['email' => $email]);

                        if ($stmt_check->fetchColumn() == 0) {
                            $stmt_insert = $pdo->prepare("INSERT INTO subscribers (email, name, is_active) VALUES (:email, :name, 1)");
                            $stmt_insert->execute(['email' => $email, 'name' => $name]);
                            $imported_count++;
                        } else {
                            $skipped_count++; 
                            error_log("Skipped duplicate email: " . $email);
                        }
                    }
                    fclose($handle);
                    $pdo->commit();
                    $message = "Uspješno uvezeno {$imported_count} pretplatnika. Preskočeno {$skipped_count} (duplikati ili nevažeći e-mailovi).";
                    $import_successful = true; 
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Greška prilikom uvoza CSV datoteke: " . $e->getMessage();
                    error_log("CSV import error: " . $e->getMessage());
                }
            } else {
                if (empty($error)) { 
                    $error = "Greška prilikom otvaranja CSV datoteke. Provjerite prava pristupa i format.";
                    error_log("Failed to open CSV file after delimiter detection: " . $file_tmp_path);
                }
            }
        } else {
            $error = "Molimo odaberite važeću CSV datoteku. Detektirani tip: " . htmlspecialchars($file_type);
            error_log("Invalid file type uploaded: " . $file_type);
        }
    } else {
        $error = "Greška prilikom uploada datoteke. Kod greške: " . ($_FILES['csv_file']['error'] ?? 'N/A');
        error_log("File upload error code: " . ($_FILES['csv_file']['error'] ?? 'N/A'));
    }
}

if ($import_successful) {
    $error = '';
}

include_once '../../includes/header.php';
?>

<div class="container">
    <h2>Uvezi pretplatnike (CSV)</h2>
    <?php if (!empty($error)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form action="import.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="csv_file">Odaberite CSV datoteku:</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
            <small>Datoteka bi trebala imati e-mail adrese u prvoj koloni, a opcionalno ime u drugoj. Koristite zarez (`,`) ili točka-zarez (`;`) kao separator.</small>
        </div>
        <button type="submit">Uvezi</button>
        <a href="index.php" class="button" style="margin-left: 10px;">Natrag na popis</a>
    </form>
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
