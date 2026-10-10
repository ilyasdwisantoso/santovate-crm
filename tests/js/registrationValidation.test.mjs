import test from 'node:test';
import assert from 'node:assert/strict';
import { registrationPasswordError, registrationPasswordMismatch } from '../../resources/js/Utils/registrationValidation.js';

test('matching passwords do not trigger a false mismatch', () => {
    assert.equal(registrationPasswordMismatch('Password12345', 'Password12345'), false);
    assert.equal(registrationPasswordError('Password12345', 'Password12345'), null);
});

test('case differences and whitespace differences are rejected, not normalized', () => {
    assert.equal(registrationPasswordMismatch('Password12345', 'password12345'), true);
    assert.equal(registrationPasswordMismatch('Password12345', 'Password12345 '), true);
    assert.match(registrationPasswordError('Password12345', 'Password12345 '), /harus sama/);
});

test('empty fields use the required browser/server validation rather than mismatch', () => {
    assert.equal(registrationPasswordMismatch('', ''), false);
    assert.equal(registrationPasswordMismatch('Password12345', ''), false);
});
