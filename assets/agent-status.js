/* Shared request feedback for the two existing PHP chat surfaces. */
(function () {
    'use strict';
    window.AgentRequestStatus = {
        start: function (parent) {
            const started = performance.now();
            const detail = document.createElement('details');
            detail.className = 'ai-request-status';
            detail.dataset.state = 'pending';
            const summary = document.createElement('summary');
            const label = document.createElement('span');
            label.className = 'ai-request-status__label';
            label.textContent = 'Thinking…';
            const elapsed = document.createElement('span');
            elapsed.className = 'ai-request-status__time';
            elapsed.setAttribute('aria-hidden', 'true');
            const bar = document.createElement('progress');
            bar.className = 'ai-request-status__progress';
            bar.setAttribute('aria-label', 'Waiting for response');
            summary.append(label, elapsed);
            detail.append(summary, bar);
            parent.append(detail);
            const update = function () { elapsed.textContent = Math.floor((performance.now() - started) / 1000) + ' sec'; };
            update();
            const timer = setInterval(update, 1000);
            let finished = false;
            return {
                finish: function (failed) {
                    if (finished) return;
                    finished = true;
                    clearInterval(timer);
                    update();
                    detail.dataset.state = failed ? 'error' : 'complete';
                    // Once the animation ends, duration is the only visible status.
                    // The response itself presents the success or error outcome.
                    label.hidden = true;
                    summary.setAttribute('aria-label', (failed ? 'Request failed after ' : 'Response completed in ') + elapsed.textContent);
                    bar.remove();
                    detail.open = false;
                }
            };
        }
    };
})();
