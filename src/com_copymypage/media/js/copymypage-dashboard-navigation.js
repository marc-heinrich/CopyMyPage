/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.22
 */

/**
 * Dashboard account navigation: native horizontal scroll and measured sticky offset.
 */
(function (window, document) {
    'use strict';

    if (window.CopyMyPageDashboardNavigationInitialized) {
        return;
    }

    window.CopyMyPageDashboardNavigationInitialized = true;

    const controllers = new Map();
    const selector = '[data-cmp-dashboard-nav]';
    const headerSelector = '.cmp-header .cmp-navbar-container';

    class DashboardNavigation {
        constructor(nav) {
            this.nav = nav;
            this.scroll = nav.querySelector('[data-cmp-dashboard-nav-scroll]');
            this.list = nav.querySelector('.cmp-dashboard-nav__list');
            this.previous = nav.querySelector('[data-cmp-dashboard-nav-previous]');
            this.next = nav.querySelector('[data-cmp-dashboard-nav-next]');
            this.header = null;
            this.frame = null;
            this.revealPending = false;
            this.resizeObserver = null;
            this.onScroll = () => this.schedule();
            this.onFocusIn = (event) => {
                const link = event.target instanceof Element
                    ? event.target.closest('.cmp-dashboard-nav__link')
                    : null;

                if (link && this.scroll.contains(link)) {
                    this.revealLink(link);
                    this.updateControls();
                }
            };
            this.onPrevious = () => this.scrollBy(-1);
            this.onNext = () => this.scrollBy(1);
            this.onControlBlur = () => this.schedule();
        }

        init() {
            if (!(this.scroll instanceof HTMLElement)
                || !(this.list instanceof HTMLElement)
                || !(this.previous instanceof HTMLButtonElement)
                || !(this.next instanceof HTMLButtonElement)) {
                return false;
            }

            this.scroll.addEventListener('scroll', this.onScroll, { passive: true });
            this.scroll.addEventListener('focusin', this.onFocusIn);
            this.previous.addEventListener('click', this.onPrevious);
            this.next.addEventListener('click', this.onNext);
            this.previous.addEventListener('blur', this.onControlBlur);
            this.next.addEventListener('blur', this.onControlBlur);

            if (typeof window.ResizeObserver === 'function') {
                this.resizeObserver = new window.ResizeObserver(() => this.schedule(true));
                this.resizeObserver.observe(this.scroll);
                this.resizeObserver.observe(this.list);
            }

            this.schedule(true);

            return true;
        }

        destroy() {
            window.cancelAnimationFrame(this.frame);
            this.scroll.removeEventListener('scroll', this.onScroll);
            this.scroll.removeEventListener('focusin', this.onFocusIn);
            this.previous.removeEventListener('click', this.onPrevious);
            this.next.removeEventListener('click', this.onNext);
            this.previous.removeEventListener('blur', this.onControlBlur);
            this.next.removeEventListener('blur', this.onControlBlur);
            this.resizeObserver?.disconnect();
        }

        schedule(reveal = false) {
            this.revealPending = this.revealPending || reveal;

            if (this.frame !== null) {
                return;
            }

            this.frame = window.requestAnimationFrame(() => {
                this.frame = null;

                if (!this.nav.isConnected) {
                    return;
                }

                const revealNow = this.revealPending;
                this.revealPending = false;
                this.syncHeaderOffset();
                this.updateControls();

                if (revealNow) {
                    this.revealActive();
                    this.updateControls();
                }
            });
        }

        syncHeaderOffset() {
            const header = document.querySelector(headerSelector);

            if (header !== this.header) {
                if (this.header && this.resizeObserver) {
                    this.resizeObserver.unobserve(this.header);
                }

                this.header = header;

                if (header && this.resizeObserver) {
                    this.resizeObserver.observe(header);
                }
            }

            const fallback = Number.parseFloat(
                window.getComputedStyle(document.documentElement).getPropertyValue('--cmp-sticky-offset')
            ) || 0;
            const bottom = header?.getClientRects().length
                ? header.getBoundingClientRect().bottom
                : 0;
            const offset = bottom > 0 ? bottom : fallback;

            this.nav.style.setProperty('--cmp-dashboard-header-bottom', `${Math.ceil(offset)}px`);
        }

        updateControls() {
            const hasOverflow = this.scroll.clientWidth > 0
                && this.scroll.scrollWidth > this.scroll.clientWidth + 1;

            if (!hasOverflow) {
                this.setControlAvailable(this.previous, false);
                this.setControlAvailable(this.next, false);
                return;
            }

            const viewport = this.scroll.getBoundingClientRect();
            const content = this.list.getBoundingClientRect();

            this.setControlAvailable(this.previous, content.left < viewport.left - 1);
            this.setControlAvailable(this.next, content.right > viewport.right + 1);
        }

        setControlAvailable(control, available) {
            const keepFocus = !available && document.activeElement === control;

            control.hidden = !available && !keepFocus;

            if (keepFocus) {
                control.setAttribute('aria-disabled', 'true');
            } else {
                control.removeAttribute('aria-disabled');
            }
        }

        revealActive() {
            const active = this.scroll.querySelector(
                '.cmp-dashboard-nav__link[aria-current="page"], '
                + '.cmp-dashboard-nav__link[aria-current="location"]'
            );

            this.revealLink(active);
        }

        revealLink(linkElement) {
            if (!(linkElement instanceof HTMLElement)) {
                return;
            }

            const viewport = this.scroll.getBoundingClientRect();
            const link = linkElement.getBoundingClientRect();
            const left = viewport.left + (this.previous.hidden ? 0 : this.previous.offsetWidth);
            const right = viewport.right - (this.next.hidden ? 0 : this.next.offsetWidth);
            const delta = link.left < left
                ? link.left - left
                : (link.right > right ? link.right - right : 0);

            if (Math.abs(delta) > 1) {
                this.scroll.scrollLeft += delta;
            }
        }

        scrollBy(direction) {
            const control = direction < 0 ? this.previous : this.next;

            if (control.getAttribute('aria-disabled') === 'true') {
                return;
            }

            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            this.scroll.scrollBy({
                left: direction * Math.max(1, this.scroll.clientWidth * 0.75),
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
        }
    }

    const refresh = () => {
        for (const [nav, controller] of controllers) {
            if (!nav.isConnected) {
                controller.destroy();
                controllers.delete(nav);
            }
        }

        document.querySelectorAll(selector).forEach((nav) => {
            if (!controllers.has(nav)) {
                const controller = new DashboardNavigation(nav);

                if (controller.init()) {
                    controllers.set(nav, controller);
                }
            } else {
                controllers.get(nav).schedule(true);
            }
        });
    };

    const updateAll = (reveal = false) => {
        controllers.forEach((controller) => controller.schedule(reveal));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh, { once: true });
    } else {
        refresh();
    }

    document.addEventListener('joomla:updated', refresh);
    document.addEventListener('copymypage:alert-offset-change', () => updateAll());
    window.addEventListener('resize', () => updateAll(true), { passive: true });
    window.addEventListener('orientationchange', () => updateAll(true), { passive: true });
    window.addEventListener('load', () => updateAll(true), { once: true });
})(window, document);
