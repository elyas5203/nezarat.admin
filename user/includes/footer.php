<?php // user/includes/footer.php ?>
            </main>
            <footer class="main-footer-bottom">
                <p>سامانه مدیریت دبستان | پنل کاربری &copy; <?php echo to_jalali(date('Y-m-d'), 'yyyy'); ?></p>
            </footer>
        </div> <!-- End main-content -->
    </div> <!-- End dashboard-container -->

    <script>
        // Script for live time and date (same as admin footer)
        function updateLiveTimeAndDate() {
            const now = new Date();
            const optionsTime = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran', numberingSystem: 'arab' };
            const optionsDate = { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Tehran', calendar: 'persian', numberingSystem: 'arab' };

            let timeString = 'hh:mm:ss';
            let dateString = 'درحال بارگذاری...';

            try {
                if (typeof Intl !== 'undefined' && typeof Intl.DateTimeFormat !== 'undefined') {
                    timeString = new Intl.DateTimeFormat('fa-IR', optionsTime).format(now);
                    dateString = new Intl.DateTimeFormat('fa-IR', optionsDate).format(now);
                } else {
                    timeString = now.toLocaleTimeString('fa-IR-u-nu-arab', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran' });
                    dateString = now.toLocaleDateString('fa-IR-u-nu-arab', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Tehran', calendar: 'persian' });
                }
            } catch (e) {
                console.warn("Intl.DateTimeFormat failed for user panel, using fallback for time/date: ", e);
                timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran' });
                const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                timeString = timeString.replace(/[0-9]/g, w => persianNumbers[+w]);
            }

            const liveTimeElement = document.getElementById('live-time-placeholder');
            const currentDateElement = document.getElementById('current-date-placeholder');

            if (liveTimeElement) liveTimeElement.innerText = timeString;
            if (currentDateElement) currentDateElement.innerText = dateString;
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateLiveTimeAndDate();
            setInterval(updateLiveTimeAndDate, 1000);

            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                if (document.readyState === 'complete') {
                    loadingOverlay.style.display = 'none';
                } else {
                    window.addEventListener('load', () => {
                        loadingOverlay.style.display = 'none';
                    });
                     setTimeout(() => {
                        if (loadingOverlay.style.display !== 'none') {
                             loadingOverlay.style.display = 'none';
                        }
                    }, 2000);
                }
            }
        });
    </script>
    <!-- User specific JS files or scripts -->
</body>
</html>
