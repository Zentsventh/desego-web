import { router } from '@inertiajs/react';

export const STORE_SECTION_PATHS = {
    inicio: '/',
    carta: '/carta',
    esencia: '/nuestra-esencia',
    reservas: '/reservar',
    contacto: '/ubicacion',
};

const STORE_PATH_SECTIONS = Object.fromEntries(
    Object.entries(STORE_SECTION_PATHS).map(([section, path]) => [path, section])
);

function scrollToStoreSection(section) {
    if (section === 'inicio') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    document.getElementById(section)?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

export function navigateToStoreSection(section, event) {
    event?.preventDefault();

    const path = STORE_SECTION_PATHS[section];
    if (!path) {
        return;
    }

    if (window.location.pathname === path) {
        if (window.location.hash) {
            window.history.replaceState(window.history.state, '', path);
        }
        scrollToStoreSection(section);
        return;
    }

    router.visit(path, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => scrollToStoreSection(section),
    });
}

export function syncStoreSectionLocation() {
    const legacySection = window.location.hash.slice(1);
    if (Object.hasOwn(STORE_SECTION_PATHS, legacySection)) {
        const path = STORE_SECTION_PATHS[legacySection];
        window.history.replaceState(window.history.state, '', path);
        requestAnimationFrame(() => scrollToStoreSection(legacySection));
        return;
    }

    const section = STORE_PATH_SECTIONS[window.location.pathname];
    if (section) {
        requestAnimationFrame(() => scrollToStoreSection(section));
    }
}
