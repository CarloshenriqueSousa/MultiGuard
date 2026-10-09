/**
 * Configuracao do Tailwind: varre as views Blade e os componentes React para gerar so as classes usadas.
 * Extensao .cjs para funcionar independente do campo "type" do package.json.
 */

module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
