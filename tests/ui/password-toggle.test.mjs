import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';
test('password toggle changes only its target and remains reversible', () => {
 const inputs={password:{type:'password'},password_confirmation:{type:'password'}};
 let listener;
 const document={addEventListener:(event,fn)=>{listener=fn;},getElementById:id=>inputs[id]};
 const code=readFileSync(new URL('../../resources/js/app.js',import.meta.url),'utf8').replace(/^import[^\n]+\n?/,'');
 runInNewContext(code,{document});
 const attributes={};const icon={className:''};
 const button={dataset:{passwordToggle:'password'},setAttribute:(key,value)=>attributes[key]=value,querySelector:()=>icon};
 listener({target:{closest:()=>button}});
 assert.equal(inputs.password.type,'text');assert.equal(inputs.password_confirmation.type,'password');assert.equal(attributes['aria-pressed'],'true');assert.equal(attributes['aria-label'],'Hide password');
 listener({target:{closest:()=>button}});
 assert.equal(inputs.password.type,'password');assert.equal(attributes['aria-pressed'],'false');assert.equal(attributes['aria-label'],'Show password');
 listener({target:{closest:()=>null}});
});
