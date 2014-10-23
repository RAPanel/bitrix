<?php

/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 15:30
 */
class RImportModel extends BaseImportModel
{

    public function createElement($data, $type, $parent_id = false, $lastMod = false, $clear = false)
    {
        if (count($data)) switch ($type):
            case 'group':
                if (!$id = $this->getId($data['external_id']))
                    if ($id = $this->getPage('name', $data['name']))
                        $this->addId($id, $data['external_id']);
                    else
                        $id = $this->newPage($this->module_name, 1, $data, $parent_id);
                if ($id && $parent_id)
                    $this->movePage($id, $parent_id);
                if ($id && is_array($data['group']))
                    foreach ($data['group'] as $row)
                        $this->createElement($row, $type, $id, $lastMod, $clear);
                return $id;
            case 'prop':
                if (!$this->getId($data['external_id']))
                    return $this->newCharacter($data);
                return false;
            case 'item':
                if (!$data['external_id']) return false;
                elseif (!$id = $this->getId($data['external_id']))
                    if ($id = $this->getPage('name', $data['name']))
                        return $this->addId($id, $data['external_id']);
                    else
                        return $this->newPage($this->module_name, 0, $data);
                return $this->editPage($id, $data, $lastMod);
            case 'priceType':
                if (!$this->getId($data['external_id']))
                    return $this->newPriceType($data);
                return false;
        endswitch;
        return parent::createElement($data, $type, $lastMod);
    }

    public function newPage($type, $category, $data, $parent_id = false)
    {
        if (!$data['name']) return false;
        $model = new Page(Module::get($type));
        if ($category || $parent_id) $model->parent_id = $parent_id ? $parent_id : $model->findRoot()->id;
        $model->is_category = $category;
        $model->getCharacters();
        $model->setAttributes($this->readyProduct($data), false);
        if(!$model->parent_id) $model->parent_id = $model->findRoot()->id;
        if ($model->save(false)) {
            $this->addId($model->id, $data['external_id'], $model->tableName());
            $this->newPhoto($data['image'], $model->id);
        } else {
            echo 'error: ';
            CVarDumper::dump($model->errors);
            echo "\r\n";
            Yii::app()->end();
        }
        return $model->id;
    }

    public function editPage($id, $data, $lastMod)
    {
        $model = Page::model()->findByPk($id);
        $model->forceCharacters = true;
        if (!$model) return $this->newPage($this->module_name, '0', $data);
        if ($lastMod && $model->lastmod > $lastMod) return true;
        $this->newPhoto($data['image'], $model->id);
        $model->setAttributes($this->readyProduct($data), false);
        return $model->save(false);
    }

    public function newCharacter($data)
    {
        $model = Character::model()->findByAttributes(array('name' => $data['name']));
        if (!$model) {
            $model = new Character();
        }
        $model->setAttributes($data, false);
        if ($model->isNewRecord) $model->url = substr(Text::tagUrl($model->name), 0, 20) . '1c' . ucfirst(uniqid());
        $model->type = 'varchar';
        $model->inputType = 'text';
        $model->position = 'additional';
        $model->num = 100;
        if ($model->save(false))
            $this->addId($model->id, $data['external_id'], $model->tableName());

        /** @var Module $module */
        $module = Module::model()->findByPk(Module::get($this->module_name));
        $config = $module->getConfig();
        $config['characters'][] = $model->url;
        $module->config = $config;
        $module->save(false);

        $model->unsetAttributes();
        return true;
    }

    public function newPriceType($data)
    {
        $model = PriceType::model()->findByAttributes(array('name' => $data['name']));
        if (!$model) $model = new PriceType();
        $data['taxInclude'] = $data['tax']['taxInclude'] == 'false' ? 0 : 1;
        $data['tax'] = $data['tax']['name'];
        $model->setAttributes($data, false);
        if ($model->save(false))
            $this->addId($model->id, $data['external_id'], $model->tableName());
        return true;
    }

    public function offer($data, $lastmod = false)
    {
        if (!$id = $this->getId($data['external_id'])) return false;
        $sql = 'SELECT `id` FROM `' . Price::getTable() . '` WHERE `page_id`=:id AND `lastmod`>FROM_UNIXTIME(:lastmod)';
        if ($lastmod && Yii::app()->db->createCommand($sql)->queryScalar(compact('id', 'lastmod'))) return false;

        $sql = 'SELECT `id`, `type_id` FROM `' . Price::getTable() . '` WHERE `page_id`=:id';
        $prices = Yii::app()->db->createCommand($sql)->queryAll(true, compact('id'));
        $prices = CHtml::listData($prices, 'type_id', 'id');

        $priceData = array();
        foreach ($data['price'] as $price) {
            $type_id = $this->getId($price['external_id']);
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

    public function order($data, $lastmod = false)
    {
        if ($this->getId($data['external_id'])) return;
        $userData = $data['user'][0];
        $user = User::model()->findByAttributes(array('email' => $userData['email']));
        if (is_null($user)) {
            $criteria = new CDbCriteria();
            if ($data['user'][0]['inn'])
                $criteria->with[] = 'rInn';
            $criteria->compare('t.module_id', Module::get('company'));
            $criteria->compare('rInn.value', $data['Контрагенты']['Контрагент']['ИНН']);
            $company = Page::model()->find($criteria);
            if (is_null($company)) {
                echo "Company by inn #" . $data['Контрагенты']['Контрагент']['ИНН'] . " not found\r\n";
                return;
            }
            $user = $company->user;
        }

        $items = array();

        foreach ($data['item'] as $row) {
            /** @var PageBase $page */
            $page = Product::model()->findByPk($this->getId($row['external_id']));
            if (is_null($page)) $items[$row['external_id']] = array(
                'external_id' => $row['external_id'],
                'name' => $row['name'],
                'price' => $row['value'],
                'quantity' => $row['count'],
            );
            else $items[$page->id] = array(
                'id' => $page->id,
                'external_id' => $row['external_id'],
                'parent_id' => $page->parent->external_id,
                'name' => $row['name'],
                'href' => $page->href,
                'image' => $page->photo ? $page->getIco('mini', 'link') : false,
                'price' => $row['value'],
                'count' => $page->count,
                'quantity' => $row['count'],
            );
        }

        if (count($items) == 0) {
            echo "No items in order\r\n";
            return;
        }
        $model = new Order();
        $model->status_id = 9;
        $model->pay_status = 1;
        $model->user_id = $user->id;
        $model->total = $data['total'];
        $model->items_info = $items;
        $model->contact_info = $userData;
        if ($model->save()) {
            $this->addId($model->id, $data['external_id'], 'order');
        } else print_r($model->errors);
    }

    public function newPhoto($name, $pageId)
    {
        if ($name && (file_exists($file = Yii::app()->params['parseDir'] . $name) || file_exists($file = Yii::getPathOfAlias('webroot.data._upload1c.base') . DIRECTORY_SEPARATOR . $name)) && is_file($file)) {
            $filename = basename($file);
            $sql = 'SELECT `id` FROM `user_photo` WHERE `page_id`=:pageId AND `name`=:filename';
            if (Yii::app()->db->createCommand($sql)->queryScalar(compact('pageId', 'filename'))) return false;
            $target = Yii::getPathOfAlias('webroot.data._tmp') . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($target)) $result = true;
            else $result = Yii::app()->imageConverter->convert($file, $target, 'big');
            if ($result) {
                $size = getimagesize($target);
                $sql = 'SELECT `id` FROM `user_photo` WHERE `page_id`=:pageId AND `num`=1';
                $data = array(array(
                    'id' => Yii::app()->db->createCommand($sql)->queryScalar(compact('pageId')),
                    'user_id' => 1,
                    'page_id' => $pageId,
                    'name' => $filename,
                    'width' => $size[0],
                    'height' => $size[1],
                    'cropParams' => 'N;',
                    'num' => '1',
                ));
                @unlink($file);
                return DAO::execute('user_photo', $data, array('page_id', 'name', 'width', 'height'));
            } else return false;
        } else return false;
    }

    public function readyProduct($data)
    {
        foreach ($data as $key => $val) {
            if ($key == 'content') {
                $val = CHtml::tag('p', array(), nl2br(trim($val)));
            } elseif ($key == 'group') {
                $data['parent_id'] = $this->getId($val['external_id']);
                if (!$data['parent_id']) unset($data['parent_id']);
                $val = null;
            } elseif ($key == 'propValue') {
                $c = Characters::getAttributesByModule(Module::get($this->module_name));
                foreach ($val as $row) {
                    $data[$c[$this->getId($row['external_id'])]] = $row['value'];
                }
            } elseif ($key == 'likeProduct') {
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

    public function clearBase($time, $type)
    {
        switch ($type):
            case 'item':
                $sql = 'DELETE FROM `page` WHERE `module_id`=:module_id AND `is_category`=0 AND `lastmod` < FROM_UNIXTIME(' . $time . ')';
                Yii::app()->db->createCommand($sql)->execute(array('module_id' => Module::get($this->module_name)));
                $sql = 'DELETE e FROM `exchange_1c` e LEFT OUTER JOIN `character`c ON(c.id=e.id) WHERE e.type="character" AND ISNULL(c.id)';
                Yii::app()->db->createCommand($sql)->execute();
                $sql = 'DELETE e FROM `exchange_1c` e LEFT OUTER JOIN page p ON(p.id=e.id) WHERE e.type="page"  AND ISNULL(p.id)';
                return Yii::app()->db->createCommand($sql)->execute();
            case 'offer':
                $sql = 'DELETE FROM `' . Price::getTable() . '` WHERE `lastmod` < FROM_UNIXTIME(' . $time . ')';
                return Yii::app()->db->createCommand($sql)->execute();
            case 'photo':
                $photos = Yii::app()->db->createCommand('SELECT `name` FROM `user_photo`')->queryColumn();
                $dir = Yii::getPathOfAlias('webroot.data._tmp') . DIRECTORY_SEPARATOR;
                $files = scandir($dir);
                $remove = array_diff($files, $photos);
                foreach ($remove as $photo) if (is_file($dir . $photo)) unlink($dir . $photo);
                return count($remove);
        endswitch;
        return false;
    }
}