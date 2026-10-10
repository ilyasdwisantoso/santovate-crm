import test from 'node:test';
import assert from 'node:assert/strict';
import { localQrisMatrix } from '../../resources/js/Utils/ipaymuQrisLocal.js';

const sample = '000201010212' + '260500123' + '52045812' + '5303360' + '540515000' + '5802ID' + '5910SANTOVATE1' + '6007JAKARTA' + '6304FFFF';

test('creates QR matrix locally for QRIS EMV payload', () => {
    const result = localQrisMatrix(sample);
    assert.ok(result && result.size > 35);
    assert.ok(result.path.length > 300);
    assert.equal(result.payloadLength, sample.length);
    assert.match(result.path, /^M\d+ \d+h1v1h-1z/);
});
test('truly empty and non-EMV payloads are not shown as QRIS', () => {
    for (const sample of ['', null, undefined, 'https://sandbox.ipaymu.com/qris/x', '000201']) {
        assert.equal(localQrisMatrix(sample), null);
    }
});
test('generated QR matrix is deterministic, without network calls', () => {
    assert.deepEqual(localQrisMatrix(sample), localQrisMatrix(sample));
});
test('invalid oversized QRIS payload returns null safely', () => {
    assert.equal(localQrisMatrix('000201' + '0'.repeat(5000)), null);
});
