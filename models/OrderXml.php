<?php
/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 15:30
 */

class OrderXml extends Order
{
    public $xml;
    public $default = array(
        'inn' => '183466738360',
    );

    static function printThis()
    {
        $model = new self;

        if ($data = $model->orders()) {
            header('Content-type: text/xml; charset=Windows-1251');
            echo iconv('utf8', 'cp1251', $model->orders());
        }
        exit;
    }

    public function orders()
    {
        $model = $this->findAll(array(
            'condition' => 't.status_id NOT IN(10,999) and t.id>0',
            'order' => 't.id desc',
        ));

        $this->xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>' . '<КоммерческаяИнформация />');
        $this->xml->addAttribute('ВерсияСхемы', '2.03');
        $this->xml->addAttribute('ДатаФормирования', date('Y-m-d'));
        foreach ($model as $row):
            if (!is_array($row->items_info)) continue;
            if($row->contact_info['region'] && $row->contact_info['region'] != Yii::app()->user->getRegionId()) continue;

            $contacts = $row->contact_info;
            if (empty($contacts['inn'])) $contacts['inn'] = $this->default['inn'];
            if (empty($row->user_id)) $row->user_id = 'Order' . $row->id;
            $row->contact_info = $contacts;

            $priceType = array();

            $document = $this->xml->addChild('Документ');
            $document->addChild('Ид', 'Resan' . ucfirst(Yii::app()->controller->action->id) . $row->id);
            $document->addChild('Номер', $row->id);
            $document->addChild('Дата', date('Y-m-d', $row->created));
            $document->addChild('ХозОперация', 'Заказ товара');
            $document->addChild('Роль', 'Продавец');
            $document->addChild('Валюта', 'руб');
            $document->addChild('Курс', '1');
            $document->addChild('Сумма', $row->total);
            $user = $document->addChild('Контрагенты')->addChild('Контрагент');
            $user->addChild('Ид', 'User' . $row->user_id);
            $user->addChild('Наименование', $row->contact_info['username']);
            $user->addChild('ПолноеНаименование', $row->contact_info['username']);
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
                    if ($row->delivery_info[$key]) $this->addData($address, $val, $row->delivery_info[$key], 'АдресноеПоле', 'Тип');
            }

            $contact = $user->addChild('Контакты');
            foreach (array(
                         'email' => 'Почта',
                         'phone' => 'Телефон',
                     ) as $key => $val)
                if ($row->contact_info[$key]) $this->addData($contact, $val, $row->contact_info[$key], 'Контакт', 'Тип');
            $user->addChild('Роль', 'Покупатель');
            $items = $document->addChild('Товары');
            foreach ($row->items_info as $val):
                $price = $this->formatPrice($val['price']);
                $type = Yii::app()->db->createCommand('SELECT `name` from price_type WHERE id=:id')->queryScalar(array('id' => ImportModel::getId($val['priceType'])));
                $priceType[$type] = $type;
                $item = $items->addChild('Товар');
                $item->addChild('Ид', $val['external_id']);
                $item->addChild('ИдКаталога', $val['parent_id']);
                $item->addChild('Наименование', $val['name']);
                $el = $item->addChild('БазоваяЕдиница ', 'шт');
                $el->addAttribute('Код', '796');
                $el->addAttribute('НаименованиеПолное', 'Штука');
//                $el->addAttribute('МеждународноеСокращение', 'PCE');
                $this->addParams($item, array(
                    'ВидНоменклатуры' => 'Товар',
                    'ТипНоменклатуры' => 'Товар',
                ));
                $item->addChild('ЦенаЗаЕдиницу', $price);
                $item->addChild('Количество', $val['quantity']);
                $item->addChild('Сумма', $this->formatPrice($price * $val['quantity']));
                $item->addChild('Коэффициент', 1);
            endforeach;
            $document->addChild('Время', date('H:i:s', $row->created));
            $document->addChild('СрокПлатежа', date('Y-m-d', $row->created));
            $document->addChild('Комментарий', 'Категория цен ' . implode(',', $priceType) . '; ' . str_replace(array("\r", "\n"), ' ', $row->comment));
            $this->addParams($document, array(
                'Метод оплаты' => $row->pay,
                'Способ доставки' => $row->delivery,
                'Заказ оплачен' => $this->bool($row->pay_status),
                'Доставка разрешена' => $this->bool($row->delivery_id),
                'Отменен' => $this->bool($row->status_id == 99),
                'Финальный статус' => $this->bool($row->status_id == 9),
                'Статус заказа' => '[N] Заказ принят',
                'Дата изменения статуса' => $row->lastmod,
//                'Сайт' => '[s2] Интернет-магазин (ResanOpt)',
            ));
        endforeach;

        return $this->xml->asXML();
    }

    /**
     * @param $from SimpleXMLElement
     * @param $data array
     */
    public function addParams($from, $data)
    {
        $params = $from->addChild('ЗначенияРеквизитов');
        foreach ($data as $key => $val)
            $this->addData($params, $key, $val);
    }

    public function bool($bool)
    {
        return (bool)$bool ? 'true' : 'false';
    }

    /**
     * @param $to SimpleXMLElement
     */
    public function addData($to, $key, $val, $name = 'ЗначениеРеквизита', $keyName = 'Наименование', $valName = 'Значение')
    {
        $param = $to->addChild($name);
        $param->addChild($keyName, $key);
        $param->addChild($valName, $val);
    }

    public function formatPrice($int)
    {
        $int = str_replace(',', '.', $int);
        $int = preg_replace("/[^0-9.]/", '', $int);
        return number_format((int)$int, 2, '.', '');
    }
}