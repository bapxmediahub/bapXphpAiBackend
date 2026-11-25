'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const {createServer} = require('node:http');
const {once} = require('node:events');
const path = require('node:path');
const {chromium} = require('playwright');

const root = path.resolve(__dirname, '..');
const csrf = 'synthetic-booking-csrf';
const pagePath = '/pages/private-workshop';
const bookingId = 'a'.repeat(32);
const bookingPath = pagePath + '/booking/' + bookingId;
const file = {id:'f'.repeat(32),name:'Workshop guide.txt',mime:'text/plain',bytes:8};

function renderPhp(kind, data) {
    // Render the actual PHP templates with synthetic inputs and no runtime secrets.
    const source = String.raw`
        function app_path(string $path): string { return getcwd().'/'.$path; }
        function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
        spl_autoload_register(static function (string $class): void {
            require app_path('app/'.str_replace('\\', '/', substr($class, 4)).'.php');
        });
        use App\Services\{AuthoredPageService,CampaignPaymentService};
        extract(json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR));
        $_SESSION = ['csrf_token'=>$csrf];
        if ($argv[1] === 'definition') {
            echo json_encode(CampaignPaymentService::fromEditor($enabled, $amount), JSON_THROW_ON_ERROR);
            return;
        }
        if ($argv[1] === 'status') {
            require app_path('views/public/campaign-booking.php');
            return;
        }
        $html = AuthoredPageService::renderContent($page['content']);
        if ($argv[1] === 'public') {
            require app_path('views/public/authored-page.php');
            return;
        }
        $pages = [$page];
        $form = $page + ['original_slug'=>$page['slug'], 'expected_revision'=>$page['revision']];
        $pageDraftOwner = 'booking-fixture-owner';
        if ($argv[1] === 'preview') $preview = compact('page','html');
        require app_path('views/admin/pages.php');
    `;
    return execFileSync('php', ['-r',source,kind,JSON.stringify({csrf,pagePath,bookingPath,...data})], {cwd:root, encoding:'utf8'});
}

async function fixture() {
    let page = {id:'pg:private-workshop',slug:'private-workshop',title:'Workshop booking',content:'## Visit and learn',
        status:'published',revision:'b'.repeat(32)};
    let bookingStatus = 'pending';
    let submits = 0;
    const server = createServer(async (request, response) => {
        function send(status, body, headers = {}) {
            response.writeHead(status, {'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store',...headers});
            response.end(body);
        }
        const document = markup => '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            + '<link rel="stylesheet" href="/assets/css/band.css"></head><body><main>' + markup + '</main></body></html>';
        try {
            const url = new URL(request.url, 'http://127.0.0.1');
            const pathname = url.pathname;
            if (['/assets/css/band.css','/assets/page-draft.js'].includes(pathname)) {
                return send(200,readFileSync(root+pathname),{'Content-Type':pathname.endsWith('.css')?'text/css':'application/javascript'});
            }
            if (pathname === '/admin/pages') return send(200, document(renderPhp('editor',{page})));
            let data = null;
            if (request.method === 'POST') {
                const chunks = [];
                for await (const chunk of request) chunks.push(chunk);
                const body = Buffer.concat(chunks);
                const contentType = request.headers['content-type'];
                data = contentType?.startsWith('multipart/form-data')
                    ? await new Response(body, {headers:{'Content-Type':contentType}}).formData()
                    : new URLSearchParams(body.toString('utf8'));
                assert.equal(data.get('_csrf'),csrf);
            }
            if (pathname === '/admin/pages/save') {
                const campaignPayment = JSON.parse(renderPhp('definition',{
                    enabled:data.get('campaign_payment_enabled'), amount:data.get('campaign_booking_amount'),
                }));
                assert.equal(campaignPayment.amount_paise,7950);
                const candidate = {...page,campaign_payment:campaignPayment,campaign_downloads:{enabled:true,files:[file]}};
                if (data.get('action') === 'preview') return send(200,document(renderPhp('preview',{page:candidate})));
                page = candidate;
                return send(303,'',{Location:'/admin/pages'});
            }
            if (pathname === pagePath) return send(200,document(renderPhp('public',{page})));
            if (pathname === pagePath+'/booking') {
                assert.equal(data.get('revision'),page.revision);
                assert.deepEqual(Array.from(data.keys()).sort(),['_csrf','revision']);
                submits++;
                return send(303,'',{Location:bookingPath});
            }
            if (pathname === bookingPath) {
                const booking = {title:page.title,mode:'test',amount_paise:page.campaign_payment.amount_paise,
                    status:bookingStatus, payment_expires_at:Math.floor(Date.now()/1000)+1800,
                    provider_url:'https://rzp.io/rzp/no-network-fixture', receipt:{id:'book_'+bookingId},fulfillment:[file]};
                return send(200,renderPhp('status',{booking}));
            }
            if (pathname === bookingPath+'/downloads/'+file.id && bookingStatus === 'captured') {
                return send(200,'fixture\n',{'Content-Type':'text/plain','Content-Disposition':'attachment; filename="Workshop guide.txt"'});
            }
            return send(404,'Not found');
        } catch (error) {
            send(500,String(error));
        }
    });
    server.listen(0,'127.0.0.1');
    await once(server,'listening');
    return {
        server, origin:'http://127.0.0.1:'+server.address().port,
        expire() { bookingStatus = 'expired'; },
        confirm() { bookingStatus = 'captured'; },
        count() { return submits; },
    };
}

for (const width of [1280,375]) test('Campaign payment editor and booking status at '+width+'px',{timeout:30000},async()=>{
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined,headless:true,args:['--no-sandbox']});
    let app;
    try {
        app = await fixture();
        const context = await browser.newContext({viewport:{width,height:850},javaScriptEnabled:false,reducedMotion:'reduce'});
        await context.route('**/*', route => route.request().url().startsWith(app.origin+'/') ? route.continue() : route.abort());
        const page = await context.newPage();
        page.setDefaultTimeout(5000);
        const fits = async () => assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.goto(app.origin+'/admin/pages');
        const toggle = page.getByRole('checkbox',{name:'Enable payment'});
        const amount = page.getByLabel('Booking amount (INR)');
        assert.equal(await amount.isVisible(),false);
        await toggle.focus();
        await toggle.press('Space');
        assert.equal(await toggle.isChecked(),true);
        await toggle.press('Tab');
        assert.equal(await amount.evaluate(input => input === document.activeElement),true);
        await amount.fill('79.50');
        await fits();
        await page.getByRole('button',{name:'Preview',exact:true}).press('Enter');
        assert.equal(await page.getByText('Preview only. Booking is disabled.').isVisible(),true);
        assert.equal(await page.getByRole('button',{name:'Review booking payment'}).count(),0);
        await page.getByRole('button',{name:'Save page',exact:true}).press('Enter');

        await page.goto(app.origin+pagePath);
        assert.equal(await page.getByText('₹79.50 INR').isVisible(),true);
        assert.equal(await page.getByRole('heading',{name:'Send an enquiry'}).count(),0);
        assert.equal(await page.getByText(file.name).count(),0);
        await page.getByRole('button',{name:'Review booking payment'}).press('Enter');
        await page.waitForURL(app.origin+bookingPath);
        assert.equal(app.count(),1);
        assert.equal(await page.getByText('Test payment mode').isVisible(),true);
        assert.equal(await page.getByRole('link',{name:'Open Razorpay in a new tab'}).getAttribute('href'),'https://rzp.io/rzp/no-network-fixture');
        assert.equal(await page.getByRole('link',{name:file.name}).count(),0);
        await fits();
        app.expire();
        await page.getByRole('link',{name:'Check payment status'}).press('Enter');
        assert.equal(await page.getByText('This payment link has expired.',{exact:false}).isVisible(),true);
        assert.equal(await page.getByRole('link',{name:'Open Razorpay in a new tab'}).count(),0);
        app.confirm();
        await page.getByRole('link',{name:'Check payment status'}).press('Enter');
        await page.getByRole('heading',{name:'Payment confirmed'}).waitFor();
        assert.equal(await page.getByRole('link',{name:'Open Razorpay in a new tab'}).count(),0);
        await fits();
        const download = page.getByRole('link',{name:file.name});
        assert.equal(await download.getAttribute('href'),bookingPath+'/downloads/'+file.id);
        const response = await context.request.get(app.origin + await download.getAttribute('href'));
        assert.equal(await response.text(),'fixture\n');
        assert.equal(response.headers()['content-disposition'],'attachment; filename="Workshop guide.txt"');
        await context.close();
    } finally {
        await browser.close();
        if (app) await new Promise(resolve => app.server.close(resolve));
    }
});
