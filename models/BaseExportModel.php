<?php

/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 15:30
 */
class BaseExportModel
{
    /** @var $data string|SimpleXMLElement */
    public static function printData($data)
    {
        if ($data) {
            if (is_object($data)) $data = $data->asXML();
            header('Content-type: text/xml; charset=Windows-1251');
            echo iconv('utf8', 'cp1251', $data);
        } else echo 'Nothing to show';
        exit;
    }

    /**
     * @param $from SimpleXMLElement
     * @param $data array
     */
    public static function addParams($from, $data)
    {
        $params = $from->addChild('ЗначенияРеквизитов');
        foreach ($data as $key => $val)
            self::addData($params, $key, $val);
    }

    public static function bool($bool)
    {
        return (bool)$bool ? 'true' : 'false';
    }

    /**
     * @param $to SimpleXMLElement
     */
    public static function addData($to, $key, $val, $name = 'ЗначениеРеквизита', $keyName = 'Наименование', $valName = 'Значение')
    {
        $param = $to->addChild($name);
        $param->addChild($keyName, $key);
        $param->addChild($valName, $val);
    }

    public static function formatPrice($int)
    {
        $int = str_replace(',', '.', $int);
        $int = preg_replace("/[^0-9.]/", '', $int);
        return number_format((float)$int, 2, '.', '');
    }

    /**
     * @param $name string
     * @param $data array
     */
    public static function printXml($name, $data)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>' . '<' . $name . ' />');
        self::printData(self::addDataXml($xml, $data));
    }

    /**
     * @param $xml SimpleXMLElement
     * @param $data string|array
     * @param $before SimpleXMLElement
     * @return SimpleXMLElement
     */
    public static function addDataXml($xml, $data, $before = null)
    {
        if (is_array($data)) foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_int($key)) {
                    self::addDataXml($xml, $value, $before);
                    $xml = $before->addChild($xml->getName());
                } else self::addDataXml($xml->addChild($key), $value, $xml);
            } elseif ($value) $xml->addChild($key, CHtml::encode($value));
        }
    }
}