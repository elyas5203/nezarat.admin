<?php // admin/includes/footer.php ?>
            </main>
            <footer class="main-footer-bottom">
                <p>تمامی حقوق برای سامانه مدیریت دبستان محفوظ است. &copy; <?php echo to_jalali(date('Y-m-d'), 'yyyy'); ?> | نسخه 0.1 آلفا</p>
            </footer>
        </div> <!-- End main-content -->
    </div> <!-- End dashboard-container -->

    <script>
        function updateLiveTimeAndDate() {
            const now = new Date();
            // Intl.DateTimeFormat options for Persian calendar and Arabic-Indic numerals
            const optionsTime = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran', numberingSystem: 'arab' };
            const optionsDate = { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Tehran', calendar: 'persian', numberingSystem: 'arab' };

            let timeString = 'hh:mm:ss';
            let dateString = 'درحال بارگذاری...';

            try {
                if (typeof Intl !== 'undefined' && typeof Intl.DateTimeFormat !== 'undefined') {
                    timeString = new Intl.DateTimeFormat('fa-IR', optionsTime).format(now);
                    dateString = new Intl.DateTimeFormat('fa-IR', optionsDate).format(now);
                } else {
                    // Fallback for very old browsers or environments missing Intl
                    timeString = now.toLocaleTimeString('fa-IR-u-nu-arab', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran' });
                    dateString = now.toLocaleDateString('fa-IR-u-nu-arab', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Asia/Tehran', calendar: 'persian' });
                }
            } catch (e) {
                console.warn("Intl.DateTimeFormat failed, using fallback for time/date: ", e);
                 // Simplified fallback if fa-IR with numberingSystem fails
                timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran' });
                const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                timeString = timeString.replace(/[0-9]/g, w => persianNumbers[+w]);
                // Date fallback is harder without a library, PHP initial value will remain for date.
            }

            const liveTimeElement = document.getElementById('live-time-placeholder');
            const currentDateElement = document.getElementById('current-date-placeholder');

            if (liveTimeElement) liveTimeElement.innerText = timeString;
            if (currentDateElement) currentDateElement.innerText = dateString;
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateLiveTimeAndDate(); // Initial call
            setInterval(updateLiveTimeAndDate, 1000); // Update every second

            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                // More robust way to hide loader: ensure it hides even if 'load' is already fired
                if (document.readyState === 'complete') {
                    loadingOverlay.style.display = 'none';
                } else {
                    window.addEventListener('load', () => {
                        loadingOverlay.style.display = 'none';
                    });
                    // Fallback if load event takes too long
                    setTimeout(() => {
                        if (loadingOverlay.style.display !== 'none') {
                             loadingOverlay.style.display = 'none';
                        }
                    }, 2000);
                }
            }
        });
    </script>
    <!-- Page specific scripts can be added here by pages including this footer -->
</body>
</html>
