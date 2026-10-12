import test from 'node:test';
import assert from 'node:assert/strict';
import {
  shouldExpandProspectAdvanced, normalizedWhatsAppNumber, prospectWhatsAppUrl,
  safeProspectSourceUrl, filterProspectTimeline, visibleProspectTimeline,
} from '../../resources/js/Utils/prospectExperience.js';

test('new prospect starts concise; edit form shows all existing details', () => {
  assert.equal(shouldExpandProspectAdvanced(false, {}), false);
  assert.equal(shouldExpandProspectAdvanced(true, {}), true);
});
test('validation errors in hidden advanced fields make them visible', () => {
  assert.equal(shouldExpandProspectAdvanced(false, {source_url:'invalid'}), true);
  assert.equal(shouldExpandProspectAdvanced(false, {company_name:'required'}), false);
  assert.equal(shouldExpandProspectAdvanced(false, {estimated_budget:'invalid'}), true);
});
test('Indonesian WhatsApp phone numbers normalize correctly', () => {
  assert.equal(normalizedWhatsAppNumber('0812-3456-7890'), '6281234567890');
  assert.equal(normalizedWhatsAppNumber('+62 812 3456 7890'), '6281234567890');
  assert.equal(prospectWhatsAppUrl('812 3456 7890'), 'https://wa.me/6281234567890');
});
test('invalid or empty phone never creates a WhatsApp action', () => {
  assert.equal(prospectWhatsAppUrl(''), null);
  assert.equal(prospectWhatsAppUrl('123'), null);
  assert.equal(prospectWhatsAppUrl('abc'), null);
});
test('external prospect data links accept only HTTP(S) URLs', () => {
  assert.equal(safeProspectSourceUrl('https://example.com/a'), 'https://example.com/a');
  assert.equal(safeProspectSourceUrl('javascript:alert(1)'), null);
  assert.equal(safeProspectSourceUrl('data:text/html,hi'), null);
  assert.equal(safeProspectSourceUrl('//example.com'), null);
});
test('timeline category filtering never mutates original activities', () => {
  const activities = [{type:'note',id:1},{type:'call',id:2},{type:'note',id:3}];
  assert.deepEqual(filterProspectTimeline(activities,'note').map(a=>a.id),[1,3]);
  assert.equal(activities.length,3);
});
test('timeline shows six entries by default and can expand', () => {
  const items = Array.from({length:9}, (_,i)=>({id:i,type:'note'}));
  assert.equal(visibleProspectTimeline(items).visible.length,6);
  assert.equal(visibleProspectTimeline(items).hasMore,true);
  assert.equal(visibleProspectTimeline(items,'all',true).visible.length,9);
});
test('empty timeline and unknown category remain predictable', () => {
  assert.deepEqual(visibleProspectTimeline([]).visible,[]);
  assert.equal(visibleProspectTimeline([{id:1,type:'note'}],'call').matchingCount,0);
  assert.equal(visibleProspectTimeline(null).matchingCount,0);
});
