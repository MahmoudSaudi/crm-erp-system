import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('layout', () => ({
        sidebarOpen: false,
        collapsed: false,
        isDesktop: window.matchMedia('(min-width: 1024px)').matches,
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',

        get visible() {
            return (this.isDesktop && !this.collapsed) || (!this.isDesktop && this.sidebarOpen);
        },

        init() {
            const media = window.matchMedia('(min-width: 1024px)');

            const onChange = (e) => {
                this.isDesktop = e.matches;
                if (this.isDesktop) {
                    this.sidebarOpen = false;
                }
            };

            if (typeof media.addEventListener === 'function') {
                media.addEventListener('change', onChange);
            } else {
                media.addListener(onChange);
            }
        },

        toggleSidebar() {
            if (this.isDesktop) {
                this.collapsed = !this.collapsed;
            } else {
                this.sidebarOpen = !this.sidebarOpen;
            }
        },

        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';

            if (this.theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            localStorage.setItem('theme', this.theme);
        },
    }));
});

Alpine.start();