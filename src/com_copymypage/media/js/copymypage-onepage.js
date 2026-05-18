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
            this._metaSyncFrame = null;
            this._recoveryTimeouts = [];
            this._sectionObserver = null;
            this._sections = [];
            this._defaultMeta = null;
            this._pendingSection = null;
            this._sectionParam = 'section';
            this._requestedSectionToken = '';
            this._activeSectionToken = '';
            this._activeMetaSignature = '';
            this._lastSmoothRestoreKey = '';
            this._pendingReleaseTimeout = null;
            this._handleLifecycleChange = this._handleLifecycleChange.bind(this);
            this._handleSectionChange = this._handleSectionChange.bind(this);
            this._handleScrollLinkClick = this._handleScrollLinkClick.bind(this);
        }

        init() {
            if (!this._isOnepageDocument()) {
                return;
            }

            this._bindNavbarStateObserver();
            this._initializeMetaState();
            this._bindSectionObserver();
            this.scheduleNavbarTopStateSync();
            this.scheduleSectionMetaSync();
            this._handleLifecycleChange();
            this.listen(window, 'load', this._handleLifecycleChange);
            this.listen(window, 'pageshow', this._handleLifecycleChange);
            this.listen(window, 'hashchange', this._handleLifecycleChange);
            this.listen(document, 'copymypage:onepage-sectionchange', this._handleSectionChange);
            this.listen(document, 'click', this._handleScrollLinkClick);
        }

        destroy() {
            window.cancelAnimationFrame(this._hashScrollFrame);
            window.cancelAnimationFrame(this._navbarSyncFrame);
            window.cancelAnimationFrame(this._metaSyncFrame);
            window.clearTimeout(this._pendingReleaseTimeout);
            this._hashScrollFrame = null;
            this._navbarSyncFrame = null;
            this._metaSyncFrame = null;
            this._pendingReleaseTimeout = null;
            this._clearRecoveryTimeouts();
            this._disconnectSectionObserver();
            this._sections = [];
            this._defaultMeta = null;
            this._pendingSection = null;
            this._sectionParam = 'section';
            this._requestedSectionToken = '';
            this._activeSectionToken = '';
            this._activeMetaSignature = '';
            this._lastSmoothRestoreKey = '';
            this._pendingReleaseTimeout = null;
            super.destroy();
        }

        handleScroll() {
            this.scheduleNavbarTopStateSync();
            this.scheduleSectionMetaSync();
        }

        handleViewportChange() {
            this.scheduleNavbarTopStateSync();
            this._bindSectionObserver();
            this.scheduleSectionMetaSync();
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

        scheduleSectionMetaSync() {
            if (this._metaSyncFrame !== null) {
                return;
            }

            this._metaSyncFrame = window.requestAnimationFrame(() => {
                this._metaSyncFrame = null;
                this._syncSectionMeta();
            });
        }

        _handleLifecycleChange() {
            const hashSection = this._getSectionByHash(window.location.hash);
            const requestedSection = hashSection
                || this._getSectionByLocationParam()
                || this._getSectionByRequestedMeta();

            if (hashSection) {
                this._clearRequestedSectionToken();
            }

            if (requestedSection && !this._isSectionInAnchorBand(requestedSection)) {
                this._pendingSection = requestedSection;
                this._applySectionMeta(requestedSection);
            } else {
                this._pendingSection = null;
            }

            this._scheduleHashRecoverySequence();
            this.scheduleSectionMetaSync();
        }

        _handleSectionChange(event) {
            const requestedHash = this.normalizeHash(event?.detail?.hash || '');
            const requestedSection = this._getSectionByHash(requestedHash);

            if (requestedSection) {
                this._clearRequestedSectionToken();
                this._pendingSection = requestedSection;
                this._applySectionMeta(requestedSection);
                this._syncSectionLocation(requestedSection.url || this._defaultMeta?.url || window.location.href);
                this._schedulePendingSectionRelease(requestedSection);
            }

            this.scheduleNavbarTopStateSync();
            this.scheduleSectionMetaSync();
        }

        _handleScrollLinkClick(event) {
            const link = event.target?.closest?.('a[data-cmp-scroll="1"]');

            if (!link || !this._isOnepageDocument()) {
                return;
            }

            const requestedHash = this.normalizeHash(link.getAttribute('href'));
            const requestedSection = this._getSectionByHash(requestedHash);

            if (!requestedSection) {
                return;
            }

            event.preventDefault();
            this._activateSectionFromMenu(requestedSection);
        }

        _activateSectionFromMenu(section) {
            this._clearRecoveryTimeouts();
            window.cancelAnimationFrame(this._hashScrollFrame);
            this._hashScrollFrame = null;
            this._clearRequestedSectionToken();
            this._pendingSection = section;
            this._applySectionMeta(section);
            this._syncSectionLocation(section.url || this._defaultMeta?.url || window.location.href);
            section.element.scrollIntoView({
                behavior: this._prefersReducedMotion() ? 'auto' : 'smooth',
                block: 'start',
            });
            this._schedulePendingSectionRelease(section);
            this.scheduleNavbarTopStateSync();
            this.scheduleSectionMetaSync();
        }

        _clearRequestedSectionToken() {
            this._requestedSectionToken = '';
            this._lastSmoothRestoreKey = '';
        }

        _schedulePendingSectionRelease(section) {
            window.clearTimeout(this._pendingReleaseTimeout);
            this._pendingReleaseTimeout = window.setTimeout(() => {
                this._pendingReleaseTimeout = null;

                if (this._pendingSection === section && this._isSectionInAnchorBand(section)) {
                    this._pendingSection = null;
                }

                this.scheduleNavbarTopStateSync();
                this.scheduleSectionMetaSync();
            }, 900);
        }

        _prefersReducedMotion() {
            return Boolean(window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
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
                    this.scheduleSectionMetaSync();
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

        _initializeMetaState() {
            const metaState = this.view?.onepage?.meta;

            this._sections = [];
            this._defaultMeta = null;
            this._requestedSectionToken = '';
            this._activeMetaSignature = '';
            this._lastSmoothRestoreKey = '';

            if (!metaState || typeof metaState !== 'object' || Array.isArray(metaState)) {
                return;
            }

            this._sectionParam = String(metaState.sectionParam || 'section').trim() || 'section';
            this._requestedSectionToken = String(metaState.requestedSection || '').trim().toLowerCase();
            this._defaultMeta = this._normalizeMetaPayload(metaState.page || {});
            const sections = metaState.sections && typeof metaState.sections === 'object'
                ? Object.values(metaState.sections)
                : [];

            this._sections = sections
                .map((entry) => this._hydrateSection(entry))
                .filter(Boolean);
        }

        _hydrateSection(entry) {
            if (!entry || typeof entry !== 'object' || Array.isArray(entry)) {
                return null;
            }

            const normalized = this._normalizeMetaPayload(entry);
            const token = normalized.token || '';
            const hash = normalized.hash || this.normalizeHash(`#${token}`);
            const selector = normalized.selector || hash;

            if (!token || !hash || !selector) {
                return null;
            }

            const element = document.querySelector(selector);

            if (!utils.isHTMLElement(element)) {
                return null;
            }

            return {
                ...normalized,
                token,
                hash,
                selector,
                element,
                isIntersecting: false,
                intersectionRatio: 0,
            };
        }

        _bindSectionObserver() {
            this._disconnectSectionObserver();

            if (!('IntersectionObserver' in window) || this._sections.length === 0) {
                return;
            }

            const stickyOffset = Math.ceil(utils.getStickyOffset());

            this._sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    const section = this._sections.find((item) => item.element === entry.target);

                    if (!section) {
                        return;
                    }

                    section.isIntersecting = entry.isIntersecting;
                    section.intersectionRatio = entry.intersectionRatio;
                });

                this.scheduleSectionMetaSync();
            }, {
                root: null,
                rootMargin: `${-stickyOffset}px 0px -45% 0px`,
                threshold: [0, 0.15, 0.35, 0.6, 0.85, 1],
            });

            this._sections.forEach((section) => this._sectionObserver.observe(section.element));
        }

        _disconnectSectionObserver() {
            if (this._sectionObserver) {
                this._sectionObserver.disconnect();
                this._sectionObserver = null;
            }
        }

        _syncSectionMeta() {
            const activeSection = this._resolveActiveSection();

            if (activeSection) {
                this._applySectionMeta(activeSection);
                return;
            }

            if (this._defaultMeta) {
                this._applySectionMeta(this._defaultMeta);
            }
        }

        _resolveActiveSection() {
            if (this._sections.length === 0) {
                return null;
            }

            if (this._pendingSection) {
                return this._pendingSection;
            }

            const hashSection = this._getSectionByHash(window.location.hash);

            if (hashSection && this._isSectionInAnchorBand(hashSection)) {
                return hashSection;
            }

            const stickyOffset = utils.getStickyOffset();
            const anchorBand = utils.getExpectedAnchorBand(stickyOffset);
            let activeSection = this._sections[0];

            for (const section of this._sections) {
                const rect = section.element.getBoundingClientRect();

                if (rect.bottom <= stickyOffset + 1) {
                    activeSection = section;
                    continue;
                }

                if (rect.top <= anchorBand) {
                    activeSection = section;
                    continue;
                }

                if (section.isIntersecting || rect.top > anchorBand) {
                    break;
                }
            }

            return activeSection;
        }

        _isSectionInAnchorBand(section) {
            const stickyOffset = utils.getStickyOffset();
            const rect = section.element.getBoundingClientRect();
            const anchorBand = utils.getExpectedAnchorBand(stickyOffset);

            return rect.top >= -1
                && rect.top <= anchorBand;
        }

        _getSectionByHash(hash) {
            const normalizedHash = this.normalizeHash(hash);

            if (!normalizedHash) {
                return null;
            }

            return this._sections.find((section) => section.hash === normalizedHash) || null;
        }

        _getSectionByLocationParam() {
            try {
                const token = new URL(window.location.href).searchParams.get(this._sectionParam || 'section');

                return this._getSectionByToken(token);
            } catch (error) {
                return null;
            }
        }

        _getSectionByRequestedMeta() {
            return this._getSectionByToken(this._requestedSectionToken);
        }

        _getSectionByToken(token) {
            const normalizedToken = String(token || '').trim().toLowerCase();

            if (!normalizedToken) {
                return null;
            }

            return this._sections.find((section) => section.token === normalizedToken) || null;
        }

        _normalizeMetaPayload(entry) {
            const payload = entry && typeof entry === 'object' && !Array.isArray(entry) ? entry : {};

            return {
                title: String(payload.title || '').trim(),
                description: String(payload.description || '').trim(),
                image: String(payload.image || '').trim(),
                imageWidth: String(payload.imageWidth || '').trim(),
                imageHeight: String(payload.imageHeight || '').trim(),
                imageAlt: String(payload.imageAlt || '').trim(),
                url: String(payload.url || '').trim(),
                twitterCard: String(payload.twitterCard || '').trim() || 'summary',
                token: String(payload.token || '').trim().toLowerCase(),
                hash: this.normalizeHash(payload.hash || ''),
                selector: String(payload.selector || '').trim(),
                label: String(payload.label || '').trim(),
            };
        }

        _applySectionMeta(meta) {
            const resolvedTitle = meta.title || this._defaultMeta?.title || document.title;
            const resolvedDescription = meta.description || this._defaultMeta?.description || '';
            const resolvedImage = meta.image || '';
            const resolvedImageWidth = resolvedImage ? (meta.imageWidth || '') : '';
            const resolvedImageHeight = resolvedImage ? (meta.imageHeight || '') : '';
            const resolvedImageAlt = resolvedImage ? (meta.imageAlt || '') : '';
            const resolvedUrl = meta.url || this._defaultMeta?.url || window.location.href;
            const resolvedTwitterCard = meta.twitterCard || (resolvedImage ? 'summary_large_image' : 'summary');
            const resolvedToken = String(meta.token || '').trim().toLowerCase();
            const signature = [
                resolvedTitle,
                resolvedDescription,
                resolvedImage,
                resolvedImageWidth,
                resolvedImageHeight,
                resolvedImageAlt,
                resolvedTwitterCard,
                resolvedUrl,
            ].join('||');

            if (signature === this._activeMetaSignature) {
                return;
            }

            this._activeMetaSignature = signature;
            this._activeSectionToken = resolvedToken;

            if (resolvedTitle) {
                document.title = resolvedTitle;
            }

            this._syncSectionLocation(resolvedUrl);
            this._upsertMetaByName('description', resolvedDescription);
            this._upsertMetaByProperty('og:title', resolvedTitle);
            this._upsertMetaByProperty('og:description', resolvedDescription);
            this._upsertMetaByProperty('og:url', resolvedUrl);
            this._upsertMetaByProperty('og:image', resolvedImage);
            this._upsertMetaByProperty('og:image:width', resolvedImageWidth);
            this._upsertMetaByProperty('og:image:height', resolvedImageHeight);
            this._upsertMetaByProperty('og:image:alt', resolvedImageAlt);
            this._upsertMetaByName('twitter:card', resolvedTwitterCard);
            this._upsertMetaByName('twitter:title', resolvedTitle);
            this._upsertMetaByName('twitter:description', resolvedDescription);
            this._upsertMetaByName('twitter:image', resolvedImage);
            this.scheduleNavbarTopStateSync();
        }

        _syncSectionLocation(url) {
            if (!url || !window.history || typeof window.history.replaceState !== 'function') {
                return;
            }

            try {
                const targetUrl = new URL(url, window.location.href);
                const currentUrl = new URL(window.location.href);

                if (targetUrl.origin !== currentUrl.origin) {
                    return;
                }

                if (targetUrl.pathname === currentUrl.pathname
                    && targetUrl.search === currentUrl.search
                    && targetUrl.hash === currentUrl.hash) {
                    return;
                }

                window.history.replaceState(window.history.state, document.title, targetUrl.toString());
            } catch (error) {
                // Ignore URL sync failures so meta updates continue to work.
            }
        }

        _upsertMetaByName(name, content) {
            this._upsertMeta(`meta[name="${this._escapeAttributeValue(name)}"]`, 'name', name, content);
        }

        _upsertMetaByProperty(property, content) {
            this._upsertMeta(`meta[property="${this._escapeAttributeValue(property)}"]`, 'property', property, content);
        }

        _upsertMeta(selector, attributeName, attributeValue, content) {
            const nodes = Array.from(document.head.querySelectorAll(selector));
            const metaNode = nodes.shift() || document.createElement('meta');

            if (!content) {
                nodes.forEach((node) => node.remove());

                if (metaNode.isConnected) {
                    metaNode.remove();
                }

                return;
            }

            metaNode.setAttribute(attributeName, attributeValue);
            metaNode.setAttribute('content', content);

            if (!metaNode.isConnected) {
                document.head.appendChild(metaNode);
            }

            nodes.forEach((node) => node.remove());
        }

        _escapeAttributeValue(value) {
            return String(value || '').replace(/"/g, '\\"');
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
            const sectionPinState = this._resolveSectionPinState(scrollLinks);

            if (sectionPinState) {
                this._pinLink(scrollLinks, sectionPinState.link, constants.PINNED_LINK_DATA.hash);
                return;
            }

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

            const currentHash = this.normalizeHash(window.location.hash);
            const sectionFromLocation = this._getSectionByLocationParam();
            const sectionFromRequest = sectionFromLocation || this._pendingSection || this._getSectionByRequestedMeta();
            const targetHash = currentHash || (sectionFromRequest ? sectionFromRequest.hash : '');
            const shouldUseSmoothRestore = !currentHash && Boolean(sectionFromRequest);

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
                if (this._pendingSection?.element === target) {
                    this._pendingSection = null;
                }

                if (!currentHash && sectionFromRequest?.token === this._requestedSectionToken) {
                    this._clearRequestedSectionToken();
                }

                return;
            }

            const restoreKey = `${window.location.pathname}${window.location.search}${window.location.hash}:${targetHash}`;
            const reduceMotion = this._prefersReducedMotion();
            const useSmoothScroll = shouldUseSmoothRestore && !reduceMotion;

            if (useSmoothScroll && this._lastSmoothRestoreKey === restoreKey) {
                return;
            }

            target.scrollIntoView({ behavior: useSmoothScroll ? 'smooth' : 'auto', block: 'start' });

            if (useSmoothScroll) {
                this._lastSmoothRestoreKey = restoreKey;
            }

            if (this._pendingSection?.element === target) {
                this._pendingSection = null;
            }

            if (!currentHash && sectionFromRequest?.token === this._requestedSectionToken) {
                this._clearRequestedSectionToken();
            }

            this.scheduleNavbarTopStateSync();
            this.scheduleSectionMetaSync();
        }

        _getOnepageHashLink(scrollLinks) {
            const currentHash = this.normalizeHash(window.location.hash);

            if (!currentHash) {
                return null;
            }

            return scrollLinks.find((link) => this.normalizeHash(link.getAttribute('href')) === currentHash) || null;
        }

        _resolveSectionPinState(scrollLinks) {
            const activeToken = String(this._activeSectionToken || '').trim().toLowerCase();

            if (!activeToken) {
                return null;
            }

            const activeSection = this._getSectionByToken(activeToken);
            const activeHash = activeSection?.hash || this.normalizeHash(`#${activeToken}`);

            if (!activeHash) {
                return null;
            }

            const link = scrollLinks.find((item) => this.normalizeHash(item.getAttribute('href')) === activeHash);

            return utils.isHTMLElement(link) ? { link } : null;
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
