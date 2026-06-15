/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (window, document, CopyMyPage) {
    'use strict';

    class AlertBarController {
        constructor(options = {}) {
            this.selector = options.selector || '[data-cmp-alert-bar]';
            this.slotSelector = options.slotSelector || '.cmp-alert-slot';
            this.storagePrefix = options.storagePrefix || 'cmpAlert:';
            this.root = document.documentElement;
            this.resizeObserver = null;
            this._resizeFrame = null;
            this._initialized = false;
            this._globalEventsBound = false;
            this._lastOffset = null;
            this._handleResize = this._handleResize.bind(this);
            this._handleJoomlaUpdated = this._handleJoomlaUpdated.bind(this);
        }

        init() {
            if (this._initialized) {
                this.refresh();
                return;
            }

            this._initialized = true;
            this._initAlerts();
            this._bindResizeObserver();
            this._bindGlobalEvents();
            this.refresh();
        }

        destroy() {
            window.cancelAnimationFrame(this._resizeFrame);
            this._resizeFrame = null;

            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }

            if (this._globalEventsBound) {
                window.removeEventListener('resize', this._handleResize);
                window.removeEventListener('load', this._handleResize);
                document.removeEventListener('joomla:updated', this._handleJoomlaUpdated);
                this._globalEventsBound = false;
            }

            this.root.style.setProperty('--cmp-alert-offset', '0px');
            this._syncStickyOffset(0);
            this.root.style.setProperty('--cmp-hero-viewport-height', '100svh');
            this._lastOffset = null;

            if (document.body) {
                document.body.classList.remove('is-alert-active');
            }

            this._initialized = false;
        }

        refresh() {
            this._scheduleOffsetSync();
        }

        _initAlerts() {
            document.querySelectorAll(this.selector).forEach((alert) => this._initAlert(alert));
        }

        _initAlert(alert) {
            if (alert.dataset.cmpAlertInitialized === '1') {
                return;
            }

            alert.dataset.cmpAlertInitialized = '1';

            if (this._wasDismissed(alert)) {
                this._removeAlert(alert);
                return;
            }

            alert.addEventListener('beforehide', () => {
                this._persistDismissal(alert);
                this._scheduleOffsetSync();
            });

            alert.addEventListener('hide', () => {
                this._persistDismissal(alert);
                this._scheduleOffsetSync();
            });

            alert.addEventListener('hidden', () => {
                this._scheduleOffsetSync();
            });

            const close = alert.querySelector('.uk-alert-close');

            if (!close) {
                return;
            }

            close.addEventListener('click', (event) => {
                this._persistDismissal(alert);

                if (!window.UIkit) {
                    event.preventDefault();
                    this._removeAlert(alert);
                }

                this._scheduleOffsetSync();
            });
        }

        _bindResizeObserver() {
            if (typeof window.ResizeObserver !== 'function') {
                return;
            }

            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
            }

            this.resizeObserver = new window.ResizeObserver(this._handleResize);
            document.querySelectorAll(this.slotSelector).forEach((slot) => {
                this.resizeObserver.observe(slot);
            });
        }

        _bindGlobalEvents() {
            if (this._globalEventsBound) {
                return;
            }

            window.addEventListener('resize', this._handleResize, { passive: true });
            window.addEventListener('load', this._handleResize, { once: true });
            document.addEventListener('joomla:updated', this._handleJoomlaUpdated);
            this._globalEventsBound = true;
        }

        _handleResize() {
            this._scheduleOffsetSync();
        }

        _handleJoomlaUpdated() {
            this._initAlerts();
            this._bindResizeObserver();
            this._scheduleOffsetSync();
        }

        _scheduleOffsetSync() {
            if (this._resizeFrame !== null) {
                return;
            }

            this._resizeFrame = window.requestAnimationFrame(() => {
                this._resizeFrame = null;
                this._syncOffset();
            });
        }

        _syncOffset() {
            const slot = this._getActiveSlot();
            const offset = slot ? Math.ceil(slot.getBoundingClientRect().height) : 0;
            const previousOffset = this._lastOffset;

            this.root.style.setProperty('--cmp-alert-offset', `${offset}px`);
            this._syncStickyOffset(offset);
            this._lastOffset = offset;

            if (!document.body) {
                return;
            }

            document.body.classList.toggle('is-alert-active', offset > 0);

            if (previousOffset !== offset) {
                this._dispatchOffsetChange(offset, previousOffset);
            }

            if (slot) {
                slot.classList.remove('is-dismissed');
                return;
            }

            document.querySelectorAll(this.slotSelector).forEach((element) => {
                element.classList.add('is-dismissed');
            });
        }

        _dispatchOffsetChange(offset, previousOffset) {
            document.dispatchEvent(new window.CustomEvent('copymypage:alert-offset-change', {
                detail: {
                    offset,
                    previousOffset,
                },
            }));
        }

        _syncStickyOffset(alertOffset) {
            const rootStyles = window.getComputedStyle(this.root);
            const headerOffset = Number.parseFloat(rootStyles.getPropertyValue('--cmp-header-offset')) || 0;
            const stickyOffset = Math.ceil(headerOffset + alertOffset);
            const heroViewportHeight = Math.max(0, Math.ceil(window.innerHeight - alertOffset));

            this.root.style.setProperty('--cmp-sticky-offset', `${stickyOffset}px`);
            this.root.style.setProperty('--cmp-hero-viewport-height', `${heroViewportHeight}px`);
        }

        _getActiveSlot() {
            const alerts = [...document.querySelectorAll(this.selector)];

            for (const alert of alerts) {
                if (alert.hidden || alert.getAttribute('aria-hidden') === 'true' || !alert.isConnected) {
                    continue;
                }

                const slot = alert.closest(this.slotSelector);

                if (slot && slot.getClientRects().length > 0) {
                    return slot;
                }
            }

            return null;
        }

        _getDismissKey(alert) {
            return String(alert.dataset.cmpAlertKey || '').trim();
        }

        _getDismissMode(alert) {
            const mode = String(alert.dataset.cmpAlertDismiss || 'none').trim().toLowerCase();

            return ['session', 'cookie'].includes(mode) ? mode : 'none';
        }

        _getCookieName(key) {
            return `cmp_alert_${this._hash(key)}`;
        }

        _readSession(key) {
            try {
                return window.sessionStorage.getItem(this.storagePrefix + key) === '1';
            } catch (error) {
                return false;
            }
        }

        _writeSession(key) {
            try {
                window.sessionStorage.setItem(this.storagePrefix + key, '1');
            } catch (error) {
                // Storage can be disabled; the alert still closes for the current page view.
            }
        }

        _readCookie(key) {
            const name = `${this._getCookieName(key)}=`;

            return document.cookie.split(';').some((cookie) => cookie.trim().indexOf(name) === 0);
        }

        _writeCookie(key, days) {
            const lifetime = Number.parseInt(days, 10);
            const maxAge = Math.max(1, Number.isNaN(lifetime) ? 7 : lifetime) * 86400;

            document.cookie = `${this._getCookieName(key)}=1; path=/; max-age=${maxAge}; samesite=lax`;
        }

        _wasDismissed(alert) {
            const key = this._getDismissKey(alert);
            const mode = this._getDismissMode(alert);

            if (!key || mode === 'none') {
                return false;
            }

            return mode === 'session' ? this._readSession(key) : this._readCookie(key);
        }

        _persistDismissal(alert) {
            const key = this._getDismissKey(alert);
            const mode = this._getDismissMode(alert);

            if (!key || mode === 'none') {
                return;
            }

            if (mode === 'session') {
                this._writeSession(key);
                return;
            }

            this._writeCookie(key, alert.dataset.cmpAlertCookieDays || '7');
        }

        _removeAlert(alert) {
            alert.hidden = true;
            alert.setAttribute('aria-hidden', 'true');

            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }

            this._scheduleOffsetSync();
        }

        _hash(value) {
            let result = 0;
            const input = String(value || '');

            for (let i = 0; i < input.length; i += 1) {
                result = ((result << 5) - result) + input.charCodeAt(i);
                result |= 0;
            }

            return Math.abs(result).toString(36);
        }
    }

    const init = () => {
        if (CopyMyPage.alertBarController instanceof AlertBarController) {
            CopyMyPage.alertBarController.destroy();
        }

        CopyMyPage.alertBarController = new AlertBarController();
        CopyMyPage.alertBarController.init();
    };

    CopyMyPage.AlertBarController = AlertBarController;
    CopyMyPage.initAlertBars = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})(window, document, window.CopyMyPage);
