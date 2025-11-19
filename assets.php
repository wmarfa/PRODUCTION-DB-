<?php
// assets.php - Local asset management for offline LAN deployment

class AssetManager {
    private $version = '1.0.0';

    public function __construct() {
        $this->ensureAssetDirectories();
    }

    /**
     * Create necessary asset directories
     */
    private function ensureAssetDirectories() {
        $directories = [
            'assets/css',
            'assets/js',
            'assets/fonts',
            'assets/images'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Get inline CSS for offline use
     */
    public function getInlineCSS() {
        return '
        /* Bootstrap 5.3.3 Core CSS - Offline Version */
        :root {
            --bs-blue: #0d6efd;
            --bs-indigo: #6610f2;
            --bs-purple: #6f42c1;
            --bs-pink: #d63384;
            --bs-red: #dc3545;
            --bs-orange: #fd7e14;
            --bs-yellow: #ffc107;
            --bs-green: #198754;
            --bs-teal: #20c997;
            --bs-cyan: #0dcaf0;
            --bs-white: #ffffff;
            --bs-gray: #6c757d;
            --bs-gray-dark: #343a40;
            --bs-gray-100: #f8f9fa;
            --bs-gray-200: #e9ecef;
            --bs-gray-300: #dee2e6;
            --bs-gray-400: #ced4da;
            --bs-gray-500: #adb5bd;
            --bs-gray-600: #6c757d;
            --bs-gray-700: #495057;
            --bs-gray-800: #343a40;
            --bs-gray-900: #212529;
            --bs-primary: #0d6efd;
            --bs-secondary: #6c757d;
            --bs-success: #198754;
            --bs-info: #0dcaf0;
            --bs-warning: #ffc107;
            --bs-danger: #dc3545;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
            --bs-font-sans-serif: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif;
            --bs-font-monospace: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            --bs-gradient: linear-gradient(180deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0));
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--bs-font-sans-serif);
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--bs-body-color);
            background-color: var(--bs-body-bg);
            -webkit-text-size-adjust: 100%;
            -webkit-tap-highlight-color: transparent;
        }

        .container, .container-fluid, .container-xxl, .container-xl, .container-lg, .container-md, .container-sm {
            width: 100%;
            padding-right: var(--bs-gutter-x, 0.75rem);
            padding-left: var(--bs-gutter-x, 0.75rem);
            margin-right: auto;
            margin-left: auto;
        }

        .row {
            --bs-gutter-x: 1.5rem;
            --bs-gutter-y: 0;
            display: flex;
            flex-wrap: wrap;
            margin-top: calc(-1 * var(--bs-gutter-y));
            margin-right: calc(-0.5 * var(--bs-gutter-x));
            margin-left: calc(-0.5 * var(--bs-gutter-x));
        }

        .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
            flex-shrink: 0;
            width: 100%;
            padding-right: calc(var(--bs-gutter-x) * 0.5);
            padding-left: calc(var(--bs-gutter-x) * 0.5);
            margin-top: var(--bs-gutter-y);
        }

        .col-1 { flex: 0 0 auto; width: 8.33333333%; }
        .col-2 { flex: 0 0 auto; width: 16.66666667%; }
        .col-3 { flex: 0 0 auto; width: 25%; }
        .col-4 { flex: 0 0 auto; width: 33.33333333%; }
        .col-6 { flex: 0 0 auto; width: 50%; }
        .col-8 { flex: 0 0 auto; width: 66.66666667%; }
        .col-9 { flex: 0 0 auto; width: 75%; }
        .col-12 { flex: 0 0 auto; width: 100%; }

        @media (min-width: 576px) {
            .container-sm, .container {
                max-width: 540px;
            }
        }

        @media (min-width: 768px) {
            .container-md, .container-sm, .container {
                max-width: 720px;
            }
            .col-md-3 { flex: 0 0 auto; width: 25%; }
            .col-md-4 { flex: 0 0 auto; width: 33.33333333%; }
            .col-md-5 { flex: 0 0 auto; width: 41.66666667%; }
            .col-md-6 { flex: 0 0 auto; width: 50%; }
            .col-md-7 { flex: 0 0 auto; width: 58.33333333%; }
            .col-md-8 { flex: 0 0 auto; width: 66.66666667%; }
            .col-md-9 { flex: 0 0 auto; width: 75%; }
            .col-md-12 { flex: 0 0 auto; width: 100%; }
        }

        @media (min-width: 992px) {
            .container-lg, .container-md, .container-sm, .container {
                max-width: 960px;
            }
            .col-lg-3 { flex: 0 0 auto; width: 25%; }
            .col-lg-4 { flex: 0 0 auto; width: 33.33333333%; }
            .col-lg-6 { flex: 0 0 auto; width: 50%; }
            .col-lg-8 { flex: 0 0 auto; width: 66.66666667%; }
            .col-lg-9 { flex: 0 0 auto; width: 75%; }
            .col-lg-12 { flex: 0 0 auto; width: 100%; }
        }

        @media (min-width: 1200px) {
            .container-xl, .container-lg, .container-md, .container-sm, .container {
                max-width: 1140px;
            }
        }

        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: 0.375rem;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 1rem 1rem;
        }

        .card-header {
            padding: 0.5rem 1rem;
            margin-bottom: 0;
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-top-left-radius: calc(0.375rem - 1px);
            border-top-right-radius: calc(0.375rem - 1px);
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border-radius: 0.375rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn-primary {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }

        .btn-warning {
            color: #000;
            background-color: #ffc107;
            border-color: #ffc107;
        }

        .btn-danger {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-info {
            color: #000;
            background-color: #0dcaf0;
            border-color: #0dcaf0;
        }

        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1.25rem;
            border-radius: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            vertical-align: top;
            border-color: #dee2e6;
        }

        .table > :not(caption) > * > * {
            padding: 0.5rem 0.5rem;
            background-color: var(--bs-table-bg);
            border-bottom-width: 1px;
            box-shadow: inset 0 0 0 9999px var(--bs-table-accent-bg);
        }

        .table > thead {
            vertical-align: bottom;
        }

        .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
        }

        .alert-success {
            color: #0f5132;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }

        .alert-info {
            color: #055160;
            background-color: #cff4fc;
            border-color: #b6effb;
        }

        .alert-warning {
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
        }

        .alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }

        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }

        .bg-primary { background-color: #0d6efd !important; }
        .bg-secondary { background-color: #6c757d !important; }
        .bg-success { background-color: #198754 !important; }
        .bg-info { background-color: #0dcaf0 !important; }
        .bg-warning { background-color: #ffc107 !important; }
        .bg-danger { background-color: #dc3545 !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .bg-dark { background-color: #212529 !important; }

        .text-primary { color: #0d6efd !important; }
        .text-secondary { color: #6c757d !important; }
        .text-success { color: #198754 !important; }
        .text-info { color: #0dcaf0 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }
        .text-light { color: #f8f9fa !important; }
        .text-dark { color: #212529 !important; }
        .text-muted { color: #6c757d !important; }

        .d-flex { display: flex !important; }
        .d-block { display: block !important; }
        .d-none { display: none !important; }
        .d-grid { display: grid !important; }
        .text-center { text-align: center !important; }
        .text-start { text-align: left !important; }
        .text-end { text-align: right !important; }

        .justify-content-between { justify-content: space-between !important; }
        .justify-content-end { justify-content: flex-end !important; }
        .justify-content-center { justify-content: center !important; }
        .align-items-center { align-items: center !important; }
        .align-items-start { align-items: flex-start !important; }

        .mt-1, .my-1 { margin-top: 0.25rem !important; }
        .mt-2, .my-2 { margin-top: 0.5rem !important; }
        .mt-3, .my-3 { margin-top: 1rem !important; }
        .mt-4, .my-4 { margin-top: 1.5rem !important; }
        .mt-5, .my-5 { margin-top: 3rem !important; }

        .mb-1, .my-1 { margin-bottom: 0.25rem !important; }
        .mb-2, .my-2 { margin-bottom: 0.5rem !important; }
        .mb-3, .my-3 { margin-bottom: 1rem !important; }
        .mb-4, .my-4 { margin-bottom: 1.5rem !important; }
        .mb-5, .my-5 { margin-bottom: 3rem !important; }

        .ms-2, .mx-2 { margin-left: 0.5rem !important; }
        .me-2, .mx-2 { margin-right: 0.5rem !important; }
        .ms-3, .mx-3 { margin-left: 1rem !important; }
        .me-3, .mx-3 { margin-right: 1rem !important; }

        .p-1 { padding: 0.25rem !important; }
        .p-2 { padding: 0.5rem !important; }
        .p-3 { padding: 1rem !important; }
        .p-4 { padding: 1.5rem !important; }
        .p-5 { padding: 3rem !important; }

        .pt-3, .py-3 { padding-top: 1rem !important; }
        .pb-3, .py-3 { padding-bottom: 1rem !important; }

        .position-relative { position: relative !important; }
        .position-absolute { position: absolute !important; }

        .w-100 { width: 100% !important; }
        .h-100 { height: 100% !important; }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .progress {
            display: flex;
            height: 1rem;
            overflow: hidden;
            font-size: 0.75rem;
            background-color: #e9ecef;
            border-radius: 0.375rem;
        }

        .progress-bar {
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            background-color: #0d6efd;
            transition: width 0.6s ease;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1055;
            display: none;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            outline: 0;
        }

        .modal-dialog {
            position: relative;
            width: auto;
            margin: 0.5rem;
            pointer-events: none;
        }

        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out;
            transform: translate(0, -50px);
        }

        .modal.show .modal-dialog {
            transform: none;
        }

        .modal-dialog-scrollable {
            height: calc(100% - 1rem);
        }

        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }

        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 0.5rem;
            outline: 0;
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            width: 100vw;
            height: 100vh;
            background-color: #000;
        }

        .modal-backdrop.fade {
            opacity: 0;
        }

        .modal-backdrop.show {
            opacity: 0.5;
        }

        .modal-header {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1rem;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: calc(0.5rem - 1px);
            border-top-right-radius: calc(0.5rem - 1px);
        }

        .modal-title {
            margin-bottom: 0;
            line-height: 1.5;
        }

        .modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem;
        }

        .modal-footer {
            display: flex;
            flex-wrap: wrap;
            flex-shrink: 0;
            align-items: center;
            justify-content: flex-end;
            padding: 0.75rem;
            border-top: 1px solid #dee2e6;
            border-bottom-right-radius: calc(0.5rem - 1px);
            border-bottom-left-radius: calc(0.5rem - 1px);
        }

        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 500px;
                margin: 1.75rem auto;
            }
        }

        @media (min-width: 992px) {
            .modal-lg,
            .modal-xl {
                max-width: 800px;
            }
        }

        @media (min-width: 1200px) {
            .modal-xl {
                max-width: 1140px;
            }
        }

        .btn-close {
            box-sizing: content-box;
            width: 1em;
            height: 1em;
            padding: 0.25em 0.25em;
            color: #000;
            background: transparent url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'m.235.867 8.832 8.832-8.832 8.832a.5.5 0 0 0 .707.707L9.774 10.4l8.832 8.832a.5.5 0 0 0 .707-.707L10.481 9.774l8.832-8.832a.5.5 0 0 0-.707-.707L9.774 9.067.942.235a.5.5 0 0 0-.707.632z\'/%3e%3c/svg%3e") center/1em auto no-repeat;
            border: 0;
            border-radius: 0.375rem;
            opacity: 0.5;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            background-image: none;
            border: 1px solid #ced4da;
            appearance: none;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: #212529;
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .form-label {
            margin-bottom: 0.5rem;
        }

        .form-text {
            margin-top: 0.375rem;
            font-size: 0.875em;
            color: #6c757d;
        }

        .mb-0 { margin-bottom: 0 !important; }
        .mt-0 { margin-top: 0 !important; }

        /* Custom Production System Styles */
        .production-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }

        .metric-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .metric-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .line-status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }

        .status-running { background-color: #28a745; }
        .status-idle { background-color: #ffc107; }
        .status-down { background-color: #dc3545; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .offline-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #198754;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            z-index: 9999;
        }

        .no-connection {
            background: #dc3545;
        }
        ';
    }

    /**
     * Get inline JavaScript for offline use
     */
    public function getInlineJS() {
        return '
        /* Production System JavaScript - Offline Version */

        // Simple modal implementation (replacing Bootstrap modal)
        class SimpleModal {
            constructor(elementId) {
                this.element = document.getElementById(elementId);
                this.isOpen = false;
                this.setupEventListeners();
            }

            setupEventListeners() {
                // Close buttons
                const closeButtons = this.element.querySelectorAll("[data-dismiss=\'modal\']");
                closeButtons.forEach(btn => {
                    btn.addEventListener("click", () => this.hide());
                });

                // Click outside to close
                this.element.addEventListener("click", (e) => {
                    if (e.target === this.element) {
                        this.hide();
                    }
                });

                // ESC key to close
                document.addEventListener("keydown", (e) => {
                    if (e.key === "Escape" && this.isOpen) {
                        this.hide();
                    }
                });
            }

            show() {
                this.element.style.display = "block";
                setTimeout(() => {
                    this.element.classList.add("show");
                }, 10);
                document.body.classList.add("modal-open");
                this.isOpen = true;
            }

            hide() {
                this.element.classList.remove("show");
                setTimeout(() => {
                    this.element.style.display = "none";
                }, 300);
                document.body.classList.remove("modal-open");
                this.isOpen = false;
            }
        }

        // Auto-refresh functionality
        class AutoRefresh {
            constructor(interval = 30000) {
                this.interval = interval;
                this.isRunning = false;
                this.timer = null;
            }

            start(callback) {
                if (this.isRunning) return;

                this.isRunning = true;
                this.timer = setInterval(callback, this.interval);
            }

            stop() {
                if (!this.isRunning) return;

                this.isRunning = false;
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            }
        }

        // Offline status indicator
        class OfflineStatus {
            constructor() {
                this.indicator = document.createElement("div");
                this.indicator.className = "offline-indicator";
                this.indicator.innerHTML = "✓ Online - Local Network";
                document.body.appendChild(this.indicator);

                this.setupEventListeners();
            }

            setupEventListeners() {
                // Monitor network status
                window.addEventListener("online", () => {
                    this.updateStatus(true);
                });

                window.addEventListener("offline", () => {
                    this.updateStatus(false);
                });

                // Initial status
                this.updateStatus(navigator.onLine);
            }

            updateStatus(isOnline) {
                if (isOnline) {
                    this.indicator.className = "offline-indicator";
                    this.indicator.innerHTML = "✓ Online - Local Network";
                } else {
                    this.indicator.className = "offline-indicator no-connection";
                    this.indicator.innerHTML = "✗ Offline Mode";
                }
            }
        }

        // Initialize components when DOM is ready
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize offline status indicator
            const offlineStatus = new OfflineStatus();

            // Initialize modals
            const modals = {};
            document.querySelectorAll(".modal").forEach(modal => {
                const id = modal.id;
                if (id) {
                    modals[id] = new SimpleModal(id);

                    // Setup trigger buttons
                    document.querySelectorAll(`[data-target="#${id}"]`).forEach(trigger => {
                        trigger.addEventListener("click", () => {
                            modals[id].show();
                        });
                    });
                }
            });

            // Make modals globally accessible
            window.modals = modals;

            // Auto-refresh setup
            const autoRefresh = new AutoRefresh(30000);

            // Setup manual refresh buttons
            document.querySelectorAll("[onclick*=\'refresh\']").forEach(btn => {
                btn.addEventListener("click", function() {
                    // Clear previous refresh state
                    this.innerHTML = \'<i class="fa fa-spinner fa-spin me-2"></i>Refreshing...\';

                    // Simulate refresh (in real app, this would fetch new data)
                    setTimeout(() => {
                        this.innerHTML = \'<i class="fas fa-sync me-2"></i>Refresh\';
                        location.reload();
                    }, 1000);
                });
            });

            // Format time displays
            function updateTimeDisplays() {
                const timeElements = document.querySelectorAll(".current-time");
                const now = new Date();
                const timeString = now.toLocaleTimeString("en-US", {
                    hour12: false,
                    hour: "2-digit",
                    minute: "2-digit"
                });

                timeElements.forEach(element => {
                    element.textContent = timeString;
                });
            }

            // Update time every second
            setInterval(updateTimeDisplays, 1000);
            updateTimeDisplays();

            // Show offline notice
            if (!navigator.onLine) {
                offlineStatus.updateStatus(false);
            }

            console.log("Production System initialized in offline mode");
        });

        // Simple fetch wrapper for offline compatibility
        window.safeFetch = function(url, options = {}) {
            // For local requests, use regular fetch
            if (url.startsWith(window.location.origin) || url.startsWith("/") || !url.startsWith("http")) {
                return fetch(url, options);
            }

            // For external requests, return error (offline mode)
            return Promise.reject(new Error("External requests not available in offline mode"));
        };
        ';
    }

    /**
     * Get Font Awesome icons as inline SVG (offline version)
     */
    public function getInlineIcons() {
        return '
        <svg style="display: none;">
            <symbol id="fa-home" viewBox="0 0 576 512">
                <path fill="currentColor" d="M280.37 148.26L96 300.11V464a16 16 0 0 0 16 16l112.06-.29a16 16 0 0 0 15.92-16V368a16 16 0 0 1 16-16h64a16 16 0 0 1 16 16v95.64a16 16 0 0 0 16 16.05L464 480a16 16 0 0 0 16-16V300L295.67 148.26a12.19 12.19 0 0 0-15.3 0zM571.6 251.47L488 182.56V44.05a12 12 0 0 0-12-12h-56a12 12 0 0 0-12 12v72.61L318.47 43a48 48 0 0 0-61 0L4.34 251.47a12 12 0 0 0-1.6 16.9l25.5 31A12 12 0 0 0 45.15 301l235.22-193.74a12.19 12.19 0 0 1 15.3 0L530.9 301a12 12 0 0 0 16.9-1.6l25.5-31a12 12 0 0 0-1.7-16.93z"/>
            </symbol>
            <symbol id="fa-tachometer-alt" viewBox="0 0 640 512">
                <path fill="currentColor" d="M320 384c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm271.39 182.06c-2.66 0-5.31-.81-7.58-2.48l-23.41-17.44c-7.72-5.75-9.3-16.64-3.55-24.36 5.75-7.72 16.64-9.31 24.36-3.55l23.41 17.44c7.72 5.75 9.3 16.64 3.55 24.36-3.42 4.58-8.69 7.03-14.03 7.03zm-554.78 0c-5.35 0-10.61-2.45-14.03-7.03-5.75-7.72-4.17-18.61 3.55-24.36l23.41-17.44c7.72-5.75 18.61-4.17 24.36 3.55 5.75 7.72 4.17 18.61-3.55 24.36l-23.41 17.44c-2.28 1.66-4.93 2.48-7.58 2.48zm398.84-134.06c-5.35 0-10.61-2.45-14.03-7.03-5.75-7.72-4.17-18.61 3.55-24.36l23.41-17.44c7.72-5.75 18.61-4.17 24.36 3.55 5.75 7.72 4.17 18.61-3.55 24.36l-23.41 17.44c-2.28 1.66-4.93 2.48-7.58 2.48zm-242.9 0c-5.35 0-10.61-2.45-14.03-7.03-5.75-7.72-4.17-18.61 3.55-24.36l23.41-17.44c7.72-5.75 18.61-4.17 24.36 3.55 5.75 7.72 4.17 18.61-3.55 24.36l-23.41 17.44c-2.28 1.66-4.93 2.48-7.58 2.48zm208-192c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm-173.05 0c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm-70.95 96c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm0 128c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm64-64c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm128 0c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm-64-192c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm0 128c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32zm64-64c-17.67 0-32 14.33-32 32s14.33 32 32 32 32-14.33 32-32-14.33-32-32-32z"/>
            </symbol>
            <symbol id="fa-sync" viewBox="0 0 512 512">
                <path fill="currentColor" d="M440.65 12.57l4 24a8 8 0 0 0 10.25 5.72l-24-4a8 8 0 0 0-10.25 5.72l-.13.73L403.43 87.6a8 8 0 0 0 3.85 10.63l16.57 8.83a8 8 0 0 0 10.65-4.86l-8.14-19.33a207 207 0 0 1 68.31 221.71 8 8 0 0 0 15.41-4.39 223 223 0 0 0-73.42-239zm-352.61 232A176 176 0 1 0 336 352a8 8 0 0 0 0-16 160 160 0 1 1-160-160 8 8 0 0 0-16 0 176 176 0 0 0 175.99 175.99z"/>
            </symbol>
            <symbol id="fa-bell" viewBox="0 0 448 512">
                <path fill="currentColor" d="M224 480c-17.66 0-32-14.34-32-32h64c0 17.66-14.34 32-32 32zm208-240c0 106-86 192-192 192S48 346 48 240c0-26.5 5.41-51.78 15.23-74.76L64 64c0-17.67 14.33-32 32-32h256c17.67 0 32 14.33 32 32l.77 101.24C394.59 186.22 400 213.5 400 240z"/>
            </symbol>
            <symbol id="fa-industry" viewBox="0 0 640 512">
                <path fill="currentColor" d="M633.82 458.18L511.92 332.33a3.99 3.99 0 0 0-5.87-.03L352 490.13a3.99 3.99 0 0 0 .09 5.69l121.81 122.04c3.78 3.79 10.5 3.04 13.19-1.57 13.58-21.21 21.36-46.23 21.46-73.13a8 8 0 0 0-2.56-5.89zM197.33 351.73l121.81-122.04a3.99 3.99 0 0 0-.09-5.69L196.14 101.84a3.99 3.99 0 0 0-5.82.04L68.5 227.74a8 8 0 0 0-2.56 5.89c.1 26.9 7.88 51.92 21.46 73.13 2.69 4.61 9.41 5.36 13.19 1.57l96.74-56.6z"/>
            </symbol>
            <symbol id="fa-chart-line" viewBox="0 0 512 512">
                <path fill="currentColor" d="M496 384H192c-8.84 0-16-7.16-16-16V64c0-8.84 7.16-16 16-16h304c8.84 0 16 7.16 16 16v304c0 8.84-7.16 16-16 16zm-384-64H16c-8.84 0-16-7.16-16-16V16C0 7.16 7.16 0 16 0h96c8.84 0 16 7.16 16 16v288c0 8.84-7.16 16-16 16zm416 128H16c-8.84 0-16-7.16-16-16v-64c0-8.84 7.16-16 16-16h512c8.84 0 16 7.16 16 16v64c0 8.84-7.16 16-16 16z"/>
            </symbol>
        </svg>
        ';
    }

    /**
     * Generate HTML header with all assets inline
     */
    public function generateHTMLHeader($title = "Production Management System") {
        $css = $this->getInlineCSS();
        $js = $this->getInlineJS();
        $icons = $this->getInlineIcons();

        return "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <title>{$title}</title>
            <style>
                {$css}
            </style>
            {$icons}
        </head>
        <body>
        <script>
            {$js}
        </script>
        ";
    }

    /**
     * Generate HTML footer
     */
    public function generateHTMLFooter() {
        return "
        </body>
        </html>
        ";
    }

    /**
     * Get offline-compatible font display
     */
    public function getOfflineFontCSS() {
        return '
        /* Local font fallbacks for offline use */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .fa {
            display: inline-block;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            line-height: 1;
        }

        .fa-home:before { content: "🏠"; }
        .fa-tachometer-alt:before { content: "📊"; }
        .fa-sync:before { content: "🔄"; }
        .fa-bell:before { content: "🔔"; }
        .fa-industry:before { content: "🏭"; }
        .fa-chart-line:before { content: "📈"; }
        .fa-cogs:before { content: "⚙️"; }
        .fa-calendar-alt:before { content: "📅"; }
        .fa-plus:before { content: "➕"; }
        .fa-lightbulb:before { content: "💡"; }
        .fa-exclamation-triangle:before { content: "⚠️"; }
        .fa-check-circle:before { content: "✅"; }
        .fa-times:before { content: "❌"; }
        .fa-spinner:before { content: "⏳"; }
        .fa-clock:before { content: "🕐"; }
        .fa-user:before { content: "👤"; }
        .fa-users:before { content: "👥"; }
        .fa-edit:before { content: "✏️"; }
        .fa-trash:before { content: "🗑️"; }
        .fa-search:before { content: "🔍"; }
        ';
    }
}

// Global asset manager instance
$GLOBALS['asset_manager'] = new AssetManager();
?>