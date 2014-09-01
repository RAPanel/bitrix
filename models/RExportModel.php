<?php

/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 15:30
 */
class RExportModel extends BaseExportModel
{
    public $default = array(
        'inn' => 0,
    );

    public function order()
    {
        $model = Order::model()->findAll(array(
            'condition' => 't.status_id NOT IN(9,10,999) and t.id>0',
            'order' => 't.id desc',
        ));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>' . '<КоммерческаяИнформация />');
        $xml->addAttribute('ВерсияСхемы', '2.03');
        $xml->addAttribute('ДатаФормирования', date('Y-m-d'));
        foreach ($model as $row):
            if (!is_array($row->items_info)) continue;
            $contacts = $row->contact_info;
            if (empty($contacts['inn'])) $contacts['inn'] = $this->default['inn'];
            $row->contact_info = $contacts;

            $priceType = array();

            $document = $xml->addChild('Документ');
            $document->addChild('Ид', 'Web' . $row->id);
            $document->addChild('Номер', $row->id);
            $document->addChild('Дата', date('Y-m-d', $row->created));
            $document->addChild('ХозОперация', 'Заказ товара');
            $document->addChild('Роль', 'Продавец');
            $document->addChild('Валюта', 'руб');
            $document->addChild('Курс', '1');
            $document->addChild('Сумма', $row->total);
            $user = $document->addChild('Контрагенты')->addChild('Контрагент');
            $user->addChild('Ид', 'User' . $row->user_id);
            $user->addChild('ЛогинНаСайте', $row->user->email);
            $user->addChild('Наименование', $row->contact_info['username'] ? $row->contact_info['username'] : $row->contact_info['name']);
            $user->addChild('ПолноеНаименование', $row->contact_info['username'] ? $row->contact_info['username'] : $row->contact_info['name']);
            $row->contact_info = CMap::mergeArray($row->contact_info, (array)$row->user->company->characters);
            $user->addChild('ИНН', $row->contact_info['inn']);
            $user->addChild('Роль', 'Покупатель');
            if ($row->contact_info['type'])
                $type = $user->addChild('РеквизитыЮрЛица');
            else
                $type = $user->addChild('РеквизитыФизЛица');
            foreach (array(
                         'company' => 'ОфициальноеНаименование',
                         'address' => 'ЮридическийАдрес',
                         'inn' => 'ИНН',
                         'kpp' => 'КПП',
                     ) as $key => $val)
                if ($row->contact_info[$key]) $type->addChild($val, $row->contact_info[$key]);

            if (count($row->delivery_info)) {
                $address = $user->addChild('АдресРегистрации');
                $address->addChild('Представление', '');
                foreach (array(
                             'zip' => 'Индекс',
                             'city' => 'Населенный пункт',
                             'street' => 'Улица',
                             'house' => 'Дом',
                             'number' => 'Квартира',
                         ) as $key => $val)
                    if ($row->delivery_info[$key]) self::addData($address, $val, $row->delivery_info[$key], 'АдресноеПоле', 'Тип');
            }

            $contact = $user->addChild('Контакты');
            foreach (array(
                         'email' => 'Почта',
                         'phone' => 'Телефон',
                     ) as $key => $val)
                if ($row->contact_info[$key]) self::addData($contact, $val, $row->contact_info[$key], 'Контакт', 'Тип');
            $user->addChild('Роль', 'Покупатель');
            $user->addChild('ИдентификацияПоИНН', true);
            $items = $document->addChild('Товары');
            foreach ($row->items_info as $val):
                $price = self::formatPrice($val['price']);
                $type = Yii::app()->db->createCommand('SELECT `name` from price_type WHERE id=:id')->queryScalar(array('id' => ImportModel::getId($val['priceType'])));
                $priceType[$type] = $type;
                $item = $items->addChild('Товар');
                $item->addChild('Ид', $val['external_id']);
                $item->addChild('ИдКаталога', $val['parent_id']);
                $item->addChild('Наименование', str_replace('¼', '', CHtml::encode($val['name'])));
                $el = $item->addChild('БазоваяЕдиница ', 'шт');
                $el->addAttribute('Код', '796');
                $el->addAttribute('НаименованиеПолное', 'Штука');
                self::addParams($item, array(
                    'ВидНоменклатуры' => 'Товар',
                    'ТипНоменклатуры' => 'Товар',
                ));
                $item->addChild('ЦенаЗаЕдиницу', $price);
                $item->addChild('Количество', $val['quantity']);
                $item->addChild('Сумма', self::formatPrice($price * $val['quantity']));
                $item->addChild('Коэффициент', 1);
            endforeach;
            $document->addChild('Время', date('H:i:s', $row->created));
            $document->addChild('СрокПлатежа', date('Y-m-d', $row->created));
            $document->addChild('Комментарий', 'Категория цен ' . implode(',', $priceType) . '; ' . str_replace(array("\r", "\n"), ' ', $row->comment));
            self::addParams($document, array(
                'Метод оплаты' => $row->pay,
                'Способ доставки' => $row->delivery,
                'Заказ оплачен' => self::bool($row->pay_status),
                'Доставка разрешена' => self::bool($row->delivery_id),
                'Отменен' => self::bool($row->status_id == 99),
                'Финальный статус' => self::bool($row->status_id == 9),
                'Статус заказа' => '[N] Заказ ' . $row->status,
                'Дата изменения статуса' => $row->lastmod,
                'Сайт' => '[s2] Интернет-магазин (' . Yii::app()->name . ')',
            ));
        endforeach;

        self::printData($xml);
    }
}