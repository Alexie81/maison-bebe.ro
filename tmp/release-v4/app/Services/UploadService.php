<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use finfo;

final class UploadService
{
    private const TYPES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    public function image(string $field,string $alt=''):?int
    {
        if(empty($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE){return null;}
        $file=$_FILES[$field];if($file['error']!==UPLOAD_ERR_OK||$file['size']>8*1024*1024){throw new HttpException(422,'Imaginea nu a putut fi încărcată sau depășește 8 MB.');}
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!isset(self::TYPES[$mime])){throw new HttpException(422,'Formatul imaginii nu este acceptat.');}
        $info=getimagesize($file['tmp_name']);if(!$info){throw new HttpException(422,'Fișierul nu este o imagine validă.');}
        $name=bin2hex(random_bytes(20)).'.'.self::TYPES[$mime];$dir=BASE_PATH.'/public/uploads/'.date('Y/m');if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)){throw new HttpException(500,'Directorul de upload nu poate fi creat.');}
        $path=$dir.'/'.$name;if(!move_uploaded_file($file['tmp_name'],$path)){throw new HttpException(500,'Imaginea nu a putut fi salvată.');}
        $public='/uploads/'.date('Y/m').'/'.$name;$statement=Database::connection()->prepare('INSERT INTO media_assets (path,mime_type,original_name,alt_text,width,height,size_bytes) VALUES (?,?,?,?,?,?,?)');$statement->execute([$public,$mime,basename((string)$file['name']),$alt,$info[0],$info[1],$file['size']]);return (int)Database::connection()->lastInsertId();
    }
}

