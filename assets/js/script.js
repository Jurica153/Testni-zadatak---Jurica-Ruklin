$(document).ready(function() {
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Jeste li sigurni da želite obrisati? Ova radnja je nepovratna!')) {
            e.preventDefault();
        }
    });

    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#newsletter_content',
            plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            height: 400,
            menubar: false,
            content_css: '<?php echo BASE_URL; ?>/assets/css/style.css',
            body_class: 'tinymce-content'
        });
    }
});