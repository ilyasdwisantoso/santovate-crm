import test from 'node:test';
import assert from 'node:assert/strict';
import { paymentIsComplete, paymentIsTerminal } from '../../resources/js/Utils/paymentStatusPolicy.js';

test('only paid AND activated marks checkout complete', () => {
    assert.equal(paymentIsComplete({status:'pending',active:true}), false);
    assert.equal(paymentIsComplete({status:'paid',active:false}), false);
    assert.equal(paymentIsComplete({status:'paid',active:true}), true);
    assert.equal(paymentIsComplete({status:'pending_verification',active:true}), false);
    assert.equal(paymentIsComplete(null), false);
});

test('manual review and negative statuses stop polling without success', () => {
    for (const status of ['failed','expired','cancelled','canceled','pending_verification']) {
        assert.equal(paymentIsTerminal({status}), true, status);
    }
    for (const status of ['pending','paid','processing','unknown']) {
        assert.equal(paymentIsTerminal({status}), false, status);
    }
});
