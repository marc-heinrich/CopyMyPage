/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

(function (window, document, Joomla) {
    'use strict';

    const runtime = window.CopyMyPageTickets || {};
    const instances = runtime.instances instanceof WeakMap
        ? runtime.instances
        : new WeakMap();

    const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

    const cloneOptions = (value) => {
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (error) {
            return {};
        }
    };

    const mergeOptions = (base, override) => {
        const merged = isObject(base) ? cloneOptions(base) : {};

        if (!isObject(override)) {
            return merged;
        }

        Object.entries(override).forEach(([key, value]) => {
            if (isObject(value)) {
                merged[key] = mergeOptions(isObject(merged[key]) ? merged[key] : {}, value);

                return;
            }

            merged[key] = Array.isArray(value) ? value.slice() : value;
        });

        return merged;
    };

    const normalizeInitialSlide = (value, slideCount) => {
        const numericValue = typeof value === 'number' || typeof value === 'string'
            ? Number(value)
            : Number.NaN;
        const normalizedValue = Number.isFinite(numericValue)
            ? Math.max(0, Math.floor(numericValue))
            : 0;

        return slideCount > 0
            ? Math.min(normalizedValue, slideCount - 1)
            : 0;
    };

    const isValidSelector = (selector) => {
        if (typeof selector !== 'string' || selector.trim() === '') {
            return false;
        }

        try {
            document.createDocumentFragment().querySelector(selector);

            return true;
        } catch (error) {
            return false;
        }
    };

    const isValidDataAttribute = (attribute) => typeof attribute === 'string'
        && /^data-[a-z0-9-]+$/.test(attribute.trim());

    const isValidClassName = (className) => typeof className === 'string'
        && /^[A-Za-z_][A-Za-z0-9_-]*$/.test(className.trim());

    const collectPaginationLabels = (root, config) => {
        if (!isValidSelector(config.slideSelector) || !isValidDataAttribute(config.paginationLabelAttribute)) {
            return [];
        }

        const attribute = config.paginationLabelAttribute.trim();

        return Array.from(root.querySelectorAll(config.slideSelector)).map((slide, index) => {
            const label = (slide.getAttribute(attribute) || '').trim();

            return /^\d{1,2}\.\d{1,2}\.$/.test(label)
                ? label
                : String(index + 1);
        });
    };

    const selectWithin = (root, selector) => {
        if (!isValidSelector(selector)) {
            return null;
        }

        return root.querySelector(selector);
    };

    const mediaMatches = (query) => typeof query === 'string'
        && query.trim() !== ''
        && typeof window.matchMedia === 'function'
        && window.matchMedia(query).matches;

    const isFlatViewport = (config) => {
        const className = isValidClassName(config.flatViewportClass)
            ? config.flatViewportClass.trim()
            : '';
        const body = document.body;

        if (body && className !== '') {
            if (body.classList.contains(className)) {
                return true;
            }

            const viewportClasses = window.CopyMyPage
                && window.CopyMyPage.constants
                && Array.isArray(window.CopyMyPage.constants.VIEWPORT_BODY_CLASSES)
                ? window.CopyMyPage.constants.VIEWPORT_BODY_CLASSES
                : [];
            const hasViewportClass = viewportClasses.some((viewportClass) => (
                isValidClassName(viewportClass) && body.classList.contains(viewportClass.trim())
            ));

            if (hasViewportClass) {
                return false;
            }
        }

        return mediaMatches(config.mobileQuery);
    };

    const getViewportMode = (config) => isFlatViewport(config) ? 'flat' : 'coverflow';

    const getConfig = () => {
        if (!Joomla || typeof Joomla.getOptions !== 'function') {
            return {};
        }

        const options = Joomla.getOptions('copymypage.params', {}) || {};

        return isObject(options.mod) && isObject(options.mod.tickets)
            ? options.mod.tickets
            : {};
    };

    const collectRoots = (context, selector) => {
        const roots = [];
        const scope = context && typeof context.querySelectorAll === 'function'
            ? context
            : document;

        if (scope instanceof Element && scope.matches(selector)) {
            roots.push(scope);
        }

        scope.querySelectorAll(selector).forEach((root) => {
            if (!roots.includes(root)) {
                roots.push(root);
            }
        });

        return roots;
    };

    const prepareSwiperOptions = (root, config, requestedInitialSlide = null) => {
        let options = mergeOptions({}, config.swiper);
        const navigation = isObject(config.navigation) ? config.navigation : {};
        const previous = selectWithin(root, navigation.previousSelector);
        const next = selectWithin(root, navigation.nextSelector);
        const pagination = selectWithin(root, config.paginationSelector);
        const isDesktop = mediaMatches(config.desktopQuery);
        const prefersReducedMotion = mediaMatches(config.reducedMotionQuery);
        const usesFlatLayout = isFlatViewport(config);
        const slideCount = isValidSelector(config.slideSelector)
            ? root.querySelectorAll(config.slideSelector).length
            : 0;
        const paginationLabels = collectPaginationLabels(root, config);

        if (isDesktop) {
            options = mergeOptions(options, config.desktopSwiper);
        }

        if (usesFlatLayout) {
            options = mergeOptions(options, config.mobileSwiper);
        }

        if (prefersReducedMotion) {
            options = mergeOptions(options, config.reducedMotionSwiper);
        }

        options.initialSlide = normalizeInitialSlide(
            requestedInitialSlide === null ? options.initialSlide : requestedInitialSlide,
            slideCount
        );

        if (options.effect !== 'coverflow') {
            delete options.coverflowEffect;
        }

        if (previous && next) {
            options.navigation = {
                ...(isObject(options.navigation) ? options.navigation : {}),
                nextEl: next,
                prevEl: previous,
            };
        } else {
            options.navigation = false;
        }

        if (pagination) {
            const paginationOptions = {
                ...(isObject(options.pagination) ? options.pagination : {}),
                el: pagination,
            };

            if (!usesFlatLayout) {
                paginationOptions.renderBullet = (index, className) => {
                    const safeClassName = typeof className === 'string'
                        && /^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/.test(className)
                        ? className
                        : 'swiper-pagination-bullet';
                    const label = paginationLabels[index] || String(index + 1);

                    return `<span class="${safeClassName}">${label}</span>`;
                };
            }

            options.pagination = paginationOptions;
        } else {
            options.pagination = false;
        }

        if (slideCount < 2) {
            options.allowTouchMove = false;
            options.centeredSlides = true;
            options.initialSlide = 0;
            options.navigation = false;
            options.pagination = false;
            root.classList.add('cmp-tickets__swiper--single');
        }

        return options;
    };

    const initializeRoot = (root, config, requestedInitialSlide = null) => {
        const initializedAttribute = typeof config.initializedAttribute === 'string'
            ? config.initializedAttribute.trim()
            : '';

        if (!isValidDataAttribute(initializedAttribute)) {
            return;
        }

        if (root.hasAttribute(initializedAttribute) || instances.has(root)) {
            return;
        }

        if (typeof window.Swiper !== 'function') {
            return;
        }

        try {
            const swiper = new window.Swiper(
                root,
                prepareSwiperOptions(root, config, requestedInitialSlide)
            );

            instances.set(root, swiper);
            root.setAttribute(initializedAttribute, 'true');
        } catch (error) {
            root.removeAttribute(initializedAttribute);
        }
    };

    const getActiveSlideIndex = (swiper) => {
        if (!swiper || typeof swiper !== 'object') {
            return 0;
        }

        if (Number.isInteger(swiper.realIndex) && swiper.realIndex >= 0) {
            return swiper.realIndex;
        }

        return Number.isInteger(swiper.activeIndex) && swiper.activeIndex >= 0
            ? swiper.activeIndex
            : 0;
    };

    const resetPaginationViewportState = (root, config) => {
        const pagination = selectWithin(root, config.paginationSelector);

        if (!pagination) {
            return;
        }

        pagination.classList.remove('swiper-pagination-bullets-dynamic');
        pagination.style.removeProperty('width');

        if (pagination.style.length === 0) {
            pagination.removeAttribute('style');
        }
    };

    const reinitializeRoot = (root, config) => {
        const initializedAttribute = typeof config.initializedAttribute === 'string'
            ? config.initializedAttribute.trim()
            : '';

        if (!isValidDataAttribute(initializedAttribute)) {
            return;
        }

        const swiper = instances.get(root);
        const activeSlideIndex = getActiveSlideIndex(swiper);

        if (swiper && !swiper.destroyed && typeof swiper.destroy === 'function') {
            try {
                swiper.destroy(true, true);
            } catch (error) {
                return;
            }
        }

        instances.delete(root);
        root.removeAttribute(initializedAttribute);
        resetPaginationViewportState(root, config);
        initializeRoot(root, config, activeSlideIndex);
    };

    const refreshViewportMode = () => {
        runtime.viewportRefreshScheduled = false;

        const config = getConfig();
        const rootSelector = config.rootSelector;

        if (!isValidSelector(rootSelector)) {
            return;
        }

        const viewportMode = getViewportMode(config);

        if (runtime.viewportMode === viewportMode) {
            return;
        }

        runtime.viewportMode = viewportMode;
        collectRoots(document, rootSelector).forEach((root) => reinitializeRoot(root, config));
    };

    const scheduleViewportRefresh = () => {
        if (runtime.viewportRefreshScheduled) {
            return;
        }

        runtime.viewportRefreshScheduled = true;
        window.requestAnimationFrame(refreshViewportMode);
    };

    const ensureViewportRuntime = (config) => {
        const viewportMode = getViewportMode(config);

        if (typeof runtime.viewportMode !== 'string') {
            runtime.viewportMode = viewportMode;
        } else if (runtime.viewportMode !== viewportMode) {
            scheduleViewportRefresh();
        }

        if (
            runtime.viewportObserver
            || !document.body
            || typeof window.MutationObserver !== 'function'
        ) {
            return;
        }

        runtime.viewportObserver = new window.MutationObserver(scheduleViewportRefresh);
        runtime.viewportObserver.observe(document.body, {
            attributes: true,
            attributeFilter: ['class'],
        });
    };

    const replaceStatusClass = (card, prefix, status) => {
        if (!(card instanceof Element) || typeof prefix !== 'string' || prefix.trim() === '') {
            return;
        }

        Array.from(card.classList).forEach((className) => {
            if (className.startsWith(prefix)) {
                card.classList.remove(className);
            }
        });
        card.classList.add(`${prefix}${status}`);
    };

    const updateAvailabilityCard = (card, state, availabilityConfig) => {
        if (!(card instanceof Element) || !isObject(state)) {
            return;
        }

        const status = typeof state.status === 'string' && /^[a-z-]+$/.test(state.status)
            ? state.status
            : 'unavailable';
        const statusElement = selectWithin(card, availabilityConfig.statusSelector);
        const progress = selectWithin(card, availabilityConfig.progressSelector);
        const progressLabel = selectWithin(card, availabilityConfig.progressLabelSelector);
        const action = selectWithin(card, availabilityConfig.actionSelector);
        const attributes = isObject(availabilityConfig.attributes)
            ? availabilityConfig.attributes
            : {};

        replaceStatusClass(card, availabilityConfig.statusClassPrefix, status);

        if (statusElement) {
            statusElement.textContent = state.statusLabel || '';
        }

        if (progress instanceof HTMLProgressElement && state.progress !== null) {
            progress.value = Math.min(100, Math.max(0, Number(state.progress) || 0));
        }

        if (progressLabel) {
            progressLabel.textContent = state.progressLabel || '';
        }

        if (!(action instanceof HTMLAnchorElement)) {
            return;
        }

        const labelAttribute = isValidDataAttribute(attributes.actionLabel)
            ? attributes.actionLabel
            : '';
        const urlAttribute = isValidDataAttribute(attributes.actionUrl)
            ? attributes.actionUrl
            : '';
        const actionLabel = labelAttribute === '' ? '' : action.getAttribute(labelAttribute) || '';
        const storedUrl = urlAttribute === '' ? '' : action.getAttribute(urlAttribute) || '';
        const selectionUrl = typeof state.selectionUrl === 'string' && state.selectionUrl !== ''
            ? state.selectionUrl
            : storedUrl;

        if (state.bookable && selectionUrl !== '') {
            action.href = selectionUrl;
            action.removeAttribute('aria-disabled');
            action.removeAttribute('tabindex');
            action.classList.remove('disabled');
            action.textContent = actionLabel;

            return;
        }

        action.removeAttribute('href');
        action.setAttribute('aria-disabled', 'true');
        action.setAttribute('tabindex', '-1');
        action.classList.add('disabled');
        action.textContent = state.statusLabel || '';
    };

    const collectAvailabilityCards = (availabilityConfig) => {
        if (!isValidSelector(availabilityConfig.cardSelector)) {
            return [];
        }

        return Array.from(document.querySelectorAll(availabilityConfig.cardSelector));
    };

    const fetchAvailability = async (config) => {
        const availabilityConfig = isObject(config.availability) ? config.availability : {};
        const attributes = isObject(availabilityConfig.attributes)
            ? availabilityConfig.attributes
            : {};

        if (
            runtime.availabilityRequestPending
            || document.hidden
            || typeof availabilityConfig.endpoint !== 'string'
            || availabilityConfig.endpoint.trim() === ''
            || !isValidDataAttribute(attributes.eventId)
        ) {
            return;
        }

        const cards = collectAvailabilityCards(availabilityConfig);
        const eventIds = Array.from(new Set(cards.map((card) => {
            const value = Number(card.getAttribute(attributes.eventId));

            return Number.isInteger(value) && value > 0 ? value : 0;
        }).filter((value) => value > 0)));

        if (eventIds.length === 0) {
            return;
        }

        runtime.availabilityRequestPending = true;

        try {
            const url = new URL(availabilityConfig.endpoint, window.location.href);
            url.searchParams.set('event_ids', eventIds.join(','));
            const response = await window.fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();
            const data = response.ok
                && payload
                && payload.success
                && isObject(payload.data)
                ? payload.data
                : {};
            const availability = isObject(data.availability) ? data.availability : {};
            const cartState = isObject(data.cart) ? data.cart : {};
            const ticketCartRuntime = window.CopyMyPageTicketCart;

            if (
                typeof cartState.active === 'boolean'
                && ticketCartRuntime
                && typeof ticketCartRuntime.setBasketState === 'function'
            ) {
                ticketCartRuntime.setBasketState(
                    cartState.active,
                    typeof cartState.expiresAt === 'string' ? cartState.expiresAt : null
                );
            }

            cards.forEach((card) => {
                const eventId = Number(card.getAttribute(attributes.eventId));
                const state = availability[String(eventId)] || availability[eventId];

                if (isObject(state)) {
                    updateAvailabilityCard(card, state, availabilityConfig);
                }
            });
        } catch (error) {
            // The server-rendered state remains a safe fallback until the next poll.
        } finally {
            runtime.availabilityRequestPending = false;
        }
    };

    const ensureAvailabilityRuntime = (config) => {
        const availabilityConfig = isObject(config.availability) ? config.availability : {};
        const interval = Math.min(
            120000,
            Math.max(10000, Number(availabilityConfig.intervalMs) || 25000)
        );

        if (collectAvailabilityCards(availabilityConfig).length === 0) {
            return;
        }

        if (!runtime.availabilityTimer) {
            runtime.availabilityTimer = window.setInterval(() => fetchAvailability(getConfig()), interval);
        }

        if (!runtime.availabilityListenersRegistered) {
            runtime.availabilityListenersRegistered = true;
            window.addEventListener('focus', () => fetchAvailability(getConfig()));
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    fetchAvailability(getConfig());
                }
            });
        }
    };

    runtime.init = (context = document) => {
        const config = getConfig();
        const rootSelector = config.rootSelector;

        if (!isValidSelector(rootSelector)) {
            return;
        }

        ensureViewportRuntime(config);
        collectRoots(context, rootSelector).forEach((root) => initializeRoot(root, config));
        ensureAvailabilityRuntime(config);
    };

    runtime.instances = instances;
    window.CopyMyPageTickets = runtime;
})(window, document, window.Joomla);
