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
            const note = document.createElement('p');
            note.className = 'ai-request-status__note';
            note.textContent = 'Request sent. Waiting for an answer using permitted site and account information. This shows request status, not internal model reasoning.';
            summary.append(label, elapsed);
            detail.append(summary, bar, note);
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
                    label.textContent = failed ? 'Request failed' : 'Response ready';
                    note.textContent = failed
                        ? 'The request did not complete successfully. You can retry; no success is implied.'
                        : 'The response is shown below. Elapsed time includes network and server processing; it is not a measure of answer accuracy.';
                    bar.remove();
                    detail.open = false;
                }
            };
        }
    };
})();
