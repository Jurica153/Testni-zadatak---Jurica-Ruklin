<?php
include_once 'includes/db.php';
include_once 'includes/functions.php';

if (is_logged_in()) {
    error_log("Index.php: Session role is: " . ($_SESSION['role'] ?? 'N/A'));
    echo "DEBUG: Index.php - Session role is: " . ($_SESSION['role'] ?? 'N/A') . "<br>"; 

    if (is_admin()) {
        redirect('/admin/dashboard.php');
    } else {
        include_once 'includes/header.php';
        echo '<div class="container">';
        echo '<h2>Dobrodošli na Newsletter Dashboard!</h2>';
        echo '<p>Vaš korisnički račun je uspješno prijavljen.</p>';
        echo '<p>Trenutno nema posebnog dashboarda za obične korisnike.</p>';
        echo '</div>';
        include_once 'includes/footer.php';
    }
} else {
    redirect('/login.php');
}
?>
