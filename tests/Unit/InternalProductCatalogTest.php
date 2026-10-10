<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InternalProductCatalogTest extends TestCase
{
    public function test_internal_santovate_workspace_cannot_open_product_catalog(): void
    {
        $this->assertForbidden(new Organization(['slug' => 'santovate-internal']));
    }

    public function test_internal_flag_also_blocks_product_catalog(): void
    {
        $this->assertForbidden(new Organization(['slug' => 'custom-internal', 'settings' => ['internal' => true]]));
    }

    private function assertForbidden(Organization $organization): void
    {
        $user = new User(['organization_id' => 123]);
        $user->setRelation('organization', $organization);
        $request = Request::create('/products', 'GET');
        $request->setUserResolver(fn () => $user);

        try {
            (new ProductController())->index($request);
            $this->fail('An internal workspace should not be permitted to open Product Catalog.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
