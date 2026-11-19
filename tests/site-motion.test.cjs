'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const source=path.join(__dirname,'../assets/site-motion.js');
function load(){assert.ok(fs.existsSync(source),'Shared progressively-enhanced motion module must exist');return require(source);}
test('motion is disabled by reduced motion, data saving, hidden tabs and unsupported APIs',()=>{
 const {motionAllowed}=load();const normal={reducedMotion:false,saveData:false,hidden:false,supported:true};
 assert.equal(motionAllowed(normal),true);
 for(const condition of ['reducedMotion','saveData','hidden'])assert.equal(motionAllowed({...normal,[condition]:true}),false);
 assert.equal(motionAllowed({...normal,supported:false}),false);
});
test('entrance is bounded, never hides content completely, and leaves no final style override',()=>{
 const {entrance}=load();const recipe=entrance(999,'public');
 assert.ok(recipe.options.duration<=360);assert.ok(recipe.options.delay<=120);
 assert.ok(recipe.keyframes.every(f=>f.opacity>0));assert.equal(recipe.keyframes.at(-1).opacity,1);
 assert.equal(recipe.options.fill,'none');assert.equal(recipe.keyframes.at(-1).transform,'none');
 assert.ok(!recipe.keyframes.some(f=>['width','height','top','left'].some(k=>Object.hasOwn(f,k))));
});
test('operational interfaces use shorter and shallower transitions than marketing',()=>{
 const {entrance}=load();const editorial=entrance(0,'public'),admin=entrance(0,'admin');
 assert.ok(admin.options.duration<editorial.options.duration);
 assert.ok(parseFloat(admin.keyframes[0].transform.match(/[-\d.]+/)[0])<parseFloat(editorial.keyframes[0].transform.match(/[-\d.]+/)[0]));
});
test('stagger rejects invalid indexes and cannot add slow waits to large catalogues',()=>{
 const {entrance}=load();assert.equal(entrance(-100,'public').options.delay,0);
 assert.equal(entrance(Number.NaN,'public').options.delay,0);
 assert.ok(entrance(1000000,'public').options.delay<=120);
});
test('scroll-to-top visibility is based on real viewport progress',()=>{
 const {showReturnControl}=load();assert.equal(showReturnControl(0,812),false);
 assert.equal(showReturnControl(100,812),false);assert.equal(showReturnControl(1000,812),true);
 assert.equal(showReturnControl(Number.NaN,812),false);
});

test('homepage product photos move within the image frame without stagger or fading',()=>{
 const {entrance}=load();const recipe=entrance(10,'product-photo');
 assert.equal(recipe.options.delay,0);
 assert.equal(recipe.options.fill,'none');
 assert.ok(recipe.options.duration<=360);
 assert.deepEqual(recipe.keyframes,[{transform:'scale(1.025)'},{transform:'none'}]);
});

test('public section headings enter once with bounded movement and no delay',()=>{
 const {entrance}=load();const recipe=entrance(99,'section-heading');
 assert.equal(recipe.options.delay,0);
 assert.equal(recipe.options.fill,'none');
 assert.ok(recipe.options.duration<=360);
 assert.deepEqual(recipe.keyframes,[{opacity:.92,transform:'translateY(8px)'},{opacity:1,transform:'none'}]);
});
