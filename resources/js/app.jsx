/**
 * Ponto de entrada do console: inicializa o Inertia, que carrega a pagina React indicada pela rota
 * a partir de resources/js/Pages e a monta na div raiz da view app.blade.php.
 */

import '../css/app.css';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

createInertiaApp({
    resolve: (name) => pages[`./Pages/${name}.jsx`],
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
