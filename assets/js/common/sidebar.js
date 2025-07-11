// assets/js/common/sidebar.js
document.addEventListener('DOMContentLoaded', function () {
    const hamburgerMenu = document.getElementById('hamburger-menu');
    const sidebar = document.getElementById('sidebar');
    const closeSidebarBtn = document.getElementById('close-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const dashboardContainer = document.querySelector('.dashboard-container'); // Used for content push on desktop

    const mainContent = document.querySelector('.main-content');


    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scrolling of body when sidebar is open on mobile

        // On desktop, add class to dashboard-container to push content
        if (window.innerWidth > 992) {
            if(dashboardContainer) dashboardContainer.classList.add('sidebar-open');
        }
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
        document.body.style.overflow = ''; // Restore scrolling

        if (window.innerWidth > 992) {
            if(dashboardContainer) dashboardContainer.classList.remove('sidebar-open');
        }
    }

    if (hamburgerMenu) {
        hamburgerMenu.addEventListener('click', function (event) {
            event.stopPropagation(); // Prevent click from bubbling up to document
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', function () {
            closeSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            closeSidebar();
        });
    }

    // Optional: Close sidebar with Escape key
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    // Adjust sidebar behavior on resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            // Desktop view: if sidebar was open (mobile style), ensure content push is applied
            if (sidebar && sidebar.classList.contains('open')) {
                 if(dashboardContainer) dashboardContainer.classList.add('sidebar-open');
            }
            if (sidebarOverlay) sidebarOverlay.classList.remove('active'); // No overlay on desktop
            document.body.style.overflow = ''; // Ensure body scroll is enabled
        } else {
            // Mobile view: if sidebar is open, ensure overlay is active
            if (sidebar && sidebar.classList.contains('open')) {
                if (sidebarOverlay) sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
             if(dashboardContainer) dashboardContainer.classList.remove('sidebar-open'); // No content push on mobile
        }
    });

});
