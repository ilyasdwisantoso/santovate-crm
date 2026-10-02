<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SubscriptionAddonPayment;
use App\Services\IpaymuService;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class IpaymuPaymentAssetController extends Controller
{
    public function subscriptionQr(Request $request, IpaymuService $ipaymu): Response
    {
        $reference = trim((string) $request->query('reference'));
        abort_if($reference === '', 422, 'Reference pembayaran wajib diisi.');

        $payment = Payment::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('reference_id', $reference)
            ->firstOrFail();

        return $this->proxyQr($ipaymu->directPaymentPresentation($payment->provider_payload ?? []), $ipaymu);
    }

    public function addonQr(Request $request, IpaymuService $ipaymu): Response
    {
        $reference = trim((string) $request->query('reference'));
        abort_if($reference === '', 422, 'Reference pembayaran wajib diisi.');

        $payment = SubscriptionAddonPayment::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('reference_id', $reference)
            ->firstOrFail();

        return $this->proxyQr($ipaymu->directPaymentPresentation($payment->provider_payload ?? []), $ipaymu);
    }

    private function proxyQr(array $presentation, IpaymuService $ipaymu): Response
    {
        abort_unless(($presentation['type'] ?? null) === 'qris', 404);

        $allowedHosts = $this->allowedHosts($ipaymu);
        $sources = array_values(array_filter([
            trim((string) ($presentation['qr_image'] ?? '')),
            trim((string) ($presentation['qr_template'] ?? '')),
        ]));

        foreach ($sources as $source) {
            if (!$this->trustedUrl($source, $allowedHosts)) {
                continue;
            }

            $response = $this->trustedGet($source, $allowedHosts, 'image/png,image/jpeg,image/webp,image/svg+xml,text/html,*/*');
            if (!$response || !$response->successful()) {
                continue;
            }

            $body = $response->body();
            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $mime = $this->detectImageMime($body, $contentType);

            if ($mime !== null) {
                return $this->imageResponse($body, $mime);
            }

            // Some iPaymu QR endpoints return an HTML wrapper rather than raw
            // image bytes. Extract the first trusted image URL and proxy it.
            $nested = $this->extractImageUrl($body, $source);
            if ($nested && $this->trustedUrl($nested, $allowedHosts)) {
                $nestedResponse = $this->trustedGet($nested, $allowedHosts, 'image/png,image/jpeg,image/webp,image/svg+xml,*/*');
                if ($nestedResponse && $nestedResponse->successful()) {
                    $nestedBody = $nestedResponse->body();
                    $nestedType = strtolower(trim(explode(';', (string) $nestedResponse->header('Content-Type'))[0]));
                    $nestedMime = $this->detectImageMime($nestedBody, $nestedType);
                    if ($nestedMime !== null) {
                        return $this->imageResponse($nestedBody, $nestedMime);
                    }
                }
            }
        }

        abort(502, 'QR image iPaymu tidak dapat diambil. Gunakan link fallback iPaymu pada halaman pembayaran.');
    }

    private function trustedGet(string $url, array $allowedHosts, string $accept, int $remainingRedirects = 2): ?HttpResponse
    {
        if (!$this->trustedUrl($url, $allowedHosts)) {
            return null;
        }

        $response = Http::timeout(12)
            ->retry(1, 180)
            ->accept($accept)
            ->withHeaders([
                'User-Agent' => 'SantovateCRM-PaymentAsset/1.1',
                'Referer' => (string) config('app.url'),
            ])
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if ($response->redirect() && $remainingRedirects > 0) {
            $location = trim((string) $response->header('Location'));
            $next = $this->resolveUrl($location, $url);
            return $next ? $this->trustedGet($next, $allowedHosts, $accept, $remainingRedirects - 1) : null;
        }

        return $response;
    }

    private function allowedHosts(IpaymuService $ipaymu): array
    {
        return collect(['sandbox', 'production'])
            ->map(fn (string $mode) => strtolower((string) parse_url($ipaymu->credentials($mode)['url'] ?? '', PHP_URL_HOST)))
            ->filter()
            ->push('sandbox.ipaymu.com')
            ->push('my.ipaymu.com')
            ->unique()
            ->values()
            ->all();
    }

    private function trustedUrl(string $url, array $allowedHosts): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return $scheme === 'https' && in_array($host, $allowedHosts, true);
    }

    private function extractImageUrl(string $html, string $baseUrl): ?string
    {
        if (!preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match)) {
            return null;
        }

        return $this->resolveUrl(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), $baseUrl);
    }

    private function resolveUrl(string $url, string $baseUrl): ?string
    {
        $url = trim($url);
        if ($url === '') return null;
        if (preg_match('#^https://#i', $url)) return $url;
        if (str_starts_with($url, '//')) return 'https:'.$url;

        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['host'])) return null;
        $origin = 'https://'.$base['host'];

        if (str_starts_with($url, '/')) return $origin.$url;

        $path = (string) ($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $origin.($dir ? $dir.'/' : '/').$url;
    }

    private function imageResponse(string $body, string $mime): Response
    {
        return response($body, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function detectImageMime(string $body, string $contentType): ?string
    {
        if (str_starts_with($contentType, 'image/')) return $contentType;
        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) return 'image/png';
        if (str_starts_with($body, "\xFF\xD8\xFF")) return 'image/jpeg';
        if (str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP') return 'image/webp';

        $trimmed = ltrim($body);
        if (str_starts_with($trimmed, '<svg') || str_starts_with($trimmed, '<?xml')) return 'image/svg+xml';
        return null;
    }
}
