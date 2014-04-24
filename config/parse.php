<?php
/**
 * Created by ReRe Design.
 * User: Semyonchick
 * MailTo: semyonchick@gmail.com
 * DateTime: 28.05.13 14:43
 */

return array(
    'Классификатор' => array(
        'start' => 'Группа',
        'name' => 'group',
        'save' => true,
        'array' => array(
            'Ид' => 'external_id',
            'Наименование' => 'name',
            'Группы' => array(
                'start' => 'Группа',
                'name' => 'group',
                'array' => array(
                    'Ид' => 'external_id',
                    'Наименование' => 'name',
                    'Группы' => array(
                        'start' => 'Группа',
                        'name' => 'group',
                        'array' => array(
                            'Ид' => 'external_id',
                            'Наименование' => 'name',
                        ),
                    ),
                ),
            ),
        ),
    ),
    'Свойство' => array(
        'start' => 'Свойство',
        'name' => 'prop',
        'save' => true,
        'array' => array(
            'Ид' => 'external_id',
            'Наименование' => 'name',
        ),
    ),
    'Каталог' => array(
        'start' => 'Товар',
        'name' => 'item',
        'save' => true,
        'array' => array(
            'Ид' => 'external_id',
            'Артикул' => 'art',
            'Наименование' => 'name',
            'Группы' => 'parent_id',
            'Описание' => 'content',
            'Картинка' => 'image',
            'СтавкиНалогов' => 'tax',
            'Сопутствующая' => array(
                'start' => 'Сопутствующая',
                'name' => 'likeItem',
                'array' => array(
                    'Ид' => 'id',
                ),
            ),
            'ЗначенияСвойства' => array(
                'start' => 'ЗначенияСвойства',
                'name' => 'propValue',
                'array' => array(
                    'Ид' => 'id',
                    'Значение' => 'value',
                ),
            ),
            'ЗначениеРеквизита' => array(
                'start' => 'ЗначениеРеквизита',
                'name' => 'OtherValue',
                'array' => array(
                    'Наименование' => 'name',
                    'Значение' => 'value',
                ),
            ),
        ),
    ),
    'ТипыЦен' => array(
        'start' => 'ТипЦены',
        'name' => 'priceType',
        'save' => true,
        'array' => array(
            'Ид' => 'external_id',
            'Наименование' => 'name',
            'Валюта' => 'currency',
            'Налог' => 'tax',
            'УчтеноВСумме' => 'taxInclude',
        ),
    ),
    'Предложения' => array(
        'start' => 'Предложение',
        'name' => 'offer',
        'save' => true,
        'array' => array(
            'Ид' => 'external_id',
            'Артикул' => 'art',
            'Наименование' => 'name',
            'БазоваяЕдиница' => 'unit',
            'Количество' => 'count',
            'Цена' => array(
                'start' => 'Цена',
                'name' => 'price',
                'array' => array(
                    'ИдТипаЦены' => 'external_id',
                    'ЦенаЗаЕдиницу' => 'value',
                    'Представление' => 'text',
                    'Валюта' => 'currency',
                    'Единица' => 'unit',
                    'Коэффициент' => 'ratio',
                ),
            ),
        ),
    ),
    'Документ' => array(
        'start' => 'Документ',
        'name' => 'document',
        'array' => array(
            'Ид' => 'id',
            'Номер' => 'number',
            'Дата' => 'date',
            'ХозОперация' => 'operation',
            'Роль' => 'role',
            'Валюта' => 'currency',
            'Курс' => 'factor',
            'Сумма' => 'total',
            'Время' => 'time',
            'СрокПлатежа' => 'payDate',
            'Комментарий' => 'comment',
            'Контрагенты' => array(
                'start' => 'Контрагент',
                'name' => 'user',
                'array' => array(
                    'Ид' => 'id',
                    'Наименование' => 'name',
                    'ПолноеНаименование' => 'fullName',
                    'ИНН' => 'inn',
                    'Роль' => 'role',
                ),
            ),
            'Налоги' => array(
                'start' => 'Налог',
                'name' => 'tax',
                'array' => array(
                    'Наименование' => 'name',
                    'УчтеноВСумме' => 'include',
                    'Сумма' => 'total',
                ),
            ),
            'Товары' => array(
                'start' => 'Товар',
                'name' => 'item',
                'array' => array(
                    'Ид' => 'id',
                    'Наименование' => 'name',
                    'СтавкиНалогов' => 'tax',
                    'ЦенаЗаЕдиницу' => 'price',
                    'Количество' => 'quantity',
                    'Сумма' => 'total',
                    'Единица' => 'unit',
                    'Коэффициент' => 'factor',
                    'Налоги' => array(
                        'start' => 'Налог',
                        'name' => 'tax',
                        'array' => array(
                            'Наименование' => 'name',
                            'УчтеноВСумме' => 'include',
                            'Сумма' => 'total',
                            'Ставка' => 'value',
                        ),
                    ),
                ),
            ),

            'ЗначенияРеквизитов' => array(
                'start' => 'ЗначениеРеквизита',
                'name' => 'info',
                'array' => array(
                    'Наименование' => 'name',
                    'Значение' => 'value',
                ),
            ),
        ),
    ),
);