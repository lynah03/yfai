(function () {
    'use strict';

    var script = document.currentScript || findCurrentScript();

    if (!script) {
        console.warn('[YFAI Concierge] Unable to initialise: script tag not found.');
        return;
    }

    var partner = (script.getAttribute('data-partner') || '').trim();
    var triggerSelector = (script.getAttribute('data-trigger') || '').trim();

    if (!partner) {
        console.warn('[YFAI Concierge] Missing required data-partner attribute.');
        return;
    }

    var scriptOrigin;

    try {
        scriptOrigin = new URL(script.src, window.location.href).origin;
    } catch (error) {
        console.warn('[YFAI Concierge] Unable to read script origin.', error);
        return;
    }

    // TODO: support partner slugs, API keys, allowed domains and partner analytics in the next integration phase.
    var iframeUrl = new URL('/ai-scent-concierge/' + encodeURIComponent(partner), scriptOrigin).toString();

    whenReady(function () {
        mountConcierge({
            partner: partner,
            triggerSelector: triggerSelector,
            iframeUrl: iframeUrl
        });
    });

    function findCurrentScript() {
        var scripts = Array.prototype.slice.call(document.getElementsByTagName('script'));

        for (var index = scripts.length - 1; index >= 0; index -= 1) {
            var item = scripts[index];

            if (item.src && item.src.indexOf('/concierge.js') !== -1) {
                return item;
            }
        }

        return null;
    }

    function whenReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    }

    function mountConcierge(options) {
        var host = document.createElement('div');
        var shadow = host.attachShadow({ mode: 'open' });
        var externalTrigger = null;
        var lastFocusedElement = null;
        var previousBodyOverflow = '';
        var previousHtmlOverflow = '';
        var iframeHasLoaded = false;

        host.setAttribute('data-yfai-concierge-host', '');
        document.body.appendChild(host);

        shadow.innerHTML = buildMarkup(options.partner);

        var floatingTrigger = shadow.querySelector('[data-yfai-concierge-floating]');
        var overlay = shadow.querySelector('[data-yfai-concierge-overlay]');
        var modal = shadow.querySelector('[data-yfai-concierge-modal]');
        var closeButton = shadow.querySelector('[data-yfai-concierge-close]');
        var backdrop = shadow.querySelector('[data-yfai-concierge-backdrop]');
        var iframe = shadow.querySelector('[data-yfai-concierge-iframe]');
        var loading = shadow.querySelector('[data-yfai-concierge-loading]');
        var errorState = shadow.querySelector('[data-yfai-concierge-error]');

        if (options.triggerSelector) {
            externalTrigger = document.querySelector(options.triggerSelector);

            if (!externalTrigger) {
                console.warn('[YFAI Concierge] No element matches data-trigger "' + options.triggerSelector + '". Injecting the fallback trigger.');
            } else {
                floatingTrigger.hidden = true;
                externalTrigger.setAttribute('aria-haspopup', 'dialog');
                externalTrigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    openModal(externalTrigger);
                });
            }
        }

        if (!externalTrigger) {
            floatingTrigger.hidden = false;
            floatingTrigger.addEventListener('click', function () {
                openModal(floatingTrigger);
            });
        }

        closeButton.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);

        iframe.addEventListener('load', function () {
            iframeHasLoaded = true;
            loading.classList.add('yfai-concierge-hidden');
            iframe.classList.add('yfai-concierge-loaded');
        });

        iframe.addEventListener('error', function () {
            loading.classList.add('yfai-concierge-hidden');
            errorState.hidden = false;
        });

        document.addEventListener('keydown', function (event) {
            if (!overlay.classList.contains('yfai-concierge-open')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
                return;
            }

            if (event.key === 'Tab') {
                keepFocusInside(event);
            }
        });

        function openModal(trigger) {
            lastFocusedElement = trigger || document.activeElement;
            previousBodyOverflow = document.body.style.overflow;
            previousHtmlOverflow = document.documentElement.style.overflow;

            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';

            overlay.classList.add('yfai-concierge-open');
            overlay.setAttribute('aria-hidden', 'false');

            if (!iframeHasLoaded && iframe.getAttribute('src') !== options.iframeUrl) {
                loading.classList.remove('yfai-concierge-hidden');
                errorState.hidden = true;
                iframe.setAttribute('src', options.iframeUrl);
            }

            if (lastFocusedElement && lastFocusedElement.setAttribute) {
                lastFocusedElement.setAttribute('aria-expanded', 'true');
            }

            window.setTimeout(function () {
                closeButton.focus({ preventScroll: true });
            }, 40);
        }

        function closeModal() {
            overlay.classList.remove('yfai-concierge-open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = previousBodyOverflow;
            document.documentElement.style.overflow = previousHtmlOverflow;

            if (lastFocusedElement && lastFocusedElement.setAttribute) {
                lastFocusedElement.setAttribute('aria-expanded', 'false');
            }

            if (lastFocusedElement && lastFocusedElement.focus) {
                lastFocusedElement.focus({ preventScroll: true });
            }
        }

        function keepFocusInside(event) {
            var focusable = [closeButton, iframe].filter(function (item) {
                return item && !item.hidden;
            });

            if (focusable.length === 0) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && shadow.activeElement === first) {
                event.preventDefault();
                last.focus();
            }

            if (!event.shiftKey && shadow.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    function buildMarkup(partner) {
        var escapedPartner = escapeHtml(partner);

        return [
            '<style>',
            ':host{all:initial;color-scheme:dark;font-family:"Cormorant Garamond",Georgia,"Times New Roman",serif;}',
            '*,*::before,*::after{box-sizing:border-box;}',
            '.yfai-concierge-floating{position:fixed;right:max(1.25rem,env(safe-area-inset-right));bottom:max(1.25rem,env(safe-area-inset-bottom));z-index:2147483000;min-height:2.9rem;padding:.72rem 1.1rem;border:1px solid rgba(247,241,232,.36);border-radius:999px;background:rgba(2,2,2,.72);box-shadow:0 1.4rem 4rem rgba(0,0,0,.38),inset 0 1px 0 rgba(255,255,255,.08);color:rgba(247,241,232,.9);font:500 1rem/1 "Cormorant Garamond",Georgia,"Times New Roman",serif;cursor:pointer;backdrop-filter:blur(18px);transition:opacity .28s ease,border-color .28s ease,transform .28s ease;}',
            '.yfai-concierge-floating:hover,.yfai-concierge-floating:focus-visible{border-color:rgba(247,241,232,.72);transform:translateY(-1px);}',
            '.yfai-concierge-floating[hidden]{display:none;}',
            '.yfai-concierge-overlay{position:fixed;inset:0;z-index:2147483001;display:grid;place-items:center;padding:clamp(.85rem,2vw,1.5rem);background:rgba(0,0,0,.74);backdrop-filter:blur(10px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .38s ease,visibility 0s linear .38s;}',
            '.yfai-concierge-overlay.yfai-concierge-open{opacity:1;visibility:visible;pointer-events:auto;transition-delay:0s;}',
            '.yfai-concierge-backdrop{position:absolute;inset:0;border:0;background:transparent;cursor:default;}',
            '.yfai-concierge-modal{position:relative;width:min(100%,61rem);height:min(86dvh,48rem);overflow:hidden;border:1px solid rgba(247,241,232,.16);border-radius:1.45rem;background:radial-gradient(ellipse 24rem 12rem at 18% 86%,rgba(255,255,255,.11),transparent 72%),#020202;box-shadow:0 2.5rem 8rem rgba(0,0,0,.62);transform:translateY(.75rem) scale(.985);transition:transform .42s cubic-bezier(.16,1,.3,1);}',
            '.yfai-concierge-open .yfai-concierge-modal{transform:translateY(0) scale(1);}',
            '.yfai-concierge-close{position:absolute;top:1rem;right:1rem;z-index:4;display:grid;width:2.55rem;height:2.55rem;place-items:center;border:1px solid rgba(247,241,232,.22);border-radius:999px;background:rgba(2,2,2,.58);color:rgba(247,241,232,.86);font:400 1.35rem/1 Arial,sans-serif;cursor:pointer;backdrop-filter:blur(16px);transition:opacity .24s ease,border-color .24s ease;}',
            '.yfai-concierge-close:hover,.yfai-concierge-close:focus-visible{border-color:rgba(247,241,232,.62);opacity:.82;}',
            '.yfai-concierge-frame{position:absolute;inset:0;width:100%;height:100%;border:0;background:#020202;opacity:0;transition:opacity .34s ease;}',
            '.yfai-concierge-frame.yfai-concierge-loaded{opacity:1;}',
            '.yfai-concierge-loading,.yfai-concierge-error{position:absolute;inset:0;z-index:2;display:grid;place-items:center;padding:2rem;background:radial-gradient(ellipse 22rem 10rem at 22% 76%,rgba(255,255,255,.12),transparent 70%),#020202;color:rgba(247,241,232,.88);text-align:center;transition:opacity .24s ease;}',
            '.yfai-concierge-error[hidden]{display:none;}',
            '.yfai-concierge-loading.yfai-concierge-hidden{opacity:0;pointer-events:none;}',
            '.yfai-concierge-loading strong,.yfai-concierge-error strong{display:block;margin:0 0 .8rem;font:400 clamp(2.2rem,5vw,4.7rem)/.95 "Cormorant Garamond",Georgia,"Times New Roman",serif;letter-spacing:0;}',
            '.yfai-concierge-loading span,.yfai-concierge-error span{display:block;color:rgba(247,241,232,.54);font:500 .95rem/1.5 "Cormorant Garamond",Georgia,"Times New Roman",serif;}',
            '.yfai-concierge-powered{position:absolute;right:1.05rem;bottom:.82rem;z-index:4;color:rgba(247,241,232,.38);font:500 .76rem/1 "Cormorant Garamond",Georgia,"Times New Roman",serif;letter-spacing:.02em;pointer-events:none;}',
            '@media (max-width:640px){.yfai-concierge-floating{right:max(.85rem,env(safe-area-inset-right));bottom:max(.85rem,env(safe-area-inset-bottom));}.yfai-concierge-overlay{padding:max(.5rem,env(safe-area-inset-top)) max(.5rem,env(safe-area-inset-right)) max(.5rem,env(safe-area-inset-bottom)) max(.5rem,env(safe-area-inset-left));}.yfai-concierge-modal{width:100%;height:calc(100dvh - 1rem);border-radius:1rem;}.yfai-concierge-close{top:.75rem;right:.75rem;width:2.7rem;height:2.7rem;}.yfai-concierge-powered{right:1rem;bottom:.72rem;font-size:.72rem;}}',
            '@media (prefers-reduced-motion:reduce){.yfai-concierge-floating,.yfai-concierge-overlay,.yfai-concierge-modal,.yfai-concierge-frame,.yfai-concierge-loading{transition-duration:1ms!important;}}',
            '</style>',
            '<button class="yfai-concierge-floating" type="button" data-yfai-concierge-floating aria-haspopup="dialog" aria-expanded="false">Find your scent</button>',
            '<div class="yfai-concierge-overlay" data-yfai-concierge-overlay aria-hidden="true">',
            '<button class="yfai-concierge-backdrop" type="button" data-yfai-concierge-backdrop aria-label="Close AI Scent Concierge"></button>',
            '<section class="yfai-concierge-modal" data-yfai-concierge-modal role="dialog" aria-modal="true" aria-label="AI Scent Concierge" tabindex="-1">',
            '<button class="yfai-concierge-close" type="button" data-yfai-concierge-close aria-label="Close AI Scent Concierge">&times;</button>',
            '<div class="yfai-concierge-loading" data-yfai-concierge-loading><div><strong>Composing your scent profile.</strong><span>' + escapedPartner + ' AI Scent Concierge is loading.</span></div></div>',
            '<div class="yfai-concierge-error" data-yfai-concierge-error hidden><div><strong>Concierge unavailable.</strong><span>Please try again in a moment.</span></div></div>',
            '<iframe class="yfai-concierge-frame" data-yfai-concierge-iframe title="' + escapedPartner + ' AI Scent Concierge" allow="clipboard-write"></iframe>',
            '<div class="yfai-concierge-powered">Powered by YourFragrance.AI</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}());
