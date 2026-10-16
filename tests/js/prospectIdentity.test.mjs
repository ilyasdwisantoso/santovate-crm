import test from 'node:test';
import assert from 'node:assert/strict';
import { prospectDisplayName } from '../../resources/js/Utils/prospectIdentity.js';
import { prospectFilterQuery, prospectActiveChips, removeProspectFilter } from '../../resources/js/Utils/prospectFilters.js';
test('individual display keeps company field empty and supports legacy business payloads',()=>{
 const individual={entity_type:'individual',individual_name:'Fixture Person',company_name:null};
 assert.equal(prospectDisplayName(individual),'Fixture Person');
 assert.equal(individual.company_name,null);
 assert.equal(prospectDisplayName({company_name:'PT Fixture'}),'PT Fixture');
 assert.equal(prospectDisplayName(null),'');
});
test('canonical display name and native type filter survive UI boundaries',()=>{
 assert.equal(prospectDisplayName({display_name:'Canonical',company_name:'Old'}),'Canonical');
 assert.deepEqual(prospectFilterQuery({entity_type:'individual'}),{entity_type:'individual'});
 assert.equal(prospectActiveChips({entity_type:'individual'})[0].label,'Jenis: Individu / B2C');
 assert.equal(removeProspectFilter({entity_type:'individual',q:'Fixture'},'q').entity_type,'individual');
});
