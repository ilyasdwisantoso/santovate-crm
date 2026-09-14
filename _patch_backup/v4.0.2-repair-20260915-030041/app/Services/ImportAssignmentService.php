<?php
namespace App\Services;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Collection;
class ImportAssignmentService {
    public const MODE_FILE='file', MODE_SINGLE='single', MODE_ROUND_ROBIN='round_robin', MODE_UNASSIGNED='unassigned';
    public const MODES=[self::MODE_FILE,self::MODE_SINGLE,self::MODE_ROUND_ROBIN,self::MODE_UNASSIGNED];
    public function activeSales(int $organizationId): Collection { return User::where('organization_id',$organizationId)->where('role','sales')->where('is_active',true)->orderBy('name')->get(['id','name','email']); }
    public function resolveFileSales(?string $email,int $organizationId): ?User { if(!$email)return null; return User::where('organization_id',$organizationId)->where('role','sales')->where('is_active',true)->whereRaw('LOWER(email)=?',[mb_strtolower(trim($email))])->first(['id','name','email']); }
    public function resolve(array $row,array $options,int $index,int $organizationId,?Prospect $existing=null): array {
        $mode=$options['mode']??self::MODE_FILE;
        if($mode===self::MODE_UNASSIGNED)return ['user_id'=>null,'user'=>null,'next_round_robin_index'=>$index];
        if($mode===self::MODE_SINGLE){$u=$this->activeSales($organizationId)->firstWhere('id',(int)($options['single_sales_id']??0));return ['user_id'=>$u?->id,'user'=>$u,'next_round_robin_index'=>$index];}
        if($mode===self::MODE_ROUND_ROBIN){$allowed=collect($options['round_robin_sales_ids']??[])->map(fn($x)=>(int)$x);$sales=$this->activeSales($organizationId)->whereIn('id',$allowed)->values();if($sales->isEmpty())return ['user_id'=>null,'user'=>null,'next_round_robin_index'=>$index];$u=$sales[$index%$sales->count()];return ['user_id'=>$u->id,'user'=>$u,'next_round_robin_index'=>$index+1];}
        $u=$this->resolveFileSales($row['assigned_sales_email']??null,$organizationId);return ['user_id'=>$u?->id??$existing?->assigned_to,'user'=>$u,'next_round_robin_index'=>$index];
    }
}
