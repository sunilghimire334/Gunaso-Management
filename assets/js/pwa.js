// PWA functionality
class PWAHandler {
    constructor() {
        this.deferredPrompt = null;
        this.init();
    }

    init() {
        this.registerServiceWorker();
        this.setupInstallPrompt();
        this.checkStandaloneMode();

        // Handle iOS Safari separately
        if (this.isIos() && !this.isInStandaloneMode()) {
            // Show iOS install popup after slight delay
            setTimeout(() => this.showIosInstallPopup(), 1500);
        }
    }

    // Register Service Worker
    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('Service Worker registered:', registration);
                    })
                    .catch(error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
    }

    // Handle install prompt (Android/Chrome)
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallprompt event fired');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallPromotion();
        });

        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed successfully!');
            this.hideInstallButton();
        });
    }

    // Show install button for Android/Chrome
    showInstallPromotion() {
        if (document.getElementById('installButton')) return;

        const installButton = document.createElement('button');
        installButton.id = 'installButton';
        installButton.innerHTML = '📱 Install App';
        installButton.className = 'pwa-install-btn';

        installButton.addEventListener('click', () => this.installApp());
        document.body.appendChild(installButton);
    }

    // Handle Android/Chrome PWA installation
    async installApp() {
        if (!this.deferredPrompt) {
            alert('Installation not available. Please use your browser menu to install.');
            return;
        }

        this.deferredPrompt.prompt();
        const { outcome } = await this.deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        this.deferredPrompt = null;
        this.hideInstallButton();
    }

    // Hide install button
    hideInstallButton() {
        const btn = document.getElementById('installButton');
        if (btn) btn.style.display = 'none';
    }

    // Detect iOS devices
    isIos() {
        return /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    }

    // Detect standalone (already installed)
    isInStandaloneMode() {
        return ('standalone' in window.navigator) && window.navigator.standalone;
    }

    // Show custom iOS install popup
    showIosInstallPopup() {
        if (document.getElementById('iosInstallPopup')) return;

        const popup = document.createElement('div');
        popup.id = 'iosInstallPopup';
        popup.className = 'ios-install-popup';
        popup.innerHTML = `
            <img src="/images/icon-192x192.png" alt="App Icon">
            <h3>Install Our App</h3>
            <p>To install this app on your iPhone or iPad:<br>
            Tap <strong>Share</strong> <span style="font-size:20px;">⬆️</span> 
            then choose <strong>Add to Home Screen</strong>.</p>
            <button id="closeIosPopup">Got it</button>
        `;
        document.body.appendChild(popup);

        // Close popup on button click
        document.getElementById('closeIosPopup').addEventListener('click', () => {
            popup.remove();
        });
    }

    // Check standalone mode and hide button if installed
    checkStandaloneMode() {
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('Running in standalone mode');
            this.hideInstallButton();
        }
    }
}

// Initialize PWA functionality
document.addEventListener('DOMContentLoaded', () => {
    window.pwaHandler = new PWAHandler();
});