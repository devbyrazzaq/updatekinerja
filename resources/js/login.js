import { animate, stagger } from 'motion';

const root = document.documentElement;
const splash = document.querySelector('[data-splash]');
const sheet = document.querySelector('[data-sheet]');
const backdrop = document.querySelector('[data-sheet-backdrop]');
const desktop = window.matchMedia('(min-width: 1024px)');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const easeOut = [0.16, 1, 0.3, 1];
const easeIn = [0.7, 0, 0.84, 0];

/** Menandai bahwa bundel animasi berhasil dimuat (dipakai fallback di <head>). */
root.classList.add('motion-ready');

/** @param {number} ms */
const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

/** @param {string} selector @returns {HTMLElement[]} */
const all = (selector) => Array.from(document.querySelectorAll(selector));

const animated = all('[data-anim]');

/**
 * Kunci keadaan awal lewat gaya inline supaya tidak ada kedipan saat kelas
 * `is-splashing` dilepas sebelum animasi masuk dijalankan.
 */
if (!reduceMotion) {
    animated.forEach((el) => {
        el.style.opacity = '0';
    });
}

const reveal = () => {
    if (reduceMotion) {
        animated.forEach((el) => {
            el.style.opacity = '1';
        });

        return;
    }

    const brand = all('[data-anim="brand"]');
    const form = all('[data-anim="form"]');

    if (brand.length) {
        animate(
            brand,
            { opacity: [0, 1], y: [28, 0] },
            { duration: 0.7, delay: stagger(0.1), ease: easeOut },
        );
    }

    if (form.length) {
        animate(
            form,
            { opacity: [0, 1], y: [22, 0] },
            { duration: 0.6, delay: stagger(0.08, { startDelay: 0.12 }), ease: easeOut },
        );
    }

    /** Blok tombol di mobile naik utuh dari bawah, setelah teks brand selesai. */
    const cta = document.querySelector('[data-anim="cta"]');

    if (cta) {
        animate(
            cta,
            { opacity: [0, 1], y: ['100%', '0%'] },
            { duration: 0.65, delay: 0.6, ease: easeOut },
        );
    }
};

/* ------------------------------------------------------------------ *
 * Bottom sheet formulir (hanya aktif di bawah breakpoint lg)
 * ------------------------------------------------------------------ */

let sheetOpen = false;

const openSheet = () => {
    if (!sheet || sheetOpen || desktop.matches) {
        return;
    }

    sheetOpen = true;
    root.classList.add('is-sheet-open');

    if (reduceMotion) {
        backdrop.style.opacity = '1';
        sheet.style.transform = 'none';

        return;
    }

    animate(backdrop, { opacity: [0, 1] }, { duration: 0.3, ease: 'linear' });
    animate(sheet, { y: ['100%', '0%'] }, { duration: 0.5, ease: easeOut });
};

const closeSheet = async () => {
    if (!sheet || !sheetOpen) {
        return;
    }

    sheetOpen = false;
    document.activeElement?.blur();

    if (!reduceMotion) {
        animate(backdrop, { opacity: [1, 0] }, { duration: 0.25, ease: 'linear' });
        await animate(sheet, { y: ['0%', '100%'] }, { duration: 0.35, ease: easeIn }).finished;
    }

    backdrop.style.opacity = '0';
    root.classList.remove('is-sheet-open');
};

/** Bersihkan gaya inline sheet saat berpindah ke tata letak desktop. */
const syncSheetToViewport = () => {
    if (!sheet || !desktop.matches) {
        return;
    }

    sheetOpen = false;
    root.classList.remove('is-sheet-open');
    sheet.style.transform = '';
    sheet.style.opacity = '';
    backdrop.style.opacity = '0';
};

if (sheet) {
    all('[data-sheet-open]').forEach((el) => el.addEventListener('click', openSheet));
    all('[data-sheet-close]').forEach((el) => el.addEventListener('click', closeSheet));
    backdrop.addEventListener('click', closeSheet);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSheet();
        }
    });

    desktop.addEventListener('change', syncSheetToViewport);
}

/* ------------------------------------------------------------------ */

/** @param {number} duration */
const hideSplash = async (duration) => {
    if (duration > 0) {
        await animate(splash, { opacity: [1, 0], scale: [1, 1.04] }, { duration, ease: easeOut }).finished;
    }

    splash.remove();
    root.classList.remove('is-splashing');
};

const playSplash = async () => {
    const logo = splash.querySelector('[data-splash-logo]');
    const texts = all('[data-splash-text]');
    const ring = splash.querySelector('[data-splash-ring]');

    if (logo) {
        animate(logo, { opacity: [0, 1], scale: [0.8, 1], y: [14, 0] }, { duration: 0.75, ease: easeOut });
    }

    if (texts.length) {
        animate(
            texts,
            { opacity: [0, 1], y: [16, 0] },
            { duration: 0.6, delay: stagger(0.09, { startDelay: 0.2 }), ease: easeOut },
        );
    }

    if (ring) {
        animate(ring, { strokeDashoffset: [150.8, 0] }, { duration: 1.15, delay: 0.3, ease: [0.4, 0, 0.2, 1] });
    }

    await wait(1500);
    await hideSplash(0.55);
};

const boot = async () => {
    if (!splash) {
        root.classList.remove('is-splashing');
    } else if (reduceMotion) {
        await hideSplash(0);
    } else if (splash.hasAttribute('data-splash-skip')) {
        await hideSplash(0);
    } else {
        await playSplash();
    }

    reveal();

    /** Login gagal: langsung buka sheet supaya pesan galat terlihat. */
    if (sheet?.hasAttribute('data-sheet-autoopen') && !desktop.matches) {
        await wait(reduceMotion ? 0 : 260);
        openSheet();
    }
};

boot();
