/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (window, document, CopyMyPage) {
    'use strict';

    const BaseFeature = CopyMyPage.BaseFeature;
    const { constants, utils } = CopyMyPage;

    if (!BaseFeature || !constants || !utils) {
        return;
    }

    class OnepageNavigationFeature extends BaseFeature {
        constructor(host) {
            super(host);
            this._hashScrollFrame = null;
            this._navbarSyncFrame = null;
            this._recoveryTimeouts = [];
            this._handleLifecycleChange = this._handleLifecycleChange.bind(this);
        }

        init() {
            if (!this._isOnepageDocument()) {
                return;
            }

            this._bindNavbarStateObserver();
            this.scheduleNavbarTopStateSync();
            this._handleLifecycleChange();
            this.listen(window, 'load', this._handleLifecycleChange);
            this.listen(window, 'pageshow', this._handleLifecycleChange);
            this.listen(window, 'hashchange', this._handleLifecycleChange);
        }

        destroy() {
            window.cancelAnimationFrame(this._hashScrollFrame);
            window.cancelAnimationFrame(this._navbarSyncFrame);
            this._hashScrollFrame = null;
            this._navbarSyncFrame = null;
            this._clearRecoveryTimeouts();
            super.destroy();
        }

        handleScroll() {
            this.scheduleNavbarTopStateSync();
        }

        handleViewportChange() {
            this.scheduleNavbarTopStateSync();
        }

        scheduleNavbarTopStateSync() {
            if (this._navbarSyncFrame !== null) {
                return;
            }

            this._navbarSyncFrame = window.requestAnimationFrame(() => {
                this._navbarSyncFrame = null;
                this._syncNavbarTopState();
            });
        }

        _handleLifecycleChange() {
            this._scheduleHashRecoverySequence();
        }

        _clearRecoveryTimeouts() {
            while (this._recoveryTimeouts.length > 0) {
                window.clearTimeout(this._recoveryTimeouts.pop());
            }
        }

        _scheduleHashRecoverySequence() {
            if (!this._isOnepageDocument()) {
                return;
            }

            this._clearRecoveryTimeouts();

            constants.TIMINGS.onepageHashRecoveryDelays.forEach((delay) => {
                const timeoutId = window.setTimeout(() => {
                    this._scheduleHashScrollRestore();
                    this.scheduleNavbarTopStateSync();
                }, delay);

                this._recoveryTimeouts.push(timeoutId);
            });
        }

        _scheduleHashScrollRestore() {
            if (this._hashScrollFrame !== null) {
                return;
            }

            this._hashScrollFrame = window.requestAnimationFrame(() => {
                this._hashScrollFrame = null;
                this._restoreHashScroll();
            });
        }

        _bindNavbarStateObserver() {
            const navbar = this._getNavbar();

            if (!navbar) {
                return;
            }

            const observer = new MutationObserver((mutations) => {
                if (!mutations.some((mutation) => mutation.type === 'attributes')) {
                    return;
                }

                this.scheduleNavbarTopStateSync();
            });

            observer.observe(navbar, {
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'aria-current'],
            });

            this.cleanup.add(() => observer.disconnect());
        }

        _syncNavbarTopState() {
            if (!this._isOnepageDocument()) {
                return;
            }

            const navbar = this._getNavbar();

            if (!navbar) {
                return;
            }

            const scrollLinks = this._getScrollLinks(navbar);

            if (scrollLinks.length === 0) {
                return;
            }

            const firstLink = scrollLinks[0];
            const firstItem = firstLink.closest('li');

            if (!utils.isHTMLElement(firstItem)) {
                return;
            }

            const stickyOffset = utils.getStickyOffset();

            if (this._shouldPinFirstLink(scrollLinks, stickyOffset)) {
                this._pinLink(scrollLinks, firstLink, constants.PINNED_LINK_DATA.top);
                return;
            }

            const hashPinState = this._resolveHashPinState(scrollLinks, stickyOffset);

            if (hashPinState?.shouldPin) {
                this._pinLink(scrollLinks, hashPinState.link, constants.PINNED_LINK_DATA.hash);
                return;
            }

            if (hashPinState) {
                this._releasePinnedLink(
                    hashPinState.link,
                    hashPinState.hasOtherActiveItem,
                    constants.PINNED_LINK_DATA.hash
                );
            }

            this._releasePinnedLink(
                firstLink,
                this._hasOtherActiveItems(scrollLinks, firstLink),
                constants.PINNED_LINK_DATA.top
            );
        }

        _shouldPinFirstLink(scrollLinks, stickyOffset) {
            const scrollY = utils.getCurrentScrollPosition();
            const secondTargetTop = this._getLinkTargetTop(scrollLinks[1] || null);

            return scrollY <= stickyOffset + 1
                && (secondTargetTop === null || secondTargetTop > stickyOffset + 1);
        }

        _resolveHashPinState(scrollLinks, stickyOffset) {
            const hashLink = this._getOnepageHashLink(scrollLinks);

            if (!utils.isHTMLElement(hashLink)) {
                return null;
            }

            const hashTargetTop = this._getLinkTargetTop(hashLink);
            const nextHashLink = scrollLinks[scrollLinks.indexOf(hashLink) + 1] || null;
            const nextHashTargetTop = this._getLinkTargetTop(nextHashLink);
            const hashPinUpperBound = utils.getExpectedAnchorBand(stickyOffset);

            return {
                link: hashLink,
                hasOtherActiveItem: this._hasOtherActiveItems(scrollLinks, hashLink),
                shouldPin: hashTargetTop !== null
                    && hashTargetTop >= -constants.THRESHOLDS.pinLeeway
                    && hashTargetTop <= hashPinUpperBound
                    && (nextHashTargetTop === null || nextHashTargetTop > stickyOffset + 1),
            };
        }

        _getLinkTargetTop(link) {
            const target = this._getLinkTarget(link);

            return utils.isHTMLElement(target) ? target.getBoundingClientRect().top : null;
        }

        _getLinkTarget(link) {
            if (!utils.isHTMLElement(link)) {
                return null;
            }

            const targetHash = this.normalizeHash(link.getAttribute('href'));

            if (!targetHash) {
                return null;
            }

            const target = document.querySelector(targetHash);

            return utils.isHTMLElement(target) ? target : null;
        }

        _hasOtherActiveItems(scrollLinks, excludedLink) {
            return scrollLinks.some((link) => {
                if (link === excludedLink) {
                    return false;
                }

                const item = link.closest('li');

                return utils.isHTMLElement(item) && item.classList.contains('uk-active');
            });
        }

        _pinLink(scrollLinks, activeLink, pinState) {
            scrollLinks.forEach((link) => {
                if (link === activeLink) {
                    return;
                }

                this._clearLinkVisualState(link);
            });

            const activeItem = activeLink.closest('li');

            if (utils.isHTMLElement(activeItem)) {
                activeItem.classList.add('uk-active');
            }

            activeLink.dataset[pinState.activeKey] = '1';

            if (activeLink.getAttribute('aria-current') !== 'page') {
                activeLink.setAttribute('aria-current', 'page');
                activeLink.dataset[pinState.ariaKey] = '1';
            }
        }

        _releasePinnedLink(link, hasOtherActiveItem, pinState) {
            const item = link.closest('li');

            if (link.dataset[pinState.activeKey] === '1' && hasOtherActiveItem) {
                if (utils.isHTMLElement(item)) {
                    item.classList.remove('uk-active');
                }

                delete link.dataset[pinState.activeKey];
            }

            if (link.dataset[pinState.ariaKey] === '1' && hasOtherActiveItem) {
                link.removeAttribute('aria-current');
                delete link.dataset[pinState.ariaKey];
            }
        }

        _clearLinkVisualState(link) {
            const item = link.closest('li');

            if (utils.isHTMLElement(item)) {
                item.classList.remove('uk-active');
            }

            if (link.getAttribute('aria-current') === 'page') {
                link.removeAttribute('aria-current');
            }
        }

        _restoreHashScroll() {
            if (!this._isOnepageDocument()) {
                return;
            }

            const targetHash = this.normalizeHash(window.location.hash);

            if (!targetHash || targetHash === '#top') {
                return;
            }

            const target = document.querySelector(targetHash);

            if (!utils.isHTMLElement(target)) {
                return;
            }

            const stickyOffset = utils.getStickyOffset();
            const scrollY = utils.getCurrentScrollPosition();
            const targetTop = target.getBoundingClientRect().top;
            const shouldRestoreScroll = scrollY <= stickyOffset + 1
                && targetTop > utils.getExpectedAnchorBand(stickyOffset);

            if (!shouldRestoreScroll) {
                return;
            }

            target.scrollIntoView({ behavior: 'auto', block: 'start' });
            this.scheduleNavbarTopStateSync();
        }

        _getOnepageHashLink(scrollLinks) {
            const currentHash = this.normalizeHash(window.location.hash);

            if (!currentHash) {
                return null;
            }

            return scrollLinks.find((link) => this.normalizeHash(link.getAttribute('href')) === currentHash) || null;
        }

        _getNavbar() {
            const navbar = document.querySelector(constants.SELECTORS.onepageNavbar);

            return utils.isHTMLElement(navbar) ? navbar : null;
        }

        _getScrollLinks(navbar) {
            return Array.from(navbar.querySelectorAll(':scope > li > a[data-cmp-scroll="1"]'));
        }

        _isOnepageDocument() {
            return Boolean(document.body && document.body.classList.contains('is-onepage'));
        }
    }

    CopyMyPage.features.OnepageNavigation = OnepageNavigationFeature;
})(window, document, window.CopyMyPage);
