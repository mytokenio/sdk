<?php

namespace Http;

class ErrorCode
{
    //success
    const SUCCESS = 0;
    const SUCCESS_MSG = 'success';

    //server error
    const SERVER_ERROR = 990;

    //未知错误，请尝试重新提交
    const ERROR_UNKNOWN_B = 999;

    //unknown error
    const ERROR_UNKNOWN = 998;

    //param error
    const PARAMS_ERROR = 800001;

    //missing param error
    const MISSING_PARAMS_ERROR = 800003;

    //no role
    const FORBIDDEN = 800002;

    //other
    const OTHER = 99999;


    // 公用错误

    //request param cannot be null
    const TEXT_EMPTY_ERROR = 110001;

    //request param cannot be long
    const TEXT_LONG_ERROR = 110002;

    //error format
    const TEXT_FORMAT_ERROR = 110003;

    //request method error
    const REQUEST_METHOD_ERROR = 110004;

    //URL error
    const REQUEST_URL_ERROR = 110005;

    //to the moon
    const REQUEST_AUTH_ERROR = 110006;

    //under maintenance
    const API_UNDER_MAINTENANCE = 540002;

    /**
     * 根据错误码获取对应的描述
     * @param int $code
     * @return string
     */
    static function getMsg($code)
    {
        $data = self::processMsg();
        return $data[$code] ?? 'unknown';
    }

    /**
     * 解析当前文件 用于处理错误码和描述
     * 返回[ [code => msg], ... ]
     */
    static function processMsg($file = __FILE__)
    {
        $lines = file($file);

        //除杂
        $lines = array_map(function ($line) {
            return trim(str_replace(["\t", "\n"], '', $line));
        }, $lines);

        $data = [];
        foreach ($lines as $n => $line) {
            if (substr($line, 0, 2) == '//') {
                $value_line = $line;
                $code_line = $lines[$n + 1] ?? null;
                if ($code_line && substr($code_line, 0, 5) == 'const') {
                    $code = (int)str_replace(';', '', explode('=', $code_line)[1]);
                    $data[$code] = trim(str_replace('//', '', $value_line));
                }
            }
        }

        //load sub file
        $subData = [];
        if ($file == __FILE__ && get_called_class() != __CLASS__) {
            $subFilePath = (new \ReflectionClass(get_called_class()))->getFileName();
            $subData = self::processMsg($subFilePath);
        }
        foreach ($subData as $k => $v) {
            if (!isset($data[$k])) {
                $data[$k] = $v;
            }
        }
        return $data;
    }

}
