<?php

/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 15:30
 */
class ImportModel
{
    static $i = 0;
    static $module_name = 'product';

    public static function createElement($data, $type, $parent_id = false, $lastMod = false, $clear = false)
    {
        if (count($data)) switch ($type):
            case 'group':
                if (!$id = self::getId($data['external_id']))
                    if ($id = self::getPage('name', $data['name']))
                        self::addId($id, $data['external_id']);
                    else
                        $id = self::newPage(self::$module_name, 1, $data, $parent_id);
                if ($id && $parent_id)
                    self::movePage($id, $parent_id);
                if ($id && is_array($data['group']))
                    foreach ($data['group'] as $row)
                        self::createElement($row, $type, $id, $lastMod, $clear);
                return $id;
            case 'prop':
                if (!self::getId($data['external_id']))
                    return self::newCharacter($data);
                return false;
            case 'item':
                if (!$data['external_id']) return false;
                elseif (!$id = self::getId($data['external_id']))
                    if ($id = self::getPage('name', $data['name']))
                        return self::addId($id, $data['external_id']);
                    else
                        return self::newPage(self::$module_name, 0, $data);
                return self::editPage($id, $data, $lastMod);
            case 'priceType':
                if (!self::getId($data['external_id']))
                    return self::newPriceType($data);
                return false;
            case 'offer':
                return self::offer($data, $lastMod);
        endswitch;
        return false;
    }

    public static function getPage($name, $value)
    {
        $sql = 'SELECT `page_id` FROM `character_varchar` WHERE `character_id`=:id AND `lang_id`=:lang AND `value`=:value';
        $params = array(
            'id' => Characters::getIdByUrl($name),
            'lang' => Yii::app()->language,
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

    public static function newPage($type, $category, $data, $parent_id = false)
    {
        if (!$data['name']) return false;
        $model = new Page();
        $model->module_id = Module::get($type);
        $model->forceCharacters = true;
        $model->parent_id = $parent_id ? $parent_id : $model->findRoot()->id;
        $model->is_category = $category;
        $model->setAttributes(self::readyProduct($data), false);
        $model->setAttributes(self::readyProduct($data));
        if ($model->parent_id && $model->save(false)) {
            self::addId($model->id, $data['external_id'], $model->tableName());
            self::newPhoto($data['image'], $model->id);
        }
        return $model->id;
    }

    public static function editPage($id, $data, $lastMod)
    {
        $model = Page::model()->findByPk($id);
        if (!$model) return self::newPage(self::$module_name, '0', $data);
        if ($lastMod && $model->lastmod > $lastMod) return true;
        $model->forceCharacters = true;
        self::newPhoto($data['image'], $model->id);
        $model->setAttributes(self::readyProduct($data), false);
        $model->setAttributes(self::readyProduct($data));
        $result = $model->save(false);
        return $result;
    }

    public static function newCharacter($data)
    {
        $model = Character::model()->findByAttributes(array('name' => $data['name']));
        if (!$model) $model = new Character();
        $model->setAttributes($data, false);
        if (!$model->url) $model->url = Text::tagUrl($model->name) . '1c';
        $model->type = 'varchar';
        $model->inputType = 'text';
        $model->position = 'additional';
        $model->num = 100;
        if ($model->save(false))
            self::addId($model->id, $data['external_id'], $model->tableName());

        $module = Module::model()->findByPk(Module::get(self::$module_name));
        $config = $module->getConfig();
        $config['characters'][] = $model->url;
        $module->config = $config;
        $module->save(false);

        $model->unsetAttributes();
        return true;
    }

    public static function newPriceType($data)
    {
        $model = PriceType::model()->findByAttributes(array('name' => $data['name']));
        if (!$model) $model = new PriceType();
        $data['taxInclude'] = $data['tax']['taxInclude'] == 'false' ? 0 : 1;
        $data['tax'] = $data['tax']['name'];
        $model->setAttributes($data, false);
        if ($model->save(false))
            self::addId($model->id, $data['external_id'], $model->tableName());
        return true;
    }

    public static function offer($data, $lastmod = false)
    {
        if (!$id = self::getId($data['external_id'])) return false;
        $sql = 'SELECT `id` FROM `' . Price::getTable() . '` WHERE `page_id`=:id AND `lastmod`>FROM_UNIXTIME(:lastmod)';
        if ($lastmod && Yii::app()->db->createCommand($sql)->queryScalar(compact('id', 'lastmod'))) return false;

        $sql = 'SELECT `id`, `type_id` FROM `' . Price::getTable() . '` WHERE `page_id`=:id';
        $prices = Yii::app()->db->createCommand($sql)->queryAll(true, compact('id'));
        $prices = CHtml::listData($prices, 'type_id', 'id');

        $priceData = array();
        foreach ($data['price'] as $price) {
            $type_id = self::getId($price['external_id']);
            $priceData[] = array(
                'id' => $prices[$type_id],
                'page_id' => $id,
                'type_id' => $type_id,
                'count' => $data['count'] ? $data['count'] : 0,
                'value' => $price['value'],
                'unit' => $price['unit'],
            );
        }
        $update = array('value', 'count', 'lastmod' => 'NOW()');

        return DAO::execute(Price::getTable(), $priceData, $update);
    }

    public static function newPhoto($name, $pageId)
    {
        if ($name && (file_exists($file = Yii::app()->params['parseDir'] . $name) || file_exists($file = Yii::getPathOfAlias('webroot.data._upload1c.base') . DIRECTORY_SEPARATOR . $name)) && is_file($file)) {
            $filename = basename($file);
            $sql = 'SELECT `id` FROM `photo` WHERE `page_id`=:pageId AND `name`=:filename';
            if (Yii::app()->db->createCommand($sql)->queryScalar(compact('pageId', 'filename'))) return false;
            $target = Yii::getPathOfAlias('webroot.data._tmp') . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($target)) $result = true;
            else $result = Yii::app()->imageConverter->convert($file, $target, 'big');
            if ($result) {
                $size = getimagesize($target);
                $sql = 'SELECT `id` FROM `photo` WHERE `page_id`=:pageId AND `num`=1';
                $data = array(array(
                    'id' => Yii::app()->db->createCommand($sql)->queryScalar(compact('pageId')),
                    'parent_id' => $pageId,
                    'name' => $filename,
                    'width' => $size[0],
                    'height' => $size[1],
                    'cropParams' => 'N;',
                    'order' => '1',
                ));
                @unlink($file);
                return DAO::execute('photo', $data, array('page_id', 'name', 'width', 'height'));
            } else return false;
        } else return false;
    }

    public static function readyProduct($data)
    {
        foreach ($data as $key => $val) {
            if ($key == 'content') {
                $val = CHtml::tag('p', array(), nl2br(trim($val)));
            } elseif ($key == 'group') {
                $data['parent_id'] = self::getId($val['external_id']);
                $val = null;
            } elseif ($key == 'propValue') {
                $c = Characters::getAttributesByModule(Module::get(self::$module_name));
                foreach ($val as $row)
                    $data[$c[self::getId($row['external_id'])]] = $row['value'];
            } elseif ($key == 'likeItem') {
                $result = CHtml::listData($val, 'external_id', 'external_id');
                $sql = 'SELECT GROUP_CONCAT(`id`) FROM `exchange_1c` WHERE `external_id` IN ("' . implode('","', $result) . '")';
                $val = Yii::app()->db->createCommand($sql)->queryScalar();
            } elseif (is_string($val)) {
                $val = str_replace(array("\r", "\n"), '', $val);
                $val = preg_replace('/[\s]{2,}/', ' ', $val);
                $val = trim($val);
            } elseif (is_array($val)) $val = current($val);
            if (in_array($key, array('propValue', 'OtherValue', 'tax', 'typeName'))) unset($val);
            if (isset($val)) $data[$key] = $val;
            else unset($data[$key]);
        }
        return $data;
    }

    public static function clearBase($time, $type)
    {
        switch ($type):
            case 'item':
                $sql = 'DELETE FROM `page` WHERE `module_id`=:module_id AND `is_category`=0 AND `lastmod` < FROM_UNIXTIME(' . $time . ')';
                return Yii::app()->db->createCommand($sql)->execute(array('module_id' => Module::get(self::$module_name)));
            case 'offer':
                $sql = 'DELETE FROM `' . Price::getTable() . '` WHERE `lastmod` < FROM_UNIXTIME(' . $time . ')';
                return Yii::app()->db->createCommand($sql)->execute();
            case 'photo':
                $photos = Yii::app()->db->createCommand('SELECT `name` FROM `photo`')->queryColumn();
                $dir = Yii::getPathOfAlias('webroot.data._tmp') . DIRECTORY_SEPARATOR;
                $files = scandir($dir);
                $remove = array_diff($files, $photos);
                foreach ($remove as $photo) if (is_file($dir . $photo)) unlink($dir . $photo);
                return count($remove);
        endswitch;
        return false;
    }
}