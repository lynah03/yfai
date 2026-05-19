import { registerReactControllerComponents } from '@symfony/ux-react';
import './bootstrap.js';

/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
//import './styles/theme.css'; // the design-system aliases above

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

const quizQuestions = [
    {
        id: 'skin_feel',
        type: 'single',
        title: 'What should your fragrance feel like on your skin?',
        subtitle: 'Choose the atmosphere you want to leave behind.',
        choices: [
            {
                label: 'Clean, close, almost invisible',
                map: {
                    preferred_accords: ['MUSKY', 'POWDERY', 'CLEAN'],
                    preferred_notes: ['Musk', 'Iris', 'Aldehydes'],
                },
            },
            {
                label: 'Warm, sensual, addictive',
                map: {
                    preferred_accords: ['AMBERY', 'GOURMAND', 'VANILLA'],
                    preferred_notes: ['Vanilla', 'Amber', 'Tonka Bean'],
                },
            },
            {
                label: 'Dark, textured, magnetic',
                map: {
                    preferred_accords: ['WOODY', 'LEATHERY', 'SMOKY'],
                    preferred_notes: ['Oud', 'Leather', 'Patchouli'],
                },
            },
            {
                label: 'Bright, fresh, luminous',
                map: {
                    preferred_accords: ['CITRUS', 'AROMATIC', 'FRESH'],
                    preferred_notes: ['Bergamot', 'Neroli', 'Citrus'],
                },
            },
        ],
    },
    {
        id: 'occasion',
        type: 'single',
        title: 'Where will you wear it most?',
        subtitle: 'The setting changes the way a fragrance speaks.',
        choices: [
            { label: 'Every day, effortlessly', map: { preferred_occasions: ['CASUAL'] } },
            { label: 'Work, meetings, polished days', map: { preferred_occasions: ['WORK'] } },
            { label: 'Dates, intimacy, after dark', map: { preferred_occasions: ['DATE', 'EVENING'] } },
            { label: 'Formal evenings and special moments', map: { preferred_occasions: ['FORMAL', 'EVENING'] } },
            { label: 'Movement, freshness, daytime energy', map: { preferred_occasions: ['SPORT', 'CASUAL'] } },
        ],
    },
    {
        id: 'season',
        type: 'single',
        title: 'Which season does your scent belong to?',
        subtitle: 'Think of temperature, fabric, light.',
        choices: [
            {
                label: 'Spring skin — soft, floral, transparent',
                map: { preferred_seasons: ['SPRING'], preferred_accords: ['FLORAL', 'MUSKY'] },
            },
            {
                label: 'Summer air — bright, clean, radiant',
                map: { preferred_seasons: ['SUMMER'], preferred_accords: ['CITRUS', 'FRESH', 'AROMATIC'] },
            },
            {
                label: 'Autumn warmth — amber, woods, texture',
                map: { preferred_seasons: ['FALL'], preferred_accords: ['AMBERY', 'WOODY', 'SPICY'] },
            },
            {
                label: 'Winter velvet — deep, rich, enveloping',
                map: { preferred_seasons: ['WINTER'], preferred_accords: ['GOURMAND', 'AMBERY', 'WOODY'] },
            },
        ],
    },
    {
        id: 'preferred_notes',
        type: 'multi',
        min: 1,
        max: 5,
        target: 'preferred_notes',
        title: 'Which notes are you naturally drawn to?',
        subtitle: 'Select up to five.',
        options: [
            'Rose',
            'Jasmine',
            'Iris',
            'Orange Blossom',
            'Musk',
            'Vanilla',
            'Amber',
            'Tonka Bean',
            'Sandalwood',
            'Cedarwood',
            'Bergamot',
            'Neroli',
            'Vetiver',
            'Patchouli',
            'Oud',
            'Leather',
        ],
    },
    {
        id: 'disliked_notes',
        type: 'multi',
        min: 0,
        max: 5,
        target: 'disliked_notes',
        title: 'Is there anything you would rather avoid?',
        subtitle: 'Optional. Select the notes that do not feel like you.',
        options: [
            'Rose',
            'Jasmine',
            'Vanilla',
            'Amber',
            'Musk',
            'Citrus',
            'Oud',
            'Leather',
            'Patchouli',
            'Smoke',
            'Powder',
            'Sweet notes',
        ],
    },
    {
        id: 'concentration',
        type: 'single',
        title: 'How close should the scent stay?',
        subtitle: 'From a quiet skin scent to a lasting signature.',
        choices: [
            { label: 'Soft and discreet', map: { concentration: 'EDT' } },
            { label: 'Present, refined, balanced', map: { concentration: 'EDP' } },
            { label: 'Intense and long-lasting', map: { concentration: 'PARFUM' } },
            { label: 'Dense, rare, extrait-like', map: { concentration: 'EXTRAIT' } },
        ],
    },
    {
        id: 'budget',
        type: 'single',
        title: 'What budget should guide the recommendations?',
        subtitle: 'Choose the range you feel comfortable with.',
        choices: [
            { label: 'Under €75', map: { budget_min: 0, budget_max: 75 } },
            { label: '€75 – €150', map: { budget_min: 75, budget_max: 150 } },
            { label: '€150 – €250', map: { budget_min: 150, budget_max: 250 } },
            { label: '€250+', map: { budget_min: 250, budget_max: null } },
            { label: 'No budget limit', map: { omitBudget: true } },
        ],
    },
    {
        id: 'gender',
        type: 'single',
        title: 'How should the fragrance be styled?',
        subtitle: 'Not identity — just the world you want the scent to lean toward.',
        choices: [
            { label: 'Feminine-coded', map: { gender: 'FEMALE' } },
            { label: 'Masculine-coded', map: { gender: 'MALE' } },
            { label: 'Genderless', map: { gender: 'NONBINARY' } },
            { label: 'Do not let gender guide it', map: { gender: 'UNDISCLOSED' } },
        ],
    },
];

const loadingLines = [
    'Reading your olfactive preferences.',
    'Matching notes, seasons and skin presence.',
    'Selecting your closest signatures.',
];

function initYfaiMenu() {
    const menu = document.querySelector('[data-yfai-menu]');

    if (!menu || menu.dataset.yfaiMenuReady === 'true') {
        return;
    }

    menu.dataset.yfaiMenuReady = 'true';

    const toggle = menu.querySelector('[data-yfai-menu-toggle]');
    const panel = menu.querySelector('#yfai-menu-panel');
    const closeTriggers = menu.querySelectorAll('[data-yfai-menu-close]');
    const links = menu.querySelectorAll('.yfai-menu-link');
    const focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let lastFocusedElement = null;

    if (!toggle || !panel) {
        return;
    }

    const isOpen = () => menu.classList.contains('is-open');

    const openMenu = () => {
        lastFocusedElement = document.activeElement;
        menu.classList.add('is-open');
        document.body.classList.add('yfai-menu-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation');
        panel.setAttribute('aria-hidden', 'false');

        const firstFocusable = panel.querySelector(focusableSelector);
        (firstFocusable || toggle).focus({ preventScroll: true });
    };

    const closeMenu = () => {
        menu.classList.remove('is-open');
        document.body.classList.remove('yfai-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');
        panel.setAttribute('aria-hidden', 'true');

        if (lastFocusedElement) {
            lastFocusedElement.focus({ preventScroll: true });
        }
    };

    toggle.addEventListener('click', () => {
        if (isOpen()) {
            closeMenu();
            return;
        }

        openMenu();
    });

    closeTriggers.forEach((trigger) => {
        trigger.addEventListener('click', closeMenu);
    });

    links.forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (!isOpen()) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements = [toggle, ...panel.querySelectorAll(focusableSelector)];
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
        }

        if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    });
}

function initYfaiQuiz() {
    const root = document.querySelector('[data-yfai-quiz]');

    if (!root) {
        return;
    }

    if (root.dataset.yfaiQuizReady === 'true') {
        return;
    }

    root.dataset.yfaiQuizReady = 'true';

    const apiUrl = root.dataset.recommendUrl;
    const panel = root.querySelector('[data-yfai-question-panel]');
    const meta = root.querySelector('.yfai-quiz-meta');
    const progress = root.querySelector('[data-yfai-progress]');
    const title = root.querySelector('[data-yfai-question-title]');
    const subtitle = root.querySelector('[data-yfai-question-subtitle]');
    const options = root.querySelector('[data-yfai-options]');
    const nextButton = root.querySelector('[data-yfai-next]');
    const backButton = root.querySelector('[data-yfai-back]');
    const error = root.querySelector('[data-yfai-error]');
    const loading = root.querySelector('[data-yfai-loading]');
    const loadingLine = root.querySelector('[data-yfai-loading-line]');
    const results = root.querySelector('[data-yfai-results]');
    const resultsList = root.querySelector('[data-yfai-results-list]');
    const restartButtons = root.querySelectorAll('[data-yfai-restart]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const transitionMs = prefersReducedMotion ? 0 : 430;
    const answers = new Map();
    let currentIndex = 0;
    let isTransitioning = false;
    let loadingTimer = null;

    if (!apiUrl || !panel || !progress || !title || !subtitle || !options || !nextButton || !backButton || !loading || !results || !resultsList) {
        return;
    }

    const setError = (message = '') => {
        if (!error) {
            return;
        }

        error.textContent = message;
        error.classList.toggle('is-visible', message !== '');
    };

    const pad = (value) => String(value).padStart(2, '0');

    const renderQuestion = () => {
        const question = quizQuestions[currentIndex];
        const storedAnswer = answers.get(question.id);

        root.classList.remove('has-results', 'is-loading');
        panel.hidden = false;
        if (meta) {
            meta.hidden = false;
        }
        loading.hidden = true;
        results.hidden = true;

        progress.textContent = `${pad(currentIndex + 1)} / ${pad(quizQuestions.length)}`;
        backButton.disabled = currentIndex === 0;
        title.textContent = question.title;
        subtitle.textContent = question.subtitle;
        options.innerHTML = '';
        setError();

        if (question.type === 'single') {
            nextButton.hidden = true;
            question.choices.forEach((choice, choiceIndex) => {
                const button = createChoiceButton(choice.label);
                button.setAttribute('aria-pressed', storedAnswer?.index === choiceIndex ? 'true' : 'false');
                button.addEventListener('click', () => {
                    answers.set(question.id, { index: choiceIndex, map: choice.map });
                    options.querySelectorAll('.yfai-quiz-choice').forEach((item) => item.setAttribute('aria-pressed', 'false'));
                    button.setAttribute('aria-pressed', 'true');
                    window.setTimeout(() => advance(), prefersReducedMotion ? 0 : 220);
                });
                options.appendChild(button);
            });
            return;
        }

        nextButton.hidden = false;
        nextButton.textContent = currentIndex === quizQuestions.length - 1 ? 'Reveal my edit' : 'Continue';
        const selected = Array.isArray(storedAnswer?.values) ? storedAnswer.values : [];

        question.options.forEach((option) => {
            const button = createChoiceButton(option);
            button.setAttribute('aria-pressed', selected.includes(option) ? 'true' : 'false');
            button.addEventListener('click', () => {
                const current = new Set(answers.get(question.id)?.values || []);

                if (current.has(option)) {
                    current.delete(option);
                } else if (current.size < question.max) {
                    current.add(option);
                } else {
                    setError(`Select up to ${question.max}.`);
                    return;
                }

                answers.set(question.id, { values: [...current], target: question.target });
                setError();
                button.setAttribute('aria-pressed', current.has(option) ? 'true' : 'false');
            });
            options.appendChild(button);
        });
    };

    const createChoiceButton = (label) => {
        const button = document.createElement('button');
        button.className = 'yfai-quiz-choice';
        button.type = 'button';
        button.textContent = label;

        return button;
    };

    const validateCurrentQuestion = () => {
        const question = quizQuestions[currentIndex];
        const answer = answers.get(question.id);

        if (question.type === 'single') {
            if (!answer) {
                setError('Choose one answer to continue.');
                return false;
            }

            return true;
        }

        const selected = Array.isArray(answer?.values) ? answer.values : [];

        if (selected.length < question.min) {
            setError(question.min === 0 ? '' : 'Choose at least one note to continue.');
            return question.min === 0;
        }

        if (selected.length > question.max) {
            setError(`Select up to ${question.max}.`);
            return false;
        }

        return true;
    };

    const transitionTo = (nextIndex) => {
        if (isTransitioning || nextIndex < 0 || nextIndex >= quizQuestions.length) {
            return;
        }

        isTransitioning = true;
        panel.classList.add('is-leaving');

        window.setTimeout(() => {
            currentIndex = nextIndex;
            renderQuestion();
            panel.classList.remove('is-leaving');
            panel.classList.add('is-entering');

            window.requestAnimationFrame(() => {
                panel.classList.remove('is-entering');
                isTransitioning = false;
            });
        }, transitionMs);
    };

    const advance = () => {
        if (!validateCurrentQuestion()) {
            return;
        }

        if (currentIndex === quizQuestions.length - 1) {
            submitQuiz();
            return;
        }

        transitionTo(currentIndex + 1);
    };

    const goBack = () => {
        if (currentIndex === 0 || isTransitioning) {
            return;
        }

        transitionTo(currentIndex - 1);
    };

    const buildPayload = () => {
        const payload = {
            preferred_notes: [],
            disliked_notes: [],
            preferred_brands: [],
            preferred_accords: [],
            preferred_seasons: [],
            preferred_occasions: [],
            limit: 5,
            maxReasons: 5,
        };

        answers.forEach((answer, questionId) => {
            const question = quizQuestions.find((item) => item.id === questionId);

            if (!question) {
                return;
            }

            if (question.type === 'multi') {
                payload[question.target].push(...(answer.values || []));
                return;
            }

            applyMap(payload, answer.map || {});
        });

        [
            'preferred_notes',
            'disliked_notes',
            'preferred_brands',
            'preferred_accords',
            'preferred_seasons',
            'preferred_occasions',
        ].forEach((field) => {
            payload[field] = [...new Set(payload[field])];
        });

        return payload;
    };

    const applyMap = (payload, map) => {
        Object.entries(map).forEach(([key, value]) => {
            if (key === 'omitBudget') {
                delete payload.budget_min;
                delete payload.budget_max;
                return;
            }

            if (Array.isArray(value)) {
                if (!Array.isArray(payload[key])) {
                    payload[key] = [];
                }
                payload[key].push(...value);
                return;
            }

            payload[key] = value;
        });
    };

    const submitQuiz = async () => {
        if (isTransitioning) {
            return;
        }

        isTransitioning = true;
        panel.classList.add('is-leaving');

        window.setTimeout(async () => {
            panel.hidden = true;
            if (meta) {
                meta.hidden = true;
            }
            root.classList.add('is-loading');
            loading.hidden = false;
            startLoadingLines();

            try {
                const [data] = await Promise.all([requestRecommendations(buildPayload()), delay(1400)]);
                stopLoadingLines();
                showResults(data.results || []);
            } catch (requestError) {
                stopLoadingLines();
                showLoadError(requestError);
            } finally {
                isTransitioning = false;
            }
        }, transitionMs);
    };

    const requestRecommendations = async (payload) => {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'Unable to compose your scent profile.');
        }

        return data;
    };

    const startLoadingLines = () => {
        let index = 0;

        if (loadingLine) {
            loadingLine.textContent = loadingLines[index];
        }

        loadingTimer = window.setInterval(() => {
            index = (index + 1) % loadingLines.length;

            if (loadingLine) {
                loadingLine.textContent = loadingLines[index];
            }
        }, 720);
    };

    const stopLoadingLines = () => {
        if (loadingTimer) {
            window.clearInterval(loadingTimer);
            loadingTimer = null;
        }
    };

    const showLoadError = (requestError) => {
        loading.hidden = true;
        if (meta) {
            meta.hidden = false;
        }
        panel.hidden = false;
        panel.classList.remove('is-leaving');
        setError(requestError.message || 'Unable to compose your scent profile.');
    };

    const showResults = (items) => {
        root.classList.remove('is-loading');
        root.classList.add('has-results');
        loading.hidden = true;
        panel.hidden = true;
        if (meta) {
            meta.hidden = true;
        }
        results.hidden = false;
        resultsList.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'yfai-results-empty';
            empty.textContent = 'No recommendation could be composed yet. Restart the consultation and adjust your profile.';
            resultsList.appendChild(empty);
            return;
        }

        items.forEach((item) => {
            resultsList.appendChild(createResultCard(item));
        });

        window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    };

    const createResultCard = (item) => {
        const article = document.createElement('article');
        article.className = 'yfai-result-card';

        const media = document.createElement('div');
        media.className = 'yfai-result-media';

        if (item.image) {
            const image = document.createElement('img');
            image.src = item.image;
            image.alt = `${item.brand || 'YFAI'} ${item.name || 'fragrance'}`;
            image.loading = 'lazy';
            image.addEventListener('error', () => {
                media.innerHTML = '';
                media.appendChild(createImagePlaceholder());
            });
            media.appendChild(image);
        } else {
            media.appendChild(createImagePlaceholder());
        }

        const body = document.createElement('div');
        body.className = 'yfai-result-body';

        const match = document.createElement('p');
        match.className = 'yfai-result-match';
        match.textContent = `${item.matchPercent || 80}% match`;
        body.appendChild(match);

        const name = document.createElement('h3');
        name.textContent = item.name || 'Unnamed fragrance';
        body.appendChild(name);

        const brand = document.createElement('p');
        brand.className = 'yfai-result-brand';
        brand.textContent = item.brand || 'Independent house';
        body.appendChild(brand);

        if (item.shortDescription || item.description) {
            const description = document.createElement('p');
            description.className = 'yfai-result-description';
            description.textContent = item.shortDescription || item.description;
            body.appendChild(description);
        }

        if (item.displayReason) {
            const reason = document.createElement('p');
            reason.className = 'yfai-result-reason';
            reason.textContent = item.displayReason;
            body.appendChild(reason);
        }

        const metaList = createResultMeta(item);
        if (metaList) {
            body.appendChild(metaList);
        }

        if (Array.isArray(item.notes) && item.notes.length > 0) {
            const notes = document.createElement('div');
            notes.className = 'yfai-result-notes';
            item.notes.slice(0, 5).forEach((note) => {
                const chip = document.createElement('span');
                chip.textContent = note.name;
                notes.appendChild(chip);
            });
            body.appendChild(notes);
        }

        if (item.productUrl) {
            const link = document.createElement('a');
            link.className = 'yfai-result-link';
            link.href = item.productUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Explore the scent';
            body.appendChild(link);
        }

        article.appendChild(media);
        article.appendChild(body);

        return article;
    };

    const createImagePlaceholder = () => {
        const placeholder = document.createElement('div');
        placeholder.className = 'yfai-result-placeholder';
        placeholder.textContent = 'YFAI';

        return placeholder;
    };

    const createResultMeta = (item) => {
        const metaItems = [];

        if (item.concentration) {
            metaItems.push(['Concentration', item.concentration]);
        }

        if (item.price?.formatted) {
            metaItems.push(['Price', item.price.formatted]);
        }

        if (Array.isArray(item.accords) && item.accords.length > 0) {
            metaItems.push(['Accords', item.accords.slice(0, 3).join(', ')]);
        }

        if (metaItems.length === 0) {
            return null;
        }

        const list = document.createElement('dl');
        list.className = 'yfai-result-meta';

        metaItems.forEach(([label, value]) => {
            const term = document.createElement('dt');
            term.textContent = label;
            const description = document.createElement('dd');
            description.textContent = value;
            list.appendChild(term);
            list.appendChild(description);
        });

        return list;
    };

    const restart = () => {
        answers.clear();
        currentIndex = 0;
        isTransitioning = false;
        stopLoadingLines();
        renderQuestion();
        window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    };

    nextButton.addEventListener('click', advance);
    backButton.addEventListener('click', goBack);
    restartButtons.forEach((button) => button.addEventListener('click', restart));

    renderQuestion();
}

function delay(ms) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

function bootYfaiPublicPage() {
    if (!document.querySelector('[data-yfai-quiz]')) {
        return;
    }

    initYfaiMenu();
    initYfaiQuiz();
}

document.addEventListener('DOMContentLoaded', bootYfaiPublicPage);
document.addEventListener('turbo:load', bootYfaiPublicPage);
