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

</body>
</html>
