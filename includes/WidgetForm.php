<?php declare(strict_types = 0);

namespace Modules\ZabbixItemGrid\Includes;

use Zabbix\Widgets\{CWidgetField, CWidgetForm};
use Zabbix\Widgets\Fields\{CWidgetFieldMultiSelectItem, CWidgetFieldTimePeriod, CWidgetFieldTextArea, CWidgetFieldTextBox, CWidgetFieldCheckBox, CWidgetFieldColor};
use CWidgetsData;

class WidgetForm extends CWidgetForm {

    public function addFields(): self {
        return $this
            ->addField(
                new CWidgetFieldTextArea('custom_names_json')
                    ->setMaxLength(65535) 
            )
            ->addField(
                (new CWidgetFieldColor('color_graph', 'Graph color'))
                    ->setDefault('4794eb')
            )
            ->addField(
                (new CWidgetFieldColor('color_frame', 'Frame color'))
                    ->setDefault('5a5a5a')
            )
            ->addField(
                (new CWidgetFieldColor('color_bg', 'Background color'))
                    ->setDefault('2f2f2f')
            )
           ->addField(
                (new CWidgetFieldMultiSelectItem('itemids', 'Items'))
                    ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
            )
            ->addField(
                (new CWidgetFieldTimePeriod('time_period', 'Time period'))
                    ->setDefault([
                        CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
                            CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
                        )
                    ])
                    ->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
                    ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
            );
    }
}
