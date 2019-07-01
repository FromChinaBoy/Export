<?php
/**
 * Created by PhpStorm.
 * User: hiho
 * Date: 18-10-19
 * Time: 下午6:28
 */

namespace zzhpeng\Export;

class Export
{
    //最高导出限制
    const EXPORT_LIMIT_MAX = 400000;
    //每次查询限制条数
    const EXPORT_LIMIT_ROW = 500;

    /**
     * @author: zzhpeng
     * Date: 2019/6/28
     * @param $fileName
     */
    public static function setExportHeader($fileName)
    {
        //设置CSV头部并且输出
        header("Content-type:text/csv;charset=utf-8");
        header('Cache-Control: no-store, no-cache, must-revalidate,' . '  pre-check=0, post-check=0, max-age=0');
        header("Content-Disposition:File Transfer");
        header("Content-Disposition:attachment;filename=" . $fileName . '.csv');
        header('Pragma: no-cache'); // HTTP/1.0
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Content-Transfer-Encoding: binary');
        header('Last-Modified: ' . gmdate(DATE_RFC1123));
        header('Expires: ' . gmdate(DATE_RFC1123));
        ini_set('max_execution_time', 0);

    }

    /**
     * @author: zzhpeng
     * Date: 2019/6/28
     * @param               $model
     * @param string        $fileName
     * @param array         $fields
     * @param \Closure|null $recordPreprocess
     */
    public static function go($model, string $fileName, array $fields, \Closure $recordPreprocess = null)
    {
        self::setExportHeader($fileName);
        //打开PHP文件句柄，php://output 表示直接输出到浏览器
        $fp = fopen('php://output', 'a');
        //兼容window打开乱码
        fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        //最高限制N
        $max = self::EXPORT_LIMIT_MAX;
        //每次查询N条
        $row = self::EXPORT_LIMIT_ROW;

        $countModel = clone $model;
        $totalCount = $countModel->count();
        if ($totalCount > $max) {
            echo '导出数量超过' . $max . '条，请分批导出';
            exit;
        }

        //定义标题
        $head = array_keys($fields);
        //将标题名称通过fputcsv写到文件句柄
        fputcsv($fp, $head);

        $pages = ceil($max / $row);
        for ($i = 1; $i <= $pages; $i++) {
            //每次拉取一页
            $offset = $row * ($i - 1);
            $data = $model->limit($offset, $row)->select();
            //如果数据为空,则停止
            if ($data->isEmpty()) {
                break;
            }
            foreach ($data as $key => &$value) {
                $rowData = [];
                $preprocessData = [];
                if (is_callable($recordPreprocess)) {
                    $preprocessData = $recordPreprocess($value);
                }
                foreach ($fields as $fieldHead => $fieldValue) {
                    if (is_string($fieldValue)) {
                        $rowData[] = self::getValueByStr($value, $fieldValue);
                    } elseif (is_array($fieldValue)) {
                        //是数组
                        if (count($fieldValue) > 0 && count($fieldValue) <= 2 && is_string($fieldValue[0])) {
                            $defaultValue = null;
                            if (isset($fieldValue[1])) {
                                $defaultValue = $fieldValue[1];
                            }
                            $rowData[] = self::getValueByStr($value, $fieldValue[0], $defaultValue);
                        } else {
                            $rowData[] = '';
                        }


                    } else if (is_callable($fieldValue)) {
                        //如果是方法
                        $rowData[] = $fieldValue($value, $preprocessData);
                    } else {
                        $rowData[] = '';
                    }
                }
                //清空缓存
                ob_flush();
                flush();
                //导出
                fputcsv($fp, $rowData);
                unset($data[$key]);
                unset($rowData);
                unset($preprocessData);
            }

            unset($data);
        }
//        }
        //文件关闭
        fclose($fp);
        exit;

    }

    /**
     * @author: zzhpeng
     * Date: 2019/6/28
     * @param        $model
     * @param        $fieldValueArr
     * @param string $default
     *
     * @return null|string
     */
    private static function getModelReationValue($model, $fieldValueArr, $default = '')
    {
        $result = null;
        foreach ($fieldValueArr as $fieldValueValue) {
            if (empty($result)) {
                $result = $model->$fieldValueValue;
            } else {
                $result = $result->$fieldValueValue;
            }
            if (empty($result)) {
                if ($default) {
                    $result = $default;
                }
                break;
            }
        }
        return $result;
    }

    /**
     * 获取模型的某个字符串key
     * @author: zzhpeng
     * Date: 2019/6/28
     * @param Model  $model
     * @param string $str
     * @param string $default
     *
     * @return null|string
     */
    private static function getValueByStr($model, string $str, $default = '')
    {
        $fieldKey = trim(trim($str), '.');
        //判断数据的字段是否存在
        $fieldKeyArr = explode('.', $fieldKey);
        if (count($fieldKeyArr) == 1) {
            //不包含点号
            if (!empty($model->$fieldKey)) {
                return $model->$fieldKey;
            } else {
                return $default;
            }
        } else {
            return self::getModelReationValue($model, $fieldKeyArr, $default);
        }
    }

}