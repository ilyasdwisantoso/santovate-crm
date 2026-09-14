<?php
namespace App\Services;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class ProspectStageService {
    private const TIMESTAMP_BY_STATUS=['dihubungi'=>'contacted_at','membalas'=>'replied_at','meeting'=>'meeting_at','demo'=>'demo_at','proposal'=>'proposal_at','negosiasi'=>'negotiation_at','deal'=>'deal_at'];
    public function __construct(private readonly BusinessConfigurationService $configs){}
    public function transition(Prospect $prospect,string $newStatus,User $actor,string $source='CRM'):Prospect{
        abort_unless($prospect->organization_id===$actor->organization_id,403);
        $allowed=collect($this->configs->pipelineFor($actor->organization))->pluck('key')->merge(['ditolak','tidak_cocok'])->unique()->all();
        abort_unless(in_array($newStatus,$allowed,true),422,'Stage tidak tersedia pada konfigurasi bisnis aktif.');
        if($prospect->status===$newStatus)return$prospect;
        return DB::transaction(function()use($prospect,$newStatus,$actor,$source){$old=$prospect->status;$updates=['status'=>$newStatus];if(in_array($newStatus,['dihubungi','membalas','meeting','demo','proposal','negosiasi','deal'],true)&&!$prospect->contacted_at)$updates['contacted_at']=now();if(in_array($newStatus,['dihubungi','meeting','demo','proposal','negosiasi','deal'],true)&&!$prospect->last_outbound_at)$updates['last_outbound_at']=now();if($newStatus==='membalas'&&!$prospect->last_customer_reply_at)$updates['last_customer_reply_at']=now();if($col=self::TIMESTAMP_BY_STATUS[$newStatus]??null)if(!$prospect->{$col})$updates[$col]=now();if(in_array($newStatus,['deal','ditolak','tidak_cocok'],true)&&!$prospect->closed_at)$updates['closed_at']=now();if(!in_array($newStatus,['deal','ditolak','tidak_cocok'],true))$updates['closed_at']=null;$prospect->update($updates);$labels=$this->configs->statusLabels($actor->organization);ProspectActivity::create(['prospect_id'=>$prospect->id,'user_id'=>$actor->id,'type'=>'status_change','title'=>'Status diperbarui','description'=>sprintf('%s -> %s · %s',$labels[$old]??$old,$labels[$newStatus]??$newStatus,$source),'occurred_at'=>now()]);return$prospect->refresh();});
    }
}
