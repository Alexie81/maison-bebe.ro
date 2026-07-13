<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$root = __DIR__;
$accountRoot = dirname($root);
$tokenFile = $accountRoot . '/.maison-upgrade-token';
$archive = $accountRoot . '/maison-release-current.zip';
$provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
$expected = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

function splitSqlStatements(string $sql): array
{
    $statements=[];$buffer='';$quote=null;$escaped=false;$length=strlen($sql);
    for($index=0;$index<$length;$index++){
        $char=$sql[$index];$next=$index+1<$length?$sql[$index+1]:'';
        if($quote===null&&$char==='-'&&$next==='-'&&($index+2>=$length||ctype_space($sql[$index+2]))){while($index<$length&&$sql[$index]!=="\n")$index++;$buffer.="\n";continue;}
        if($quote===null&&$char==='#'){while($index<$length&&$sql[$index]!=="\n")$index++;$buffer.="\n";continue;}
        if($quote===null&&$char==='/'&&$next==='*'){$index+=2;while($index+1<$length&&!($sql[$index]==='*'&&$sql[$index+1]==='/'))$index++;$index++;continue;}
        if($quote!==null){$buffer.=$char;if($escaped){$escaped=false;continue;}if($char==='\\'){$escaped=true;continue;}if($char===$quote){if($next===$quote&&$quote!=='`'){$buffer.=$next;$index++;}else{$quote=null;}}continue;}
        if($char==="'"||$char==='"'||$char==='`'){$quote=$char;$buffer.=$char;continue;}
        if($char===';'){if(($trimmed=trim($buffer))!=='')$statements[]=$trimmed;$buffer='';continue;}
        $buffer.=$char;
    }
    if(($trimmed=trim($buffer))!=='')$statements[]=$trimmed;
    return $statements;
}

try {
    if (!class_exists(ZipArchive::class) || !is_file($archive)) {
        throw new RuntimeException('Pachetul de upgrade sau extensia ZIP lipsește.');
    }
    $removeWrongEntry = static function (string $path) use (&$removeWrongEntry): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) $removeWrongEntry($path . DIRECTORY_SEPARATOR . $child);
            @rmdir($path);
            return;
        }
        @unlink($path);
    };
    foreach (array_diff(scandir($root) ?: [], ['.', '..']) as $entry) {
        if (str_contains($entry, '\\')) $removeWrongEntry($root . DIRECTORY_SEPARATOR . $entry);
    }

    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new RuntimeException('Pachetul nu poate fi deschis.');
    for ($index=0;$index<$zip->numFiles;$index++) {
        $name=str_replace('\\','/',(string)$zip->getNameIndex($index));
        if($name===''||str_starts_with($name,'/')||preg_match('#(^|/)\.\.(/|$)#',$name)) throw new RuntimeException('Pachetul conține o cale invalidă.');
    }
    $fileCount=$zip->numFiles;
    if(!$zip->extractTo($root)) throw new RuntimeException('Fișierele nu au putut fi extrase.');
    $zip->close();

    require $root . '/bootstrap.php';
    $pdo=MaisonBebe\Core\Database::connection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,migration VARCHAR(190) NOT NULL UNIQUE,batch INT UNSIGNED NOT NULL,executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $batch=(int)$pdo->query('SELECT COALESCE(MAX(batch),0)+1 FROM migrations')->fetchColumn();
    $applied=[];
    foreach(glob($root.'/database/migrations/*.sql')?:[] as $migration){
        $name=basename($migration);$check=$pdo->prepare('SELECT 1 FROM migrations WHERE migration=?');$check->execute([$name]);if($check->fetchColumn())continue;
        $sql=file_get_contents($migration);if($sql===false)throw new RuntimeException('Migrarea nu poate fi citită: '.$name);
        foreach(splitSqlStatements($sql) as $statement)$pdo->exec($statement);
        $pdo->prepare('INSERT INTO migrations (migration,batch) VALUES (?,?)')->execute([$name,$batch]);$applied[]=$name;
    }
    foreach(glob($root.'/storage/cache/*')?:[] as $cacheFile)if(is_file($cacheFile)&&basename($cacheFile)!=='.gitkeep')@unlink($cacheFile);

    @unlink($archive);@unlink($tokenFile);@unlink(__FILE__);
    echo json_encode(['ok'=>true,'files'=>$fileCount,'migrations'=>$applied,'tables'=>(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log($exception->__toString());
    echo json_encode(['ok'=>false,'error'=>$exception->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
