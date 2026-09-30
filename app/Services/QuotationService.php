<?php
namespace App\Services;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class QuotationService {
    public function totals(array $items,string $discountType,float $discountValue,float $taxPercent):array{
        $subtotal=0;$normalized=[];
        foreach(array_values($items) as $i=>$item){
            $qty=max(0,(float)($item['quantity']??1));$price=max(0,(float)($item['unit_price']??0));$lineDisc=min(100,max(0,(float)($item['discount_percent']??0)));$base=$qty*$price;$line=$base-($base*$lineDisc/100);$subtotal+=$line;
            $normalized[]=[
                'sort_order'=>$i+1,'solution_catalog_item_id'=>$item['solution_catalog_item_id']??null,
                'name'=>trim((string)($item['name']??'')),'description'=>$item['description']??null,
                'quantity'=>$qty,'unit'=>$item['unit']??'item','unit_price'=>$price,
                'internal_cost_snapshot'=>isset($item['internal_cost_snapshot'])?(float)$item['internal_cost_snapshot']:null,
                'recommended_price_snapshot'=>isset($item['recommended_price_snapshot'])?(float)$item['recommended_price_snapshot']:null,
                'minimum_price_snapshot'=>isset($item['minimum_price_snapshot'])?(float)$item['minimum_price_snapshot']:null,
                'discount_percent'=>$lineDisc,'line_total'=>round($line,2),
            ];
        }
        $discount=$discountType==='fixed'?min($subtotal,max(0,$discountValue)):($subtotal*min(100,max(0,$discountValue))/100);$after=max(0,$subtotal-$discount);$tax=$after*max(0,$taxPercent)/100;
        return['items'=>$normalized,'subtotal'=>round($subtotal,2),'discount_amount'=>round($discount,2),'tax_amount'=>round($tax,2),'grand_total'=>round($after+$tax,2)];
    }
    public function approvalRequired(array $data,array $totals):bool{
        $discountPercent=$data['discount_type']==='percent'?(float)$data['discount_value']:($totals['subtotal']>0?($totals['discount_amount']/$totals['subtotal']*100):0);
        $belowFloor=collect($totals['items'])->contains(function($item){$floor=$item['minimum_price_snapshot'];return $floor!==null && $floor>0 && (float)$item['unit_price']+0.01<(float)$floor;});
        $belowCost=collect($totals['items'])->contains(function($item){$cost=$item['internal_cost_snapshot'];return $cost!==null && $cost>0 && (float)$item['unit_price']+0.01<(float)$cost;});
        return $discountPercent>5||($data['pricing_type']??'standard')==='custom'||$totals['grand_total']>=50000000||$belowFloor||$belowCost;
    }
    public function snapshot(Quotation $q):array{$q->loadMissing('items');return['quotation'=>collect($q->getAttributes())->except(['updated_at'])->all(),'items'=>$q->items->map(fn($i)=>$i->only(['sort_order','solution_catalog_item_id','name','description','quantity','unit','unit_price','internal_cost_snapshot','recommended_price_snapshot','minimum_price_snapshot','discount_percent','line_total']))->values()->all()];}
    public function saveRevision(Quotation $q,User $actor,?string $reason=null):void{$q->revisions()->create(['revision_number'=>$q->revision_number,'created_by'=>$actor->id,'reason'=>$reason,'snapshot'=>$this->snapshot($q)]);}
    public function nextNumber(int $organizationId,string $prefix='QT'):string{return DB::transaction(function()use($organizationId,$prefix){$year=now()->format('Y');$last=Quotation::where('organization_id',$organizationId)->where('quotation_number','like',"{$prefix}-{$year}-%")->lockForUpdate()->orderByDesc('id')->value('quotation_number');$n=$last?(int)substr($last,-6)+1:1;return sprintf('%s-%s-%06d',$prefix,$year,$n);});}
}
