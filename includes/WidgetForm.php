<?php declare(strict_types=0);

namespace Modules\NewZabbixItemGrid\Includes;

use Zabbix\Widgets\{CWidgetField, CWidgetForm};
use Zabbix\Widgets\Fields\{CWidgetFieldMultiSelectItem, CWidgetFieldTimePeriod, CWidgetFieldTextArea, CWidgetFieldIntegerBox, CWidgetFieldCheckBox};
use CWidgetsData;

class WidgetForm extends CWidgetForm
{

    public function addFields(): self
    {
        return $this
            ->addField(
                (new CWidgetFieldMultiSelectItem('itemids', 'Елементи'))
                    ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
            )
            ->addField(
                (new CWidgetFieldCheckBox('show_status', 'Показувати статус (online/offline)'))
                    ->setDefault(1)
            )
            ->addField(
                (new CWidgetFieldIntegerBox('grid_count', 'Кількість блоків сітки', 0, 10))
                    ->setDefault(2)
            )
            ->addField(
                (new CWidgetFieldTextArea('custom_names_json', 'Мітки елементів'))
            )
            ->addField(
                (new CWidgetFieldTimePeriod('time_period', 'Період часу'))
                    ->setDefault([
                        CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
                            CWidgetField::REFERENCE_DASHBOARD,
                            CWidgetsData::DATA_TYPE_TIME_PERIOD
                        )
                    ])
                    ->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
                    ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
            );
    }
}
