<?php
/** Explicit local-only disposable database runner. Never boot the development app. */
require dirname(__DIR__,2).'/vendor/autoload.php';
use Symfony\Component\Process\Process;
if(($argv[1]??'')!=='--provision-local'){fwrite(STDERR,"Requires --provision-local. Creates and removes a dedicated local test schema/account.\n");exit(2);}
$root=dirname(__DIR__,2);$env=Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$host=$env['DB_HOST']??'';$port=(int)($env['DB_PORT']??0);$development=$env['DB_DATABASE']??'';
if(!in_array($host,['127.0.0.1','localhost'],true)||$port!==3307||$development!=='crm_santovate'||!empty($env['DB_URL'])||!empty($env['DB_SOCKET'])){
    throw new RuntimeException('This provisioning command is restricted to audited local Santovate MariaDB on port 3307.');
}
$developmentConnection=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$env['DB_USERNAME']??'',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
$port=3317;
$admin=new PDO("mysql:host=127.0.0.1;port={$port};charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
$datadir=str_replace('\\','/',(string)$admin->query('SELECT @@datadir')->fetchColumn());
$expected=str_replace('\\','/',rtrim(sys_get_temp_dir(),'/\\')).'/santovate-mariadb-v952_';
if(!str_starts_with(strtolower($datadir),strtolower($expected))||!preg_match('/santovate-mariadb-v952_[a-f0-9]{12}\\/$/D',$datadir))throw new RuntimeException('Dedicated temporary MariaDB datadir required');
$signature=static function()use($developmentConnection,$development): string{
    $admin=$developmentConnection;
    $admin->exec('SET SESSION TRANSACTION READ ONLY');$admin->beginTransaction();
    try{
        $rows=$admin->query('SELECT * FROM `'.$development.'`.`prospects` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $migrations=$admin->query('SELECT * FROM `'.$development.'`.`migrations` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $s=$admin->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $s->execute([$development,'prospects']);return hash('sha256',serialize([$rows,$migrations,$s->fetchAll(PDO::FETCH_ASSOC)]));
    }finally{$admin->rollBack();$admin->exec('SET SESSION TRANSACTION READ WRITE');}
};
$before=$signature();
$token=bin2hex(random_bytes(6));$schema='santovate_crm_test_v952_'.$token;$username='santovate_test_'.$token;$password=bin2hex(random_bytes(24));
if(!preg_match('/\Asantovate_crm_test_v952_[a-f0-9]{12}\z/D',$schema)||!preg_match('/\Asantovate_test_[a-f0-9]{12}\z/D',$username))throw new RuntimeException('Unsafe generated target');
$createdSchema=false;$createdUser=false;$exit=1;$marker=bin2hex(random_bytes(16));$schemaIdentifier='`'.$schema.'`';$account=$admin->quote($username)."@'127.0.0.1'";
try{
    $admin->exec('CREATE DATABASE '.$schemaIdentifier.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$createdSchema=true;
    $admin->exec('CREATE TABLE '.$schemaIdentifier.'._isolated_test_owner (token VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
    $owner=$admin->prepare('INSERT INTO '.$schemaIdentifier.'._isolated_test_owner VALUES (?)');$owner->execute([$marker]);
    $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));$createdUser=true;
    // Escape underscores so MySQL database GRANT patterns cannot match other schemas.
    $grantIdentifier='`'.str_replace('_','\\_',$schema).'`';
    $admin->exec('GRANT ALL PRIVILEGES ON '.$grantIdentifier.'.* TO '.$account);
    $test=new PDO("mysql:host=127.0.0.1;port={$port};dbname={$schema};charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $grants=$test->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    if(count($grants)!==2)throw new RuntimeException('Unexpected account grants');
    foreach($grants as $grant){
        if(str_contains($grant,' ON *.* ')&&!str_starts_with($grant,'GRANT USAGE ON *.* '))throw new RuntimeException('Global privilege denied');
        if(str_contains($grant,'GRANT OPTION'))throw new RuntimeException('Grant option denied');
    }
    // Prove development tables cannot be selected by this test account.
    try{$test->query('SELECT id FROM `'.$development.'`.`prospects` LIMIT 0');throw new RuntimeException('Test account can read development data');}
    catch(PDOException $expected){if(!in_array($expected->errorInfo[1]??0,[1044,1142],true))throw $expected;}
    // The ownership marker was created before account provisioning so failed grants can be cleaned.
    echo 'Disposable database provisioned; development access denied. Server '.$test->query('SELECT VERSION()')->fetchColumn()."\n";
    $childEnv=['TEST_DB_HOST'=>'127.0.0.1','TEST_DB_PORT'=>(string)$port,'TEST_DB_DATABASE'=>$schema,'TEST_DB_USERNAME'=>$username,'TEST_DB_PASSWORD'=>$password];
    $process=new Process([PHP_BINARY,$root.'/vendor/bin/phpunit','--configuration',$root.'/phpunit.mysql.xml'],$root,$childEnv,null,120);
    $exit=$process->run(static function($type,$output){echo $output;});
    $test=null;
}finally{
    if($createdSchema){
        $check=$admin->query('SELECT token FROM '.$schemaIdentifier.'._isolated_test_owner')->fetchColumn();
        if(!hash_equals($marker,(string)$check))throw new RuntimeException('Ownership marker mismatch; cleanup refused');
        $admin->exec('DROP DATABASE '.$schemaIdentifier);
    }
    if($createdUser)$admin->exec('DROP USER '.$account);
    if(!hash_equals($before,$signature()))throw new RuntimeException('Development fingerprint changed');
    echo "Disposable resources removed; development prospect data, schema and migration history unchanged.\n";
}
exit($exit);
