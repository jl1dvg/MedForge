const $ = window.jQuery;

if (!$) {
    throw new Error('MedForge shell runtime requires jQuery.');
}

const SELECTOR = {
    wrapper: '.wrapper',
    contentWrapper: '.content-wrapper',
    layoutBoxed: '.layout-boxed',
    mainFooter: '.main-footer',
    mainHeader: '.main-header',
    sidebar: '.sidebar',
    controlSidebar: '.control-sidebar',
    sidebarMenu: '[data-widget="tree"]',
    pushMenuButton: '[data-toggle="push-menu"]',
    box: '.box',
    boxBody: '.box-body, .box-content',
    treeview: '.treeview',
    treeviewMenu: '.treeview-menu',
};

const CLASS_NAME = {
    fixed: 'fixed',
    holdTransition: 'hold-transition',
    collapsed: 'sidebar-collapse',
    open: 'sidebar-open',
    menuOpen: 'menu-open',
    rotated: 'rotate-180',
    boxMaximize: 'box-maximize',
    boxFullscreen: 'box-fullscreen',
};

const EVENT = {
    pushExpanded: 'expanded.pushMenu',
    pushCollapsed: 'collapsed.pushMenu',
    treeExpanded: 'expanded.tree',
    treeCollapsed: 'collapsed.tree',
};

const PUSH_MENU_COLLAPSE_SCREEN_SIZE = 767;

let layoutTimer = null;

const scheduleLayoutFix = (delay = 0) => {
    window.clearTimeout(layoutTimer);
    layoutTimer = window.setTimeout(fixLayout, delay);
};

const emitEvent = (target, name) => {
    $(target).trigger($.Event(name));
};

const fixLayout = () => {
    const body = document.body;
    const contentWrapper = document.querySelector(SELECTOR.contentWrapper);

    if (!body || !contentWrapper) {
        return;
    }

    const wrapper = document.querySelector(SELECTOR.wrapper);
    const layoutBoxedWrapper = document.querySelector(`${SELECTOR.layoutBoxed} > ${SELECTOR.wrapper}`);
    const footer = document.querySelector(SELECTOR.mainFooter);
    const header = document.querySelector(SELECTOR.mainHeader);
    const sidebar = document.querySelector(SELECTOR.sidebar);
    const controlSidebar = document.querySelector(SELECTOR.controlSidebar);

    if (layoutBoxedWrapper) {
        layoutBoxedWrapper.style.overflow = 'hidden';
    }

    if (wrapper) {
        wrapper.style.height = 'auto';
        wrapper.style.minHeight = '100%';
    }

    document.documentElement.style.height = 'auto';
    document.documentElement.style.minHeight = '100%';
    body.style.height = 'auto';
    body.style.minHeight = '100%';

    const footerHeight = footer?.offsetHeight ?? 0;
    const headerHeight = header?.offsetHeight ?? 0;
    const windowHeight = window.innerHeight;
    const sidebarHeight = sidebar?.offsetHeight ?? 0;

    if (body.classList.contains(CLASS_NAME.fixed)) {
        contentWrapper.style.minHeight = `${Math.max(windowHeight - footerHeight, 0)}px`;
        return;
    }

    const neg = headerHeight + footerHeight;
    let postSetHeight = windowHeight >= sidebarHeight
        ? Math.max(windowHeight - neg, 0)
        : sidebarHeight;

    if ((controlSidebar?.offsetHeight ?? 0) > postSetHeight) {
        postSetHeight = controlSidebar.offsetHeight;
    }

    contentWrapper.style.minHeight = `${postSetHeight}px`;
};

const updatePushMenuButtonState = () => {
    const expanded = window.innerWidth <= PUSH_MENU_COLLAPSE_SCREEN_SIZE
        ? document.body.classList.contains(CLASS_NAME.open)
        : !document.body.classList.contains(CLASS_NAME.collapsed);

    document.querySelectorAll(SELECTOR.pushMenuButton).forEach((button) => {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
};

const togglePushMenu = () => {
    const body = document.body;
    const isMobile = window.innerWidth <= PUSH_MENU_COLLAPSE_SCREEN_SIZE;

    if (isMobile) {
        body.classList.toggle(CLASS_NAME.open);
        body.classList.remove(CLASS_NAME.collapsed);
        const eventName = body.classList.contains(CLASS_NAME.open) ? EVENT.pushExpanded : EVENT.pushCollapsed;
        emitEvent(document, eventName);
        emitEvent(body, eventName);
    } else {
        body.classList.toggle(CLASS_NAME.collapsed);
        body.classList.remove(CLASS_NAME.open);
        const eventName = body.classList.contains(CLASS_NAME.collapsed) ? EVENT.pushCollapsed : EVENT.pushExpanded;
        emitEvent(document, eventName);
        emitEvent(body, eventName);
    }

    updatePushMenuButtonState();
    scheduleLayoutFix(350);
};

const setTreeviewMenuState = ($menu, expanded) => {
    if (expanded) {
        $menu.stop(true, true).slideDown(250);
    } else {
        $menu.stop(true, true).slideUp(250);
    }
};

const collapseTreeview = ($treeview, $treeRoot) => {
    if (!$treeview.length) {
        return;
    }

    $treeview.removeClass(CLASS_NAME.menuOpen);
    setTreeviewMenuState($treeview.children(SELECTOR.treeviewMenu), false);
    $treeview.find(`${SELECTOR.treeview}.${CLASS_NAME.menuOpen}`).removeClass(CLASS_NAME.menuOpen);
    $treeview.find(SELECTOR.treeviewMenu).stop(true, true).slideUp(250);
    emitEvent($treeRoot, EVENT.treeCollapsed);
};

const expandTreeview = ($treeview, $treeRoot) => {
    if (!$treeview.length) {
        return;
    }

    const $siblings = $treeview.siblings(`.${CLASS_NAME.menuOpen}`);
    $siblings.each((_, sibling) => collapseTreeview($(sibling), $treeRoot));

    $treeview.addClass(CLASS_NAME.menuOpen);
    setTreeviewMenuState($treeview.children(SELECTOR.treeviewMenu), true);
    emitEvent($treeRoot, EVENT.treeExpanded);
};

const initializeTree = () => {
    $(SELECTOR.sidebarMenu).each((_, menu) => {
        const $menu = $(menu);
        $menu.addClass('tree');

        $menu.find(`${SELECTOR.treeview}.${CLASS_NAME.menuOpen} > ${SELECTOR.treeviewMenu}`).show();
        $menu.find(`${SELECTOR.treeview}.active`).addClass(CLASS_NAME.menuOpen).children(SELECTOR.treeviewMenu).show();
        $menu.find(`${SELECTOR.treeview} .active`).parents(SELECTOR.treeview).addClass(CLASS_NAME.menuOpen).children(SELECTOR.treeviewMenu).show();
    });

    $(document).on('click.medforge-tree', `${SELECTOR.sidebarMenu} ${SELECTOR.treeview} > a`, function handleTreeClick(event) {
        const $link = $(this);
        const $treeview = $link.parent(SELECTOR.treeview);
        const $treeRoot = $link.closest(SELECTOR.sidebarMenu);
        const href = ($link.attr('href') || '').trim();

        if (href === '' || href === '#') {
            event.preventDefault();
        }

        if (!$treeview.length) {
            return;
        }

        if ($treeview.hasClass(CLASS_NAME.menuOpen)) {
            collapseTreeview($treeview, $treeRoot);
        } else {
            expandTreeview($treeview, $treeRoot);
        }

        scheduleLayoutFix(300);
    });
};

const initializeBoxControls = () => {
    $(document).on('click.medforge-box', '.box-btn-close', function handleClose(event) {
        event.preventDefault();

        const $box = $(this).closest(SELECTOR.box);
        if (!$box.length) {
            return;
        }

        $box.fadeOut(250, function removeBox() {
            const $parent = $box.parent();

            if ($parent.children().length === 1) {
                $parent.remove();
            } else {
                $box.remove();
            }

            scheduleLayoutFix();
        });
    });

    $(document).on('click.medforge-box', '.box-btn-slide', function handleSlide(event) {
        event.preventDefault();

        const $button = $(this);
        const $box = $button.closest(SELECTOR.box);
        const $boxSections = $box.find(SELECTOR.boxBody);

        $button.toggleClass(CLASS_NAME.rotated);
        $boxSections.stop(true, true).slideToggle(200);
        scheduleLayoutFix(220);
    });

    $(document).on('click.medforge-box', '.box-btn-maximize', function handleMaximize(event) {
        event.preventDefault();
        $(this)
            .closest(SELECTOR.box)
            .toggleClass(CLASS_NAME.boxMaximize)
            .removeClass(CLASS_NAME.boxFullscreen);
        scheduleLayoutFix();
    });

    $(document).on('click.medforge-box', '.box-btn-fullscreen', function handleFullscreen(event) {
        event.preventDefault();
        $(this)
            .closest(SELECTOR.box)
            .toggleClass(CLASS_NAME.boxFullscreen)
            .removeClass(CLASS_NAME.boxMaximize);
        scheduleLayoutFix();
    });
};

const initializePushMenu = () => {
    updatePushMenuButtonState();

    $(document).on('click.medforge-pushmenu', SELECTOR.pushMenuButton, function handlePushMenuClick(event) {
        event.preventDefault();
        togglePushMenu();
    });
};

const initializeLayout = () => {
    document.body.classList.remove(CLASS_NAME.holdTransition);

    fixLayout();

    window.addEventListener('resize', () => scheduleLayoutFix(50));
    $(document).on(`${EVENT.pushExpanded} ${EVENT.pushCollapsed}`, () => scheduleLayoutFix(200));
    $(document).on(`${EVENT.treeExpanded} ${EVENT.treeCollapsed}`, () => scheduleLayoutFix(200));

    const transitionTargets = document.querySelectorAll('.main-header .logo, .sidebar');
    transitionTargets.forEach((element) => {
        element.addEventListener('transitionend', () => scheduleLayoutFix());
    });
};

const initializeShellRuntime = () => {
    initializeLayout();
    initializePushMenu();
    initializeTree();
    initializeBoxControls();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeShellRuntime, { once: true });
} else {
    initializeShellRuntime();
}
