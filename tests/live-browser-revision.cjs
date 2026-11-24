'use strict';

const {setTimeout: pause} = require('node:timers/promises');

// Respect retry guidance on rate limits: https://www.rfc-editor.org/rfc/rfc6585#section-4
const retryableStatuses = new Set([429, 502, 503, 504]);
const retryableNetworkErrors = new Set(['ECONNRESET', 'UND_ERR_SOCKET', 'ETIMEDOUT', 'EPIPE']);
const attempts = 3;
const budgetMs = 16000;
const requestMs = 4500;

function retryDelay(response, attempt) {
    const fallbackMs = attempt * 750;
    const raw = response?.headers.get('retry-after')?.trim();
    if (!raw) return fallbackMs;
    const minimumMs = /^\d+$/.test(raw) ? Number(raw) * 1000 : Date.parse(raw) - Date.now();
    return Number.isNaN(minimumMs) ? fallbackMs : Math.max(fallbackMs, minimumMs);
}

async function verifyLiveRevision(origin, expected) {
    if (expected.length !== 40 || /[^a-f0-9]/.test(expected)) throw Error('Expected exact deployed Git SHA');
    const endpoint = new URL('/is-sha-valid', origin);
    endpoint.searchParams.set('sha', expected);
    const deadline = Date.now() + budgetMs;

    for (let attempt = 1; attempt <= attempts; ++attempt) {
        const remainingMs = deadline - Date.now();
        if (remainingMs <= 0) throw Error('Live Git HEAD verification exceeded its deadline');
        const signal = AbortSignal.timeout(Math.min(remainingMs, requestMs));
        let response;
        let failure;
        let data;
        try {
            response = await fetch(endpoint, {
                headers: {'Accept': 'application/json', 'Cache-Control': 'no-cache'},
                redirect: 'error',
                signal,
            });
            if (response.status === 200) data = await response.json();
        } catch (error) {
            if (signal.aborted) {
                failure = 'request timeout';
            } else if (retryableNetworkErrors.has(error.cause?.code)) {
                failure = error.cause.code;
            } else {
                throw Error('Live Git HEAD verification could not read the revision response');
            }
        }

        if (!failure && response.status === 200) {
            if (data?.status !== 'match' || data.match !== true || data.expected_sha !== expected
                || data.deployed_sha !== expected || data.source !== 'git_head') {
                throw Error('Live Git HEAD did not match the expected exact revision');
            }
            return;
        }
        if (!failure) {
            failure = 'HTTP ' + response.status;
            await response.body?.cancel().catch(()=>{});
            if (!retryableStatuses.has(response.status)) {
                throw Error('Live Git HEAD verification failed: ' + failure);
            }
        }
        if (attempt === attempts) {
            throw Error('Live Git HEAD verification failed after ' + attempt + ' attempts: ' + failure);
        }
        const delayMs = retryDelay(response, attempt);
        if (Date.now() + delayMs >= deadline) {
            throw Error('Live Git HEAD verification cannot retry within its deadline: ' + failure);
        }
        console.warn('Live Git HEAD probe attempt ' + attempt + ' received ' + failure + '. Retrying in ' + delayMs + 'ms');
        await pause(delayMs);
    }
}

module.exports = {verifyLiveRevision};
