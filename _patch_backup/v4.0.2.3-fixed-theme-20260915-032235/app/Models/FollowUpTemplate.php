<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FollowUpTemplate extends Model {
    public const TYPE_NO_REPLY='no_reply';
    public const TYPE_CUSTOMER_REPLIED='customer_replied';
    public const TYPE_LEAD_AGE='lead_age';
    protected $fillable=['organization_id','business_configuration_id','key','name','trigger_type','wait_days','message','header_type','image_url','meta_template_name','meta_language','meta_status','meta_category','body_parameters','is_active','created_by'];
    protected function casts(): array { return ['wait_days'=>'integer','body_parameters'=>'array','is_active'=>'boolean']; }
}
