'use strict';

const assert = require('node:assert/strict');
const {createServer} = require('node:http');
const {once} = require('node:events');
const {test} = require('node:test');
const {verifyLiveRevision} = require('./live-browser-revision.cjs');

const sha = 'a'.repeat(40);
const exact = {status:'match', match:true, expected_sha:sha, deployed_sha:sha, source:'git_head'};

async function withRevisionServer(reply, exercise) {
    const requests = [];
    const server = createServer((request, response) => {
        requests.push({method:request.method, url:request.url, time:Date.now()});
        reply(response, requests.length);
    });
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    try {
        await exercise('http://127.0.0.1:' + server.address().port, requests);
        assert.ok(requests.every(request => request.method === 'GET' && request.url === '/is-sha-valid?sha=' + sha));
    } finally {
        await new Promise(resolve => server.close(resolve));
    }
}

function sendRevision(response, data = exact) {
    response.writeHead(200, {'Content-Type':'application/json'});
    response.end(JSON.stringify(data));
}

test('the live probe recovers a transport reset and verifies an exact Git HEAD', async()=>{
    await withRevisionServer((response, attempt)=>{
        if (attempt === 1) response.destroy();
        else sendRevision(response);
    }, async(origin, requests)=>{
        await verifyLiveRevision(origin, sha);
        assert.equal(requests.length, 2);
    });
});

test('the live probe waits for Retry-After before accepting an exact response', async()=>{
    await withRevisionServer((response, attempt)=>{
        if (attempt === 1) response.writeHead(429, {'Retry-After':'1'}).end();
        else sendRevision(response);
    }, async(origin, requests)=>{
        await verifyLiveRevision(origin, sha);
        assert.equal(requests.length, 2);
        assert.ok(requests[1].time - requests[0].time >= 1000);
    });
});

test('continued HTTP 429 fails after bounded attempts', async()=>{
    await withRevisionServer(response=>response.writeHead(429).end(), async(origin, requests)=>{
        await assert.rejects(verifyLiveRevision(origin, sha), /failed after 3 attempts: HTTP 429/);
        assert.equal(requests.length, 3);
    });
});

test('retry guidance outside the budget stops the probe', async()=>{
    await withRevisionServer(response=>response.writeHead(429, {'Retry-After':'120'}).end(), async(origin, requests)=>{
        await assert.rejects(verifyLiveRevision(origin, sha), /cannot retry within its deadline: HTTP 429/);
        assert.equal(requests.length, 1);
    });
});

test('a wrong checkout fails immediately even if the next response would match', async()=>{
    await withRevisionServer((response, attempt)=>{
        const data = attempt === 1 ? {...exact, status:'mismatch', match:false, deployed_sha:'b'.repeat(40)} : exact;
        sendRevision(response, data);
    }, async(origin, requests)=>{
        await assert.rejects(verifyLiveRevision(origin, sha), /did not match the expected exact revision/);
        assert.equal(requests.length, 1);
    });
});

test('a malformed revision response fails immediately', async()=>{
    await withRevisionServer(response=>response.writeHead(200).end('not JSON'), async(origin, requests)=>{
        await assert.rejects(verifyLiveRevision(origin, sha), /could not read the revision response/);
        assert.equal(requests.length, 1);
    });
});
