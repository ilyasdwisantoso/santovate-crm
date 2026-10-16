<?php
// Child process for real InnoDB races. Only explicit isolated test credentials work.
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class,static function($app){
    Tests\Support\IsolatedDatabaseGuard::protect($app);
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config()->set('santovate.crm.native_individuals_enabled',true);
$actor=App\Models\User::findOrFail((int)$argv[1]);$mode=$argv[2];$contactId=(int)($argv[3]??0);
fwrite(STDOUT,"READY\n");fflush(STDOUT);
if(trim((string)fgets(STDIN))!=='go')exit(2);
try{
    if($mode==='convert'){
        $p=app(App\Services\WhatsAppLeadConversionService::class)->convert($actor,App\Models\WhatsAppInboundContact::findOrFail($contactId),['entity_type'=>'individual','individual_name'=>'Concurrent conversion']);
    }elseif($mode==='import'){
        $file=tempnam(sys_get_temp_dir(),'sv952-race-');
        try{
            file_put_contents($file,"Nama Perusahaan\nPT Concurrent Import\n");
            $importer=app(App\Services\ProspectImportService::class);
            $importer->withinOrganization($actor,function()use($actor,$file,$importer){
                $batch=App\Models\ImportBatch::create(['organization_id'=>$actor->organization_id,'filename'=>'concurrent.csv','imported_by'=>$actor->id,'assignment_mode'=>'unassigned']);
                $importer->import($file,$actor,$batch,'skip',['mode'=>'unassigned']);
            });
            $p=App\Models\Prospect::where('organization_id',$actor->organization_id)->where('company_name','PT Concurrent Import')->firstOrFail();
        }finally{unlink($file);}
    }else{
        $data=$mode==='duplicate'?['entity_type'=>'individual','individual_name'=>'Concurrent duplicate']:['company_name'=>'PT Concurrent Form'];
        $p=app(App\Services\ProspectCreationService::class)->create($actor,$data);
    }
    echo json_encode(['result'=>'created','id'=>$p->id])."\n";
}catch(Illuminate\Validation\ValidationException $e){
    echo json_encode(['result'=>array_key_first($e->errors())])."\n";
}catch(RuntimeException $e){
    if(str_contains($e->getMessage(),'Kapasitas'))echo json_encode(['result'=>'entitlement'])."\n";
    else throw $e;
}
