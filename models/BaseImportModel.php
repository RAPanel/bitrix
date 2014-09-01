<?php

/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 15:30
 */
class BaseImportModel
{
    static $module_name;

    public static function createElement($data, $type, $lastMod = false)
    {
        if (count($data)) return self::$type($data, $lastMod);
        return false;
    }

    public static function getPage($name, $value)
    {
        $sql = 'SELECT `id` FROM `character_varchar` cv JOIN `page` ON(`id`=`page_id`) WHERE `character_id`=:id AND `value`=:value';
        $params = array(
            'id' => Characters::getIdByUrl($name),
            'value' => $value,
        );
        return Yii::app()->db->createCommand($sql)->queryScalar($params);
    }

    public static function getId($value)
    {
        $sql = 'SELECT `id` FROM `exchange_1c` WHERE `external_id`=:value';
        return Yii::app()->db->createCommand($sql)->queryScalar(compact('value'));
    }

    public static function addId($id, $external_id, $type = 'page')
    {
        return DAO::execute('exchange_1c', array(compact('id', 'type', 'external_id')), array('id', 'type'));
    }

    public static function movePage($id, $parent_id)
    {
        $sql = 'SELECT `id` FROM `page` WHERE `id`=:id AND `parent_id`=:parent_id';
        $params = compact('id', 'parent_id');
        if (Yii::app()->db->createCommand($sql)->execute($params) == 0) {
            $page = Page::model()->findByPk($id);
            $page->parent_id = $parent_id;
            $page->save();
        }
        return true;
    }

    public static function clearBase($time, $type)
    {
        return false;
    }
}