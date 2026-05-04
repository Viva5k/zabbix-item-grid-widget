<?php declare(strict_types = 0);

namespace Modules\ZabbixItemGrid\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        $itemids = $this->fields_values['itemids'] ?? [];    
        $items = [];
        $history_data = [];
        $json_names = $this->fields_values['custom_names_json'] ?? '{}';
        $custom_labels = json_decode($json_names, true) ?: [];
        $color_graph = $this->fields_values['color_graph'] ?? '4794eb';
        $color_frame = $this->fields_values['color_frame'] ?? '5a5a5a';
        $color_bg = $this->fields_values['color_bg'] ?? '2f2f2f';

        $parser = new \CRelativeTimeParser();
        $from = $this->getInput('from', $this->fields_values['time_period']['from'] ?? 'now-1h');
        $to = $this->getInput('to', $this->fields_values['time_period']['to'] ?? 'now');
        
        $from_ts = time() - 3600;
        if ($from !== '' && $parser->parse($from) === \CParser::PARSE_SUCCESS) {
            $from_ts = $parser->getDateTime(true)->getTimestamp();
        } elseif ($from !== '') {
            $from_ts = strtotime($from) ?: $from_ts;
        }
        
        $to_ts = time();
        if ($to !== '' && $parser->parse($to) === \CParser::PARSE_SUCCESS) {
            $to_ts = $parser->getDateTime(false)->getTimestamp();
        } elseif ($to !== '') {
            $to_ts = strtotime($to) ?: $to_ts;
        }

        if ($itemids) {
            $items = \API::Item()->get([
                'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'value_type', 'units'],
                'selectHosts' => ['name'],
                'selectValueMap' => ['mappings'],
                'itemids' => $itemids,
                'preservekeys' => true
            ]);

            foreach ($items as $itemid => $item) {
                $hist = \API::History()->get([
                    'history'   => $item['value_type'],
                    'itemids'   => $itemid,
                    'time_from' => $from_ts,
                    'time_till' => $to_ts,
                    'sortfield' => 'clock',
                    'sortorder' => 'ASC',
                    'limit'     => 5000
                ]);
                $history_data[$itemid] = $hist;
            }
        }

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'user' => [
                'debug_mode' => $this->getDebugMode()
            ],     
            'items_data' => $items,
            'custom_labels' => $custom_labels,
            'from_ts' => $from_ts,
            'to_ts' => $to_ts,
            'color_graph' => $color_graph,
            'color_frame' => $color_frame,
            'color_bg' => $color_bg,
            'history_data' => $history_data
        ]));
    }
}
