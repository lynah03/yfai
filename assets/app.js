import { registerReactControllerComponents } from '@symfony/ux-react';
import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import './styles/theme.css'; // the design-system aliases above
import './styles/dashboard.css';

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            resolve();
        } catch (error) {
            reject(error);
        } finally {
            textarea.remove();
        }
    });
}

function initDashboardCopyButtons() {
    document.querySelectorAll('[data-copy-code]').forEach((button) => {
        if (button.dataset.copyReady === 'true') {
            return;
        }

        button.dataset.copyReady = 'true';

        button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget || '');
            const feedback = document.querySelector(`[data-copy-feedback="${button.dataset.copyTarget}"]`);

            if (!target) {
                return;
            }

            try {
                await copyText(target.textContent.trim());

                if (feedback) {
                    feedback.textContent = 'Copied';
                    window.setTimeout(() => {
                        feedback.textContent = '';
                    }, 1800);
                }
            } catch (error) {
                if (feedback) {
                    feedback.textContent = 'Copy failed';
                }
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardCopyButtons);
} else {
    initDashboardCopyButtons();
}
