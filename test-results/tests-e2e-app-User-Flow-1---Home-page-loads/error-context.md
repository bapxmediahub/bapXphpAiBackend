# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/app.spec.js >> User Flow >> 1 - Home page loads
- Location: tests/e2e/app.spec.js:7:5

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.innerHTML: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('#root')

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - link "Sri Panchami Spiritual logo Sri Panchami Spiritual" [ref=e3] [cursor=pointer]:
      - /url: /
      - img "Sri Panchami Spiritual logo" [ref=e4]
      - generic [ref=e5]: Sri Panchami Spiritual
    - navigation [ref=e6]:
      - link "Home" [ref=e7] [cursor=pointer]:
        - /url: /
      - link "Shop" [ref=e8] [cursor=pointer]:
        - /url: /shop
      - link "Temples" [ref=e9] [cursor=pointer]:
        - /url: /temples
      - link "Astrologers" [ref=e10] [cursor=pointer]:
        - /url: /astrologers
      - link "About Us" [ref=e11] [cursor=pointer]:
        - /url: /about
      - link "Contact Us" [ref=e12] [cursor=pointer]:
        - /url: /contact
      - link "Login" [ref=e13] [cursor=pointer]:
        - /url: /login
    - link "Cart" [ref=e15] [cursor=pointer]:
      - /url: /cart
      - img [ref=e16]
  - main [ref=e20]:
    - generic [ref=e21]:
      - generic [ref=e22]:
        - generic [ref=e23]: Blessings · Protection · Prosperity
        - heading "Buy Original Rudraksha, Pooja Items & Spiritual Products Online in Chennai" [level=1] [ref=e24]
        - paragraph [ref=e25]: Authentic certified rudraksha, sacred pooja samagri, spiritual jewellery, and expert Vedic astrology consultation — trusted by 500+ devotees across India. Free shipping on all orders.
        - generic [ref=e26]:
          - link "Shop Spiritual Products" [ref=e27] [cursor=pointer]:
            - /url: /shop
          - link "Book Astrology Consultation" [ref=e28] [cursor=pointer]:
            - /url: /astrologers
        - generic [ref=e29]:
          - generic [ref=e30]:
            - generic [ref=e31]: 500+
            - generic [ref=e32]: Happy Devotees
          - generic [ref=e33]:
            - generic [ref=e34]: 14+
            - generic [ref=e35]: Sacred Categories
          - generic [ref=e36]:
            - generic [ref=e37]: "3"
            - generic [ref=e38]: Expert Astrologers
      - img "Sri Maha Varahi Amman — Divine deity worshipped at Sri Panchami Spiritual, Chennai" [ref=e40]
    - generic [ref=e41]:
      - generic [ref=e42]:
        - img [ref=e43]
        - text: Secure Payments
      - generic [ref=e46]:
        - img [ref=e47]
        - text: Free Shipping
      - generic [ref=e50]:
        - img [ref=e51]
        - text: 100% Authentic
      - generic [ref=e54]:
        - img [ref=e55]
        - text: Blessed & Energized
    - generic [ref=e59]:
      - generic [ref=e60]:
        - heading "✧ Shop by Category" [level=2] [ref=e61]
        - paragraph [ref=e62]: Curated collections of authentic spiritual products for every need — from rudraksha malas to complete pooja kits
      - generic [ref=e63]:
        - link "Buy Sacred Emblems online in Chennai Sacred Emblems Divine dollar pendants featuring powerful deities for protection and blessings." [ref=e64] [cursor=pointer]:
          - /url: /shop?category=sacred-emblems
          - img "Buy Sacred Emblems online in Chennai" [ref=e66]
          - heading "Sacred Emblems" [level=3] [ref=e67]
          - paragraph [ref=e68]: Divine dollar pendants featuring powerful deities for protection and blessings.
        - link "Buy Spiritual Jewelry online in Chennai Spiritual Jewelry Exquisite wearable sacred jewelry for daily devotion and spiritual connection." [ref=e69] [cursor=pointer]:
          - /url: /shop?category=jewelry
          - img "Buy Spiritual Jewelry online in Chennai" [ref=e71]
          - heading "Spiritual Jewelry" [level=3] [ref=e72]
          - paragraph [ref=e73]: Exquisite wearable sacred jewelry for daily devotion and spiritual connection.
    - generic [ref=e74]:
      - generic [ref=e75]:
        - heading "✧ Featured Spiritual Products" [level=2] [ref=e76]
        - link "View All Products" [ref=e77] [cursor=pointer]:
          - /url: /shop
      - generic [ref=e78]:
        - article [ref=e79]:
          - generic [ref=e80]:
            - img "Varahi Amman Dollar — Buy online at Sri Panchami Spiritual, Chennai" [ref=e81]
            - generic [ref=e82]: Sale
          - generic [ref=e83]:
            - heading "Varahi Amman Dollar" [level=3] [ref=e84]
            - paragraph [ref=e85]: A sacred dollar pendant featuring Goddess Varahi, one of the powerful Matrika deities. Crafted with divine precision for protection and spiritual strength.
            - generic [ref=e86]:
              - generic [ref=e87]: ₹2999
              - generic [ref=e88]: ₹3499
              - generic [ref=e89]: "-14%"
            - generic [ref=e90]:
              - link "View" [ref=e91] [cursor=pointer]:
                - /url: /product/varahi-amman-dollar
              - button "Add to Cart" [ref=e93] [cursor=pointer]
        - article [ref=e94]:
          - generic [ref=e95]:
            - img "Varahi Amman Ring — Buy online at Sri Panchami Spiritual, Chennai" [ref=e96]
            - generic [ref=e97]: Sale
          - generic [ref=e98]:
            - heading "Varahi Amman Ring" [level=3] [ref=e99]
            - paragraph [ref=e100]: An elegant ring featuring Goddess Varahi, perfect for daily wear. Crafted with premium finish for spiritual connection and divine protection.
            - generic [ref=e101]:
              - generic [ref=e102]: ₹1999
              - generic [ref=e103]: ₹2499
              - generic [ref=e104]: "-20%"
            - generic [ref=e105]:
              - link "View" [ref=e106] [cursor=pointer]:
                - /url: /product/varahi-amman-ring
              - button "Add to Cart" [ref=e108] [cursor=pointer]
        - article [ref=e109]:
          - generic [ref=e110]:
            - img "Murugar Vel Mayil Dollar — Buy online at Sri Panchami Spiritual, Chennai" [ref=e111]
            - generic [ref=e112]: Sale
          - generic [ref=e113]:
            - heading "Murugar Vel Mayil Dollar" [level=3] [ref=e114]
            - paragraph [ref=e115]: A divine dollar featuring Lord Murugan with his sacred vel (spear). Symbolizes courage, wisdom, and victory over obstacles.
            - generic [ref=e116]:
              - generic [ref=e117]: ₹2799
              - generic [ref=e118]: ₹3199
              - generic [ref=e119]: "-13%"
            - generic [ref=e120]:
              - link "View" [ref=e121] [cursor=pointer]:
                - /url: /product/murugar-vel-mayil-dollar
              - button "Add to Cart" [ref=e123] [cursor=pointer]
        - article [ref=e124]:
          - generic [ref=e125]:
            - img "Lingam Dollar — Buy online at Sri Panchami Spiritual, Chennai" [ref=e126]
            - generic [ref=e127]: Sale
          - generic [ref=e128]:
            - heading "Lingam Dollar" [level=3] [ref=e129]
            - paragraph [ref=e130]: A powerful representation of Lord Shiva in his Lingam form. This sacred pendant embodies cosmic energy and spiritual transformation.
            - generic [ref=e131]:
              - generic [ref=e132]: ₹3499
              - generic [ref=e133]: ₹3999
              - generic [ref=e134]: "-13%"
            - generic [ref=e135]:
              - link "View" [ref=e136] [cursor=pointer]:
                - /url: /product/lingam-dollar
              - button "Add to Cart" [ref=e138] [cursor=pointer]
        - article [ref=e139]:
          - generic [ref=e140]:
            - img "Lakshmi Dollar — Buy online at Sri Panchami Spiritual, Chennai" [ref=e141]
            - generic [ref=e142]: Sale
          - generic [ref=e143]:
            - heading "Lakshmi Dollar" [level=3] [ref=e144]
            - paragraph [ref=e145]: A beautiful dollar featuring Goddess Lakshmi, the deity of wealth and prosperity. Brings abundance and spiritual blessings to the wearer.
            - generic [ref=e146]:
              - generic [ref=e147]: ₹2499
              - generic [ref=e148]: ₹2899
              - generic [ref=e149]: "-14%"
            - generic [ref=e150]:
              - link "View" [ref=e151] [cursor=pointer]:
                - /url: /product/lakshmi-dollar
              - button "Add to Cart" [ref=e153] [cursor=pointer]
    - generic [ref=e154]:
      - generic [ref=e155]:
        - generic [ref=e156]: Sacred Spaces · Divine Energy
        - heading "✧ Our Temples in Chennai" [level=2] [ref=e157]
        - paragraph [ref=e158]: Visit our sacred spaces for divine blessings, spiritual awakening, and traditional pooja ceremonies.
      - generic [ref=e159]:
        - article [ref=e160]:
          - img "Sri Maha Varahi Amman Temple — Temple at Sri Panchami Spiritual, Chennai" [ref=e162]
          - heading "Sri Maha Varahi Amman Temple" [level=2] [ref=e163]
          - paragraph [ref=e164]: The divine abode of Goddess Varahi, one of the powerful Matrika deities. Known for protection, prosperity, and spiritual awakening. Devotees experience deep peace and divine grace in this sacred space.
          - paragraph [ref=e165]:
            - img [ref=e166]
            - text: Porur - Vanagaram Main Rd, Odamanagar, Vanagaram, Chennai, Tamil Nadu 600095
        - article [ref=e169]:
          - img "Shri Shiva Vishnu Temple — Temple at Sri Panchami Spiritual, Chennai" [ref=e171]
          - heading "Shri Shiva Vishnu Temple" [level=2] [ref=e172]
          - paragraph [ref=e173]: A sacred space dedicated to Lord Shiva (Kedareswarar) and Lord Vishnu (Srinivasa Perumal), representing the unity of destruction and preservation. One of the few temples where both Shiva and Vishnu shrines coexist in harmony.
          - paragraph [ref=e174]:
            - img [ref=e175]
            - text: 2, Natesan Street, N Usman Rd, T. Nagar, Chennai, Tamil Nadu 600017
      - link "View All Temples" [ref=e179] [cursor=pointer]:
        - /url: /temples
    - generic [ref=e180]:
      - generic [ref=e181]:
        - generic [ref=e182]: Guidance · Clarity · Remedies
        - heading "✧ Expert Vedic Astrology Consultation in Chennai" [level=2] [ref=e183]
        - paragraph [ref=e184]: Consult experienced Vedic astrologers for accurate kundli matching, horoscope reading, career guidance, and personalized remedy recommendations in Tamil and English.
      - generic [ref=e185]:
        - article [ref=e186]:
          - generic [ref=e187]:
            - img "Pandit Rajesh Shastri — Kundli Analysis in Chennai" [ref=e188]
            - generic [ref=e189]:
              - heading "Pandit Rajesh Shastri" [level=3] [ref=e190]
              - paragraph [ref=e191]: Kundli Analysis
          - generic [ref=e192]:
            - generic [ref=e193]:
              - generic [ref=e194]: Experience
              - generic [ref=e195]: 20 yrs
            - generic [ref=e196]:
              - generic [ref=e197]: Languages
              - generic [ref=e198]: Hindi, English
          - generic [ref=e199]:
            - generic [ref=e200]: ₹1100 / session
            - link "Book Now" [ref=e201] [cursor=pointer]:
              - /url: /astrologers/pandit-shastri
        - article [ref=e202]:
          - generic [ref=e203]:
            - img "Acharya Meena — Nadi Astrology in Chennai" [ref=e204]
            - generic [ref=e205]:
              - heading "Acharya Meena" [level=3] [ref=e206]
              - paragraph [ref=e207]: Nadi Astrology
          - generic [ref=e208]:
            - generic [ref=e209]:
              - generic [ref=e210]: Experience
              - generic [ref=e211]: 15 yrs
            - generic [ref=e212]:
              - generic [ref=e213]: Languages
              - generic [ref=e214]: English, Sanskrit
          - generic [ref=e215]:
            - generic [ref=e216]: ₹1500 / session
            - link "Book Now" [ref=e217] [cursor=pointer]:
              - /url: /astrologers/acharya-meena
        - article [ref=e218]:
          - generic [ref=e219]:
            - img "Swamy Vashishtha — Planetary Remediation in Chennai" [ref=e220]
            - generic [ref=e221]:
              - heading "Swamy Vashishtha" [level=3] [ref=e222]
              - paragraph [ref=e223]: Planetary Remediation
          - generic [ref=e224]:
            - generic [ref=e225]:
              - generic [ref=e226]: Experience
              - generic [ref=e227]: 25 yrs
            - generic [ref=e228]:
              - generic [ref=e229]: Languages
              - generic [ref=e230]: Hindi, English
          - generic [ref=e231]:
            - generic [ref=e232]: ₹2000 / session
            - link "Book Now" [ref=e233] [cursor=pointer]:
              - /url: /astrologers/swamy-vashishtha
      - link "Book Astrology Consultation" [ref=e235] [cursor=pointer]:
        - /url: /astrologers
    - generic [ref=e236]:
      - generic [ref=e237]:
        - heading "✧ Why Choose Sri Panchami Spiritual" [level=2] [ref=e238]
        - paragraph [ref=e239]: Chennai's trusted destination for authentic spiritual products and expert astrology guidance
      - generic [ref=e240]:
        - article [ref=e241]:
          - img [ref=e242]
          - heading "100% Authentic Products" [level=3] [ref=e245]
          - paragraph [ref=e246]: Every item sourced with devotion and verified for genuineness
        - article [ref=e247]:
          - img [ref=e248]
          - heading "Expert Astrologers" [level=3] [ref=e250]
          - paragraph [ref=e251]: Experienced Vedic astrologers with proven track record
        - article [ref=e252]:
          - img [ref=e253]
          - heading "Secure Payments" [level=3] [ref=e256]
          - paragraph [ref=e257]: Safe payments via Razorpay with bank-grade encryption
        - article [ref=e258]:
          - img [ref=e259]
          - heading "Free Shipping" [level=3] [ref=e262]
          - paragraph [ref=e263]: Quick and careful delivery across India
  - contentinfo [ref=e264]:
    - generic [ref=e265]:
      - generic [ref=e266]:
        - generic [ref=e267]:
          - generic [ref=e268]: Sri Panchami Spiritual
          - paragraph [ref=e269]: Authentic spiritual products, sacred jewellery, expert Vedic astrology and temple guidance in Chennai, Tamil Nadu. Buy original rudraksha, pooja items, and spiritual accessories online with free shipping across India.
        - generic [ref=e270]:
          - heading "Shop" [level=4] [ref=e271]
          - list [ref=e272]:
            - listitem [ref=e273]:
              - link "All Products" [ref=e274] [cursor=pointer]:
                - /url: /shop
            - listitem [ref=e275]:
              - link "Temples" [ref=e276] [cursor=pointer]:
                - /url: /temples
            - listitem [ref=e277]:
              - link "Astrologers" [ref=e278] [cursor=pointer]:
                - /url: /astrologers
            - listitem [ref=e279]:
              - link "About Us" [ref=e280] [cursor=pointer]:
                - /url: /about
            - listitem [ref=e281]:
              - link "Contact" [ref=e282] [cursor=pointer]:
                - /url: /contact
        - generic [ref=e283]:
          - heading "Services" [level=4] [ref=e284]
          - list [ref=e285]:
            - listitem [ref=e286]:
              - link "Astrology" [ref=e287] [cursor=pointer]:
                - /url: /astrologers
            - listitem [ref=e288]:
              - link "Temples" [ref=e289] [cursor=pointer]:
                - /url: /temples
            - listitem [ref=e290]:
              - link "About Us" [ref=e291] [cursor=pointer]:
                - /url: /about
            - listitem [ref=e292]:
              - link "Contact" [ref=e293] [cursor=pointer]:
                - /url: /contact
        - generic [ref=e294]:
          - heading "Contact" [level=4] [ref=e295]
          - list [ref=e296]:
            - listitem [ref=e297]: 23, 1st Cross Street Kothari Nagar
            - listitem [ref=e298]: Ramapuram, Chennai, Tamil Nadu 600089
            - listitem [ref=e299]:
              - link "sripanchamispiritual@gmail.com" [ref=e300] [cursor=pointer]:
                - /url: mailto:sripanchamispiritual@gmail.com
      - generic [ref=e301]: © 2026 Sri Panchami Spiritual · Chennai, Tamil Nadu
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | const BASE = 'http://localhost:8000';
  4  | 
  5  | test.describe('User Flow', () => {
  6  | 
  7  |     test('1 - Home page loads', async ({ page }) => {
  8  |         console.log('\n=== TEST 1: Home page ===');
  9  |         await page.goto(BASE);
  10 |         await page.waitForTimeout(1500);
  11 |         await page.screenshot({ path: 'tests/e2e/screenshots/01-home.png' });
  12 | 
> 13 |         const rootHTML = await page.locator('#root').innerHTML();
     |                                                      ^ Error: locator.innerHTML: Test timeout of 30000ms exceeded.
  14 |         console.log(`Root content length: ${rootHTML.length}`);
  15 |         console.log(`Has loading text: ${rootHTML.includes('Loading...')}`);
  16 | 
  17 |         // Check if header rendered
  18 |         const header = page.locator('header');
  19 |         console.log(`Header exists: ${await header.count()}`);
  20 | 
  21 |         // Check all visible text
  22 |         const bodyText = await page.locator('body').textContent();
  23 |         const lines = bodyText.split('\n').filter(l => l.trim()).slice(0, 30);
  24 |         console.log('Visible text:');
  25 |         lines.forEach(l => console.log(`  "${l.trim()}"`));
  26 | 
  27 |         expect(rootHTML.length).toBeGreaterThan(50);
  28 |     });
  29 | 
  30 |     test('2 - Navigation menu exists', async ({ page }) => {
  31 |         console.log('\n=== TEST 2: Navigation ===');
  32 |         await page.goto(BASE);
  33 |         await page.waitForTimeout(1500);
  34 |         await page.screenshot({ path: 'tests/e2e/screenshots/02-nav.png' });
  35 | 
  36 |         // Find all anchor tags
  37 |         const links = page.locator('a');
  38 |         const count = await links.count();
  39 |         console.log(`Total links: ${count}`);
  40 | 
  41 |         const linkData = [];
  42 |         for (let i = 0; i < Math.min(count, 30); i++) {
  43 |             const text = await links.nth(i).textContent().catch(() => '');
  44 |             const href = await links.nth(i).getAttribute('href').catch(() => '');
  45 |             const visible = await links.nth(i).isVisible().catch(() => false);
  46 |             if (text.trim() && visible) {
  47 |                 linkData.push({ text: text.trim(), href });
  48 |                 console.log(`  "${text.trim()}" -> ${href}`);
  49 |             }
  50 |         }
  51 | 
  52 |         expect(linkData.length).toBeGreaterThan(0);
  53 |     });
  54 | 
  55 |     test('3 - Astrologers page loads with cards', async ({ page }) => {
  56 |         console.log('\n=== TEST 3: Astrologers ===');
  57 |         await page.goto(BASE + '/astrologers');
  58 |         await page.waitForTimeout(1500);
  59 |         await page.screenshot({ path: 'tests/e2e/screenshots/03-astros.png' });
  60 | 
  61 |         // Check for cards
  62 |         const cards = page.locator('.astrologer-card');
  63 |         const cardCount = await cards.count();
  64 |         console.log(`Astrologer cards: ${cardCount}`);
  65 | 
  66 |         // Check images
  67 |         const imgs = page.locator('img');
  68 |         for (let i = 0; i < Math.min(await imgs.count(), 5); i++) {
  69 |             const src = await imgs.nth(i).getAttribute('src');
  70 |             const box = await imgs.nth(i).boundingBox().catch(() => null);
  71 |             console.log(`  Img: ${src} ${box ? `${Math.round(box.width)}x${Math.round(box.height)}` : ''}`);
  72 |         }
  73 | 
  74 |         // Check buttons
  75 |         const btns = page.locator('button');
  76 |         for (let i = 0; i < Math.min(await btns.count(), 10); i++) {
  77 |             const text = await btns.nth(i).textContent();
  78 |             console.log(`  Button: "${text.trim()}"`);
  79 |         }
  80 | 
  81 |         expect(cardCount).toBeGreaterThan(0);
  82 |     });
  83 | 
  84 |     test('4 - Admin page loads', async ({ page }) => {
  85 |         console.log('\n=== TEST 4: Admin ===');
  86 |         await page.goto(BASE + '/admin');
  87 |         await page.waitForTimeout(1000);
  88 |         await page.screenshot({ path: 'tests/e2e/screenshots/04-admin.png' });
  89 | 
  90 |         const bodyText = await page.locator('body').textContent();
  91 |         const hasLogin = bodyText.toLowerCase().includes('login');
  92 |         const hasAdmin = bodyText.toLowerCase().includes('admin');
  93 |         const hasDashboard = bodyText.toLowerCase().includes('dashboard');
  94 |         console.log(`Has login: ${hasLogin}, admin: ${hasAdmin}, dashboard: ${hasDashboard}`);
  95 | 
  96 |         expect(hasLogin || hasAdmin || hasDashboard).toBeTruthy();
  97 |     });
  98 | });
  99 | 
```