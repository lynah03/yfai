module.exports = {
    content: [
        './assets/**/*.{js,jsx,ts,tsx}',
        './templates/**/*.twig',
    ],
    theme: { extend: {} },
    plugins: [ require('@tailwindcss/forms') ],
};
