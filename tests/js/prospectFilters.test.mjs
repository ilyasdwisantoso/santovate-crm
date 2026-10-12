import test from 'node:test';
import assert from 'node:assert/strict';
import {
    DEFAULT_FILTERS, prospectFilterState, prospectFilterQuery,
    prospectActiveChips, removeProspectFilter,
} from '../../resources/js/Utils/prospectFilters.js';

test('default URL excludes empty and default sort values', () => {
    assert.deepEqual(prospectFilterQuery(DEFAULT_FILTERS), {});
});
test('search, batch, and sort survive URL roundtrip', () => {
    const state=prospectFilterState({q:'Parfum',import_batch:'17',sort:'recent'});
    assert.deepEqual(prospectFilterQuery(state),{q:'Parfum',import_batch:'17',sort:'recent'});
});
test('query excludes empty whitespace and keeps only known keys', () => {
    assert.deepEqual(prospectFilterQuery({q:'  ',priority:'tinggi',other:'injected'}),{priority:'tinggi'});
});
test('active chips accurately represent server-side filters', () => {
    const chips=prospectActiveChips({q:'PT Aman',priority:'tinggi',followup:'due',import_batch:'2',sort:'value'},
        {priorities:{tinggi:'Tinggi'}});
    assert.equal(chips.length,5);
    assert.ok(chips.some(c=>c.key==='import_batch'&&c.label==='Import #2'));
});
test('AE never displays admin-only assignment chip', () => {
    const filters={assigned_to:'3'};
    assert.equal(prospectActiveChips(filters,{isAdmin:false}).length,0);
    assert.equal(prospectActiveChips(filters,{isAdmin:true,salesUsers:[{id:3,name:'Dina'}]})[0].label,'Account Executive: Dina');
});
test('remove filter clears only requested key and preserves batch', () => {
    const next=removeProspectFilter({q:'Oud',import_batch:'2',sort:'recent'},'q');
    assert.equal(next.q,'');
    assert.equal(next.import_batch,'2');
    assert.equal(next.sort,'recent');
});
test('clearing sort restores canonical default', () => {
    assert.equal(removeProspectFilter({sort:'value'},'sort').sort,'score');
});
