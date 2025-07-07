</main>
        </div> 
    </div> 
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    <script>
        $(document).ready(function() {
            $('.user-dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                $(this).next('.user-dropdown-menu').toggleClass('active');
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-dropdown').length) {
                    $('.user-dropdown-menu').removeClass('active');
                }
            });
        });
    </script>
    <!--<footer style="position: fixed; bottom: 0; width: 100%; text-align: center; padding: 15px; background-color: #2c3e50; color: #ecf0f1;">
        <p>&copy; <?php echo date("Y"); ?>Users Newsletters Dashboard. Sva prava pridržana.</p>
    </footer>-->
</body>
</html>


