<?php
// includes/footer.php

$base_url = defined('BASE_URL') ? BASE_URL : '';
?>
            </div> <!-- /.main-content -->

            <!-- Footer -->
            <footer class="footer">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Task Management <?= date('Y') ?></span>
                    </div>
                </div>
            </footer>
            <!-- /Footer -->

        </div> <!-- /#content -->
    </div> <!-- /.wrapper -->

    <!-- Bootstrap Bundle with Popper -->
    <script src="<?= $base_url ?>/assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>
    <!-- jQuery (əgər başqa skriptlər üçün lazımdırsa) -->
    <script src="<?= $base_url ?>/assets/vendors/jquery/jquery-3.7.1.min.js"></script>
    <!-- Chart.js (Statistika və Dashboard üçün) -->
    <script src="<?= $base_url ?>/assets/vendors/chart.js/Chart.min.js"></script>

    <!-- === PUSHER VƏ BİLDİRİŞ SKRİPTLƏRİ === -->
    <?php if (defined('PUSHER_ENABLED') && PUSHER_ENABLED === true && isset($_SESSION['user_id'])): // Yalnız Pusher aktivdirsə və istifadəçi daxil olubsa ?>
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script> <?php // Pusher CDN və ya lokal fayl ?>
        <script src="<?= $base_url ?>/assets/js/notifications.js"></script> <?php // Bizim bildiriş skriptimiz ?>
    <?php endif; ?>
    <!-- === PUSHER VƏ BİLDİRİŞ SKRİPTLƏRİ SONU === -->

    <!-- Custom Scripts (Sidebar Toggle, Tooltips) -->
    <script>
        // Sidebar Toggle Script
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
                $('.overlay').toggleClass('active');
            });

            // Overlay kliklədikdə sidebarı bağla
            $('.overlay').on('click', function () {
                $('#sidebar').removeClass('active');
                $('.overlay').removeClass('active');
            });

             // Tooltip-ləri aktivləşdir (əgər istifadə olunursa)
             var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
             var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
               return new bootstrap.Tooltip(tooltipTriggerEl)
             })
        });
    </script>

    <?php
        // Hər modula məxsus əlavə skriptlər burada yüklənə bilər
        // Məsələn, statistika səhifəsi üçün chart konfiqurasiyası
        // if ($current_module === 'statistics' || $current_module === 'dashboard') {
        //     echo '<script src="' . $base_url . '/assets/js/charts-config.js"></script>';
        // }
    ?>

</body>
</html>
