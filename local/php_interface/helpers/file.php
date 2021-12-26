<?php

namespace Helper;

class File
{

    protected $path;

    public function __counstruct(string $path, int $day = 3)
    {
        $this->path = $path;
        $this->day = 60 * 60 * 24 * $day;
    }

    public function cleanFiles(string $path)
    {
        $date_1 = time();

        $files = scandir($path);
            foreach ($files as $file) {
                $date_2 = filemtime($log.$file);
                if (($date_1 - $date_2) >= $this->day) {
                    if (is_dir($log.$file) === true) {
                        $dir = $log.$file;
                        $this->dirDel($dir);
                    } else {
                        unlink($log.$file);
                    }
                }
            }
    }

    public function dirDel($dir)
    {
        $target = opendir($dir);
        while (($entry = readdir($target)) !== false) {
            if ($entry != "." && $entry != "..") {
                $date_1 = time();
                $date_2 = filemtime($entry);
                if (($date_1 - $date_2) >= $this->day) {
                    if (is_dir($dir."/".$entry)) {
                        $this->dirDel($dir."/".$entry);
                    } else {
                        unlink($dir."/".$entry);
                    }
                }
            }
        }
        closedir($target);
        rmdir($dir);
    }
    
    public function getFileFullUrl($id) 
    {
        $path = \CFile::GetPath($id);
        if (!empty($path)) {
            $result = 'https://site.ru' . $path;
        }
        return $result ?? null;
    }

}