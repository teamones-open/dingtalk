<?php

namespace EasyDingTalk\Internal\Http\Support;

class File
{
    public static function getStreamExt(string $contents): string
    {
        if (!class_exists(\finfo::class)) {
            return '';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        return [
            'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif',
            'application/pdf' => '.pdf', 'audio/mpeg' => '.mp3', 'video/mp4' => '.mp4',
            'application/zip' => '.zip', 'text/plain' => '.txt',
        ][$mime] ?? '';
    }
}
