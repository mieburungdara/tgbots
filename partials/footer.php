<?php
// This is a bit of a hack, but we need to know if we're on an admin page
// to close the correct divs.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$is_admin_page_footer = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
?>
            </div> <!-- .content -->
        </main> <!-- .container -->
<?php if ($is_admin_page_footer): ?>
    </div> <!-- .admin-main-content -->
<?php endif; ?>

<!-- Prism.js for Syntax Highlighting -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js" integrity="sha512-9khQRAUBYEJDCDVP2yw3LRUQvjJ0Pjx0ESbkJChCntkAYQCRJ1EBs_milbDUCNBDOLeDDztge1sEgEgALEA/dw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js" integrity="sha512-S_1iUeECGqPTcZCjD9OkaHcsUeI9OT2i7wvep96AGs2SMhCaGxS6yif7xJ/S23DkpceEqCiaZ5XjB2S_2sDk9A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</body>
</html>
