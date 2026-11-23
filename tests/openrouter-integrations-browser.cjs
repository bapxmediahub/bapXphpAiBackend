'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {test}=require('node:test');
const {chromium}=require('playwright');
const path=require('node:path');
const root=path.resolve(__dirname,'..');
function fragment(enabled){
    const php=[
        "function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}",
        "$secrets=['ai_openrouter_enabled'=>'"+(enabled?'1':'0')+"','ai_openrouter_key'=>'PRIVATE_FIXTURE_KEY'];",
        "$s=file_get_contents('views/admin/integrations.php');",
        "$a=strpos($s,'<fieldset id=\"openrouter-provider\">');",
        "$b=strpos($s,'<fieldset>', $a+1);",
        "if($a===false||$b===false)throw new RuntimeException('OpenRouter fieldset missing');",
        "eval('?>'.substr($s,$a,$b-$a));"
    ].join(' ');
    return execFileSync('php',['-r',php],{cwd:root,encoding:'utf8'});
}
for(const enabled of [false,true])test('OpenRouter integration toggle in native Chromium, enabled='+enabled,async()=>{
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const page=await browser.newPage({viewport:{width:375,height:800}});
    try{
        await page.setContent('<form>'+fragment(enabled)+'</form>');
        const toggle=page.getByRole('checkbox',{name:'Enable OpenRouter first'});
        assert.equal(await toggle.isChecked(),enabled);
        assert.equal(await page.locator('input[name="ai_openrouter_key"]').getAttribute('type'),'password');
        assert.equal(await page.locator('input[name="ai_openrouter_key"]').inputValue(),'');
        assert.doesNotMatch(await page.content(),/PRIVATE_FIXTURE_KEY/);
        await toggle.click();
        assert.equal(await toggle.isChecked(),!enabled);
        await page.locator('input[name="ai_openrouter_key"]').fill('synthetic-test-input');
        const form=await page.locator('form').evaluate(f=>[...new FormData(f).entries()]);
        assert.equal(form.filter(([k])=>k==='ai_openrouter_enabled').at(-1)[1],enabled?'0':'1');
        assert.equal(form.find(([k])=>k==='ai_openrouter_key')[1],'synthetic-test-input');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
    }finally{await browser.close();}
});
