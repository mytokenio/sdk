<?php
/**
 * Created by PhpStorm.
 * User: henter
 * Date: 2018/05/23
 * Time: 21:22
 */

class S3
{
    /**
     * @return string
     */
    public static function getServer()
    {
        return \SDK::config('s3', 'http://172.31.26.127:3333');
    }

    /**
     * @param $binary
     * @return string
     * @throws \Exception
     */
    public static function uploadBinary($binary)
    {
        $s3 = self::getServer().'/upload_binary';

        try {
            $client = new \GuzzleHttp\Client();
            $rsp = $client->request('POST', $s3, [
                'timeout' => 3,
                'form_params' => [
                    'binary' => $binary
                ],
            ]);
        } catch (\Exception $e) {
            \Log::getLogger('exception')->error('upload request exception', ['e' => $e]);
            //throw $e;
            return '';
        }

        if ($rsp->getStatusCode() == 200) {
            $data = \json_decode($rsp->getBody(), true);
            if ($data['code'] != 0) {
                \Log::getLogger('s3')->error('upload error '.$data['msg']);
                return '';
            }
            return current($data['data']['urls']);
        } else {
            \Log::getLogger('s3')->error('upload request failed');
            return '';
        }
    }


    /**
     * $file
        "name" => "ruby-china-logo.png"
        "type" => "image/png"
        "tmp_name" => "/private/var/tmp/phpLNKlEA"
        "error" => 0
        "size" => 12458
     * @param array $file
     * @return string
     * @throws \Exception
     */
    public static function upload(array $file)
    {
        $s3 = self::getServer().'/upload';

        if (!isset($file['name']) || !file_exists($file['tmp_name'])) {
            throw new \Exception('file error');
        }

        try {
            $client = new \GuzzleHttp\Client();
            $rsp = $client->request('POST', $s3, [
                'timeout' => 3,
                'multipart' => [
                    [
                        'name'     => 'files',
                        'contents' => fopen($file['tmp_name'], 'r'),
                        'filename' => $file['name'],
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            \Log::getLogger('exception')->error('upload request exception', ['e' => $e]);
            //throw $e;
            return '';
        }

        if ($rsp->getStatusCode() == 200) {
            $data = \json_decode($rsp->getBody(), true);
            if ($data['code'] != 0) {
                \Log::getLogger('s3')->error('upload error '.$data['msg']);
                return '';
            }
            return current($data['data']['urls']);
        } else {
            \Log::getLogger('s3')->error('upload request failed');
            return '';
        }
    }

}
