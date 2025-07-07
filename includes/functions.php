<?php
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>