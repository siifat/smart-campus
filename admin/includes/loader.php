<!-- Enhanced Loading Screen for Admin Panel -->
<div id="pageLoader" class="page-loader">
    <div class="loader-content">
        <div class="loader-logo">
            <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading Dashboard...</div>
        <div class="loader-progress">
            <div class="loader-progress-bar"></div>
        </div>
    </div>
</div>

<style>
.page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f68b1f 0%, #fbbf24 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 99999;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}

.page-loader.fade-out {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.loader-content {
    text-align: center;
}

.loader-logo {
    width: 90px;
    height: 90px;
    background: white;
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
    animation: logoFloat 3s ease-in-out infinite;
}

.loader-logo i {
    font-size: 45px;
    color: #f68b1f;
}

.loader-spinner {
    width: 70px;
    height: 70px;
    border: 5px solid rgba(255, 255, 255, 0.2);
    border-top-color: white;
    border-radius: 50%;
    margin: 0 auto 25px;
    animation: spin 1s linear infinite;
}

.loader-text {
    color: white;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 20px;
    animation: pulse 2s ease-in-out infinite;
}

.loader-progress {
    width: 200px;
    height: 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    margin: 0 auto;
    overflow: hidden;
}

.loader-progress-bar {
    height: 100%;
    background: white;
    border-radius: 10px;
    animation: progress 2s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

@keyframes progress {
    0% { width: 0%; }
    50% { width: 70%; }
    100% { width: 100%; }
}
</style>

<script>
// Hide loader when page is fully loaded
window.addEventListener('load', function() {
    setTimeout(function() {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(function() {
                loader.style.display = 'none';
            }, 500);
        }
    }, 800);
});

// Show loader on page navigation
document.addEventListener('DOMContentLoaded', function() {
    // Add loader to all internal links
    const links = document.querySelectorAll('a[href]:not([target="_blank"]):not([href^="#"]):not([href^="javascript"])');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href.includes('.php')) {
                const loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.style.display = 'flex';
                    loader.classList.remove('fade-out');
                }
            }
        });
    });
});
</script>
