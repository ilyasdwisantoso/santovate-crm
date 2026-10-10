import test from 'node:test';
import assert from 'node:assert/strict';
import { trustedIpaymuUrl, qrImageCandidates, paymentTemplateUrl } from '../../resources/js/Utils/paymentQrSources.js';

test('only genuine HTTPS iPaymu hosts are eligible for QR display', () => {
    assert.equal(trustedIpaymuUrl('https://sandbox.ipaymu.com/qris/demo'), 'https://sandbox.ipaymu.com/qris/demo');
    assert.equal(trustedIpaymuUrl('https://my.ipaymu.com/qris/demo'), 'https://my.ipaymu.com/qris/demo');
    for (const url of ['http://sandbox.ipaymu.com/qris/demo', 'https://sandbox.ipaymu.com.evil.com/x', 'https://evil.com/qr', 'javascript:alert(1)', 'data:image/svg+xml,abc', 'https://sandbox.ipaymu.com@evil.com/qr', 'https://sandbox.ipaymu.com:8443/x']) {
        assert.equal(trustedIpaymuUrl(url), null, url);
    }
});

test('localhost uses the direct authenticated merchant QR first then proxy', () => {
    const presentation = { qr_image: 'https://sandbox.ipaymu.com/image/example', qr_proxy_url: 'http://127.0.0.1:8000/subscription/payment-qr?reference=test' };
    assert.deepEqual(qrImageCandidates(presentation, true), [presentation.qr_image, presentation.qr_proxy_url]);
    assert.deepEqual(qrImageCandidates(presentation, false), [presentation.qr_proxy_url, presentation.qr_image]);
});

test('untrusted direct image and iframe template URLs are rejected', () => {
    const presentation = { qr_image: 'https://malicious.example/image', qr_template: 'http://sandbox.ipaymu.com/qris/test', qr_proxy_url: '/subscription/payment-qr' };
    assert.deepEqual(qrImageCandidates(presentation, true), ['/subscription/payment-qr']);
    assert.equal(paymentTemplateUrl(presentation), null);
});

test('trusted template is retained as final QR poster fallback', () => {
    const presentation = { qr_template: 'https://sandbox.ipaymu.com/qris/demo', qr_image: null };
    assert.equal(paymentTemplateUrl(presentation), presentation.qr_template);
    assert.deepEqual(qrImageCandidates(presentation), []);
});
