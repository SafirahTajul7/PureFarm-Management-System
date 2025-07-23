<!-- Update in includes/footer.php -->
<footer class="footer">
            <div class="footer-content">
                <div class="footer-left">
                    <p>&copy; <?php echo date('Y'); ?> PureFarm Management System. All rights reserved.</p>
                </div>
                <div class="footer-right">
                    <p>Version 1.0</p>
                </div>
            </div>
        </footer>
    </div><!-- Close wrapper div -->

    <!-- Core JavaScript -->
    <script src="/PureFarm/assets/js/jquery.min.js"></script>
    <script src="/PureFarm/assets/js/main.js"></script>

    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>


</html>