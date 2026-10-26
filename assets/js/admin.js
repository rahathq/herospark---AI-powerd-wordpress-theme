(function () {
    'use strict';
    const button = document.getElementById('hs-generate');
    if (!button || !window.herosparkStudio) return;
    const studio = document.querySelector('.herospark-studio');
    const notice = document.getElementById('hs-generation-result');
    const context = document.getElementById('herospark-context');
    const title = document.getElementById('herospark-title');
    const paragraph = document.getElementById('herospark-paragraph');
    const config = window.herosparkStudio;
    let busy = false;
    const showMessage = (message, success) => {
        notice.hidden = false;
        notice.className = 'notice ' + (success ? 'notice-success' : 'notice-error');
        notice.querySelector('p').textContent = message;
    };
    studio.addEventListener('submit', event => {
        if (busy) event.preventDefault();
    });
    button.addEventListener('click', async () => {
        if (busy) return;
        if (!context.value.trim()) {
            showMessage(config.emptyBrief, false);
            context.focus();
            return;
        }
        busy = true;
        const originalText = button.textContent;
        const submits = Array.from(studio.querySelectorAll('[type="submit"]'));
        submits.forEach(input => { input.disabled = true; });
        button.disabled = true;
        button.textContent = config.generating;
        [context, title, paragraph].forEach(input => { input.readOnly = true; });
        studio.setAttribute('aria-busy', 'true');
        notice.hidden = true;
        try {
            const body = new URLSearchParams({ action: 'herospark_generate', nonce: config.nonce, context: context.value });
            const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.data && result.data.message ? result.data.message : config.failure);
            }
            const copy = result.data && result.data.content;
            if (!copy || typeof copy.title !== 'string' || typeof copy.paragraph !== 'string') {
                throw new Error(config.failure);
            }
            title.value = copy.title;
            paragraph.value = copy.paragraph;
            context.value = copy.context;
            const history = document.getElementById('hs-history-container');
            if (history && typeof result.data.history === 'string') {
                // Only authenticated server-rendered markup with escaped content is returned.
                history.innerHTML = result.data.history;
            }
            showMessage(result.data.message, true);
        } catch (error) {
            showMessage(error instanceof SyntaxError || error instanceof TypeError ? config.failure : error.message, false);
        } finally {
            busy = false;
            submits.forEach(input => { input.disabled = false; });
            button.disabled = false;
            button.textContent = originalText;
            [context, title, paragraph].forEach(input => { input.readOnly = false; });
            studio.removeAttribute('aria-busy');
        }
    });
    window.addEventListener('beforeunload', event => {
        if (busy) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}());
